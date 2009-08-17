<?php
App::import(array('HttpSocket', 'File', 'Folder', 'Xml'));
class InstallTask extends Shell {

/**
 * HttpSocket instance.
 * @var string
 */
	var $Socket = null;

	function execute() {
	}

	function git($maintainer = null) {
		$maintainer = ($maintainer === null) ? 'cakephp-plugin-provider' : $maintainer;
		$this->Socket = new HttpSocket();

		$this->__doGitInstall($maintainer);
	}

	function zip($maintainer = null) {
		$maintainer = ($maintainer === null) ? 'cakephp-plugin-provider' : $maintainer;
		$this->Socket = new HttpSocket();

		$this->__doZipInstall($maintainer);
	}

/**
 * Install a function from github using git
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 */
	function __doGitInstall($maintainer = null) {
		$maintainer = ($maintainer === null) ? 'cakephp-plugin-provider' : $maintainer;
		$validCommands = array();
		$availablePlugins = $this->__listServerPlugins($maintainer);

		foreach ($availablePlugins as $key => $plugin) {
			$name = str_replace('-', '_', $plugin['name']);
			$name = Inflector::humanize($name);
			if (substr_count($name, 'Plugin') > 0) {
				$name = substr_replace($name, '', strrpos($name, ' Plugin'), strlen(' Plugin'));
			}
			$this->out($key+1 . ". {$name} Plugin");
			$validCommands[] = $key+1;
		}

		$validCommands[] = 'q';
		$enteredPlugin = null;

		while ($enteredPlugin === null) {
			$enteredPlugin = $this->in(__("Enter a number from the list above  or 'q' or nothing to exit", true), null, 'q');

			if ($enteredPlugin === 'q') {
				$this->out(__("Exit", true));
				$this->_stop();
			} elseif (in_array($enteredPlugin, $validCommands)) {
				// So now we actually have to install this plugin...

				// Find the original Repository if possible
				$repoURL = $this->__findOriginalRepository($availablePlugins[$enteredPlugin-1]['name'], $maintainer);

				// Get the name under which the user would like to place this plugin
				$pluginName = $this->in(__("Enter a name for this plugin or 'q' to exit", true), null, 'q');

				if ($pluginName === 'q') {
					$this->out(__("Exit", true));
					$this->_stop();
				} else {

					if (($topLevelDirectory = Cache::read('Plugins.application.topDirectory')) === false) {
						// Check if this app isn't the root of the git project
						$topLevelDirectory = $this->in(__("If you aren't running this from the toplevel of the working tree, specify the full toplevel path. Leave off trailing slashes. Press enter if this is the toplevel."), null, ROOT);
						if ($topLevelDirectory !== '') {
							Cache::write('Plugins.application.topDirectory', $topLevelDirectory);
						} else {
							$topLevelDirectory = ROOT;
							Cache::write('Plugins.application.topDirectory', ROOT);
						}
					}
					$this->out($topLevelDirectory);
					if (($pluginRelativePath = Cache::read('Plugins.application.pluginPath')) === false) {
						// See if there are any special paths for this plugin
						$pluginRelativePath = $this->in(__("What is the relative path of the plugin folder in respect to the toplevel of the working tree? Include the directory of the plugins folder, and leave off the beginning and trailing slash."), null, 'app/plugins');
						if ($pluginRelativePath !== '') {
							Cache::write('Plugins.application.pluginPath', $pluginRelativePath);
						} else {
							$pluginRelativePath = 'app/plugins';
							Cache::write('Plugins.application.pluginPath', 'app/plugins');
						}
					}
					$this->out("Adding Plugin Submodule to {$topLevelDirectory}/{$pluginRelativePath}/{$pluginName}...");
					$this->out(shell_exec("cd {$topLevelDirectory} ; git submodule add {$repoURL} {$pluginRelativePath}/{$pluginName}"));
					$this->out("Initializing Plugin Submodule...");
					$this->out(shell_exec("cd {$topLevelDirectory} ; git submodule init"));
					$this->out("Updating Plugin Submodule...");
					$this->out(shell_exec("cd {$topLevelDirectory} ; git submodule update"));
				}
			} else {
				$enteredPlugin = null;
			}
		}
	}

/**
 * Install a function from github using git
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 */
	function __doZipInstall($maintainer = null) {
		$maintainer = ($maintainer === null) ? 'cakephp-plugin-provider' : $maintainer;
		$validCommands = array();

		// Make sure the temporary plugin folder exists
		$this->__checkPluginFolder();

		$this->out("Fetching list of all Plugins...");
		$availablePlugins = $this->__listServerPlugins($maintainer);

		foreach ($availablePlugins as $key => $plugin) {
			$name = str_replace('-', '_', $plugin['name']);
			$name = Inflector::humanize($name);
			if (substr_count($name, 'Plugin') > 0) {
				$name = substr_replace($name, '', strrpos($name, ' Plugin'), strlen(' Plugin'));
			}
			$this->out($key+1 . ". {$name} Plugin");
			$validCommands[] = $key+1;
		}

		$validCommands[] = 'q';
		$enteredPlugin = null;

		while ($enteredPlugin === null) {
			$enteredPlugin = $this->in(__("Enter a number from the list above  or 'q' or nothing to exit", true), null, 'q');

			if ($enteredPlugin === 'q') {
				$this->out(__("Exit", true));
				$this->_stop();
			} elseif (in_array($enteredPlugin, $validCommands)) {
				// So now we actually have to install this plugin...

				// Find the original Repository if possible
				$this->out("Fetching the Repository Zip URL...");
				$response = $this->__findZipURL($availablePlugins[$enteredPlugin-1]['name'], $maintainer);
				$repoPath = $response['maintainer'] . '-' . $response['repositoryName'] . '-' . $response['useBranch'];
				$zipURL = $response['download'];


				$this->out("\nRepository Zip URL is {$zipURL}");
				// Get the name under which the user would like to place this plugin
				$pluginName = $this->in(__("Enter a name for this plugin or 'q' to exit", true), null, 'q');

				if ($pluginName === 'q') {
					$this->out(__("Exit", true));
					$this->_stop();
				} else {

					$this->out("\nFetching plugin package...");
					$data = $this->Socket->get($zipURL);

					$tempZipPath = trim(TMP . DS . 'plugins' . DS . $pluginName . '.zip');
					$tempRepoPath = trim(TMP . DS . 'plugins' . DS . $repoPath);
					$installPath = trim(APP . 'plugins');

					$zipHandler = new File($tempZipPath);
					$this->out("\nZip Downloaded to {$tempZipPath}");
					$zipHandler->write($data);

					App::import('Vendor', 'GithubPlugins.PclzipLib', array('file' => 'pclzip' . DS . 'pclzip.lib.php'));
					$archive = new PclZip($tempZipPath);
					$this->out("Writing {$installPath} from {$tempZipPath}...");
					if (($v_result_list = $archive->extract(
							PCLZIP_OPT_PATH, $installPath,
							PCLZIP_OPT_REMOVE_PATH, $repoPath,
							PCLZIP_OPT_ADD_PATH, $pluginName)) == 0) {
						die("Error : ".$archive->errorInfo(true));
					}

					$tempHandler = new Folder();
					$tempHandler->delete($tempRepoPath);

					$this->out("\nDone.\n");
				}
			} else {
				$enteredPlugin = null;
			}
		}
	}

/**
 * List all the plugins in the github plugin account
 *
 * @return array
 * @author Jose Diaz-Gonzalez
 */
	function __listServerPlugins($maintainer = null) {
		$maintainer = ($maintainer === null) ? 'cakephp-plugin-provider' : $maintainer;
		$githubServer = "http://github.com/api/v2/xml/";
		$pluginList = array();

		Cache::set(array('duration' => '+7 days'));
		if (($pluginList = Cache::read('Plugins.server.list.' . date('W-Y'))) === false) {
			$xmlResponse = new Xml($this->Socket->get("{$githubServer}repos/show/{$maintainer}"));
			$pluginList = Set::reverse($xmlResponse);
			Cache::set(array('duration' => '+7 days'));
			Cache::write('Plugins.server.list.' . date('W-Y'), $pluginList);
		}

		return $pluginList['Repositories']['Repository'];
	}

/**
 * Returns the url of the original repository
 *
 * @param string $repositoryName
 * @return string
 * @author Jose Diaz-Gonzalez
 **/
	function __findOriginalRepository($repositoryName, $maintainer = null) {
		$maintainer = ($maintainer === null) ? 'cakephp-plugin-provider' : $maintainer;
		$githubServer = "http://github.com/api/v2/xml/";
		$originalRepository = array();

		Cache::set(array('duration' => '+7 days'));
		if (($originalRepository = Cache::read("Plugins.server.{$repositoryName}.original" . date('W-Y'))) === false) {
			$pluginNetwork = $this->__getNetwork($githubServer, $maintainer, $repositoryName);

			foreach ($pluginNetwork['Network']['Network'] as $repository) {
				if ($repository['fork']['value'] === 'false') {
					$maintainer = $repository['owner'];
					$repositoryName = $repository['name'];
					break;
				}
			}
			$originalRepository = "git://github.com/{$maintainer}/{$repositoryName}.git";
			Cache::set(array('duration' => '+7 days'));
			Cache::write("Plugins.server.{$repositoryName}.original" . date('W-Y'), $originalRepository);
		}

		return $originalRepository;
	}

/**
 * Returns an array containing a repository's network
 *
 * @param string $githubServer
 * @param string $maintainer
 * @param string $repositoryName
 * @return array
 * @author Jose Diaz-Gonzalez
 **/
	function __getNetwork($githubServer, $maintainer, $repositoryName) {
		$maintainer = ($maintainer === null) ? 'cakephp-plugin-provider' : $maintainer;
		$pluginNetwork = array();

		Cache::set(array('duration' => '+7 days'));
		if (($pluginNetwork = Cache::read("Plugins.server.{$repositoryName}.network" . date('W-Y'))) === false) {
			$xmlResponse = new Xml(
				$this->Socket->get(
					"{$githubServer}repos/show/{$maintainer}/{$repositoryName}/network"));
			$pluginNetwork = Set::reverse($xmlResponse);
			Cache::set(array('duration' => '+7 days'));
			Cache::write("Plugins.server.{$repositoryName}.network" . date('W-Y'), $pluginNetwork);
		}
		return $pluginNetwork;
	}

/**
 * Checks to see if the temporary plugin folder exists
 * and creates it if it does not
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 **/
	function __checkPluginFolder() {
		$tempHandler = new Folder();
		$tempPath = trim(TMP);
		$pluginPath = trim(TMP . 'plugins');

		$tempHandler->cd($tempPath);
		$temp = $tempHandler->ls();
		foreach ($temp[0] as $tempFolder) {
			if ($tempFolder !== 'plugins') {
				$tempHandler->create($pluginPath);
			}
		}
	}

/**
 * Returns the url of the zip containing the latest repository commit
 *
 * @param string $repositoryName
 * @return array
 * @author Jose Diaz-Gonzalez
 **/
	function __findZipURL($repositoryName, $maintainer) {
		$maintainer = ($maintainer === null) ? 'cakephp-plugin-provider' : $maintainer;
		$githubServer = 'http://github.com/api/v2/xml/';

		$pluginNetwork = $this->__getNetwork($githubServer, $maintainer, $repositoryName);

		foreach ($pluginNetwork['Network']['Network'] as $repository) {
			if ($repository['fork']['value'] === 'false') {
				$maintainer = $repository['owner'];
				$repositoryName = $repository['name'];
				break;
			}
		}

		$branches = $this->__findBranches($githubServer, $maintainer, $repositoryName, true);

		if($branches['master']) {
			$useBranch = $branches['hash']['master'];
		} else {
			// Lets just use the first branch available
			$branchKey = $branches['branches'][0];
			$useBranch = $branches['hash'][$branchKey];
		}

		// The following might need to be downloaded via the socket class in order to ensure that the zip is created
		$response = array();

		$response['maintainer'] = $maintainer;
		$response['repositoryName'] = $repositoryName;
		$response['useBranch'] = $useBranch;
		$response['zipball'] = "http://github.com/{$maintainer}/{$repositoryName}/zipball/master";
		$response['waitdownload'] = "http://waitdownload.github.com/{$maintainer}-{$repositoryName}-{$useBranch}.zip";

		$this->out("Hardcore Archiving...");
		$xmlResponse = $this->Socket->get($response['zipball']);
		$this->out("Redirecting...");
		$xmlResponse = $this->Socket->get($response['waitdownload'] );

		$response['download'] = "http://download.github.com/{$maintainer}-{$repositoryName}-{$useBranch}.zip";

		return $response;
	}

/**
 * Returns an array of containing an array of all the branch names
 * and an array containing the latest branch commit hash
 * as well as whether a master branch exists
 *
 * @param string $maintainer
 * @param string $repositoryName
 * @return array
 * @author Jose Diaz-Gonzalez
 **/
	function __findBranches($server, $maintainer = null, $repositoryName, $master = false) {
		$maintainer = ($maintainer === null) ? 'cakephp-plugin-provider' : $maintainer;
		$branches = array();

		Cache::set(array('duration' => '+7 days'));
		if (($branches = Cache::read("Plugins.server.{$repositoryName}.branches" . date('W-Y'))) === false) {
			$xmlResponse = new Xml(
				$this->Socket->get(
					"{$server}repos/show/{$maintainer}/{$repositoryName}/branches"));
			$arrayResponse = Set::reverse($xmlResponse);

			$branches = array();
			$branches['branches'] = array_keys($arrayResponse['Branches']);
			$branches['hash'] = $arrayResponse['Branches'];
			$branches['master'] = false;

			if ($master) {
				foreach ($branches['branches'] as $branch) {
					if ($branch == 'master') {
						$branches['master'] = true;
						break;
					}
				}
			}

			Cache::set(array('duration' => '+7 days'));
			Cache::write("Plugins.server.{$repositoryName}.branches" . date('W-Y'), $branches);
		}

		return $branches;
	}
}
?>