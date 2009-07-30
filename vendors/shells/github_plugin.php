<?php
App::import(array('HttpSocket', 'File', 'Folder', 'Xml'));
/**
 * Github Plugin management shell.
 *
 * @package default
 */
class GithubPluginShell extends Shell {

/**
 * HttpSocket instance.
 * @var string
 */
	var $Socket = null;

/**
 * Main shell logic.
 *
 * @return void
 * @author John David Anderson
 */
	function main() {

		if(isset($this->params['server'])) {
			$this->serverUri = $this->params['server'];
			$this->out('Using specified server: ' . $this->serverUri);
		}

		$this->Socket = new HttpSocket();

		if(!empty($this->command)) {
			$this->command = substr($this->command, 0, 1);
		}

		$this->__run();
	}

/**
 * Main application flow control.
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 */
	function __run() {
		$validCommands = array('l', 'v', 's', 'g', 'z', 'p', 'u', 'q');

		while (empty($this->command)) {
			$this->out("Github Plugin Server");
			$this->hr();
			$this->out("[L]ist Installed Plugins");
			$this->out("[V]iew Available Plugins");
			$this->out("[S]earch Available Plugins");
			$this->out("[G]it Install Plugin as Submodule");
			$this->out("[Z]ip Install Plugin from an archive");
			$this->out("[P]ull all Plugin Submodule Updates");
			$this->out("[U]pdate a specific Plugin Submodule");
			$this->out("[Q]uit");
			$temp = $this->in("What command would you like to perform?", $validCommands, 'i');
			if (in_array(strtolower($temp), $validCommands)) {
				$this->command = $temp;
			} else {
				$this->out("Try again.");
			}
		}

		switch ($this->command) {
			case 'l' :
				$this->__doList();
				break;
			case 'v' :
				$this->__doView();
				break;
			case 's' :
				$this->__doSearch();
				break;
			case 'g' :
				$this->__doSubmoduleInstall();
				break;
			case 'z' :
				$this->__doZipInstall();
				break;
			case 'p' :
				$this->__doPull();
				break;
			case 'u' :
				$this->__doUpdate();
				break;
			case 'q' :
				$this->out(__("Exit", true));
				$this->_stop();
				break;
		}
	}

/**
 * List all the plugins installed
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 */
	function __doList() {
		$this->out("\nThis is a list of currently installed plugins in your application.");
		foreach ($this->__listPlugins() as $key => $plugin) {
			$this->out(" " . $key+1 . ". " . Inflector::humanize($plugin) . " Plugin");
		}
	}

/**
 * View all available plugins
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 */
	function __doView() {
		$this->out("\nThis is a list of currently active plugins on the server.");
		$availablePlugins = $this->__listServerPlugins();
		foreach ($availablePlugins as $key => $plugin) {
			$name = str_replace('-', '_', $plugin['name']);
			$name = Inflector::humanize($name);
			if (substr_count($name, 'Plugin') > 0) {
				$name = substr_replace($name, '', strrpos($name, ' Plugin'), strlen(' Plugin'));
			}
			$this->out($key+1 . ". {$name} Plugin");
		}
	}

/**
 * Search all available plugins
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 **/
	function __doSearch() {
		$query = $this->in(__("Enter a search term or 'q' or nothing to exit", true), null, 'q');
		$this->out("Grabbing all plugins...");
		$availablePlugins = $this->__listServerPlugins();

		$this->out("Searching all plugins for query...");
		$results = array();
		foreach ($availablePlugins as $plugin) {
			if ((stristr($plugin['name'], $query) !== false) or (stristr($plugin['description'], $query) !== false)) {
				$results[] = $plugin;
			}
		}
		
		if (empty($results)) {
			$this->out("No results found. Sorry.");
		} else {
			foreach ($results as $key => $result) {
				$name = str_replace('-', '_', $result['name']);
				$name = Inflector::humanize($name);
				if (substr_count($name, 'Plugin') > 0) {
					$name = substr_replace($name, '', strrpos($name, ' Plugin'), strlen(' Plugin'));
				}
				$this->out($key+1 . ". {$name} Plugin");
			}
		}
	}

/**
 * Install a function from github using git
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 */
	function __doGitInstall() {
		$validCommands = array();
		$availablePlugins = $this->__listServerPlugins();

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
				$repoURL = $this->__findOriginalRepository($availablePlugins[$enteredPlugin-1]['name']);

				// Get the name under which the user would like to place this plugin
				$pluginName = $this->in(__("Enter a name for this plugin or 'q' to exit", true), null, 'q');
				
				if ($pluginName === 'q') {
					$this->out(__("Exit", true));
					$this->_stop();
				} else {

					// Check if this app isn't the root of the git project
					$topLevelDirectory = $this->in(__("If you aren't running this from the toplevel of the working tree, specify the full toplevel path. Press enter if this is the toplevel."), null, '');

					if ($topLevelDirectory !== null) {

						// See if there are any special paths for this plugin
						$pluginRelativePath = $this->in(__("What is the relative path of the plugin folder in respect to the toplevel of the working tree? Include the directory of the plugins folder, and leave off the beginning and trailing slash."), null, '');
						if ($pluginRelativePath !== null) {
							$this->out("Adding Plugin Submodule to {$topLevelDirectory}/{$pluginRelativePath}/{$pluginName}...");
							$this->out(shell_exec("cd {$topLevelDirectory} ; git submodule add {$repoURL} {$pluginRelativePath}/{$pluginName}"));
							$this->out("Initializing Plugin Submodule...");
							$this->out(shell_exec("cd {$topLevelDirectory} ; git submodule init"));
							$this->out("Updating Plugin Submodule...");
							$this->out(shell_exec("cd {$topLevelDirectory} ; git submodule update"));
						}
					} else {
						$this->out("Adding Plugin Submodule to plugins/{$pluginName}...");
						$this->out(shell_exec("git submodule add {$repoURL} plugins/{$pluginName}"));
						$this->out("Initializing Plugin Submodule...");
						$this->out(shell_exec("git submodule init"));
						$this->out("Updating Plugin Submodule...");
						$this->out(shell_exec("git submodule update"));
					}
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
	function __doZipInstall() {
		$validCommands = array();

		// Make sure the temporary plugin folder exists
		$this->__checkPluginFolder();

		$this->out("Fetching list of all Plugins...");
		$availablePlugins = $this->__listServerPlugins();

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
				$response = $this->__findZipURL($availablePlugins[$enteredPlugin-1]['name']);
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
 * Pull the updates for all plugin submodules
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 **/
	function __doPull() {
		$this->out("\nUpdating submodules...\n");
		$installedPlugins = $this->__listPlugins();

		foreach ($installedPlugins as $key => $plugin) {
			$this->out(Inflector::humanize($plugin) . " Plugin");
			$this->out(shell_exec("cd " . $this->params['working'] . "/plugins/{$installedPlugins[$key]} ; git remote update "));
			$this->out(shell_exec("cd " . $this->params['working'] . "/plugins/{$installedPlugins[$key]} ; git merge origin/master "));
		}
		$this->out("Remember to commit all wanted changes.");
	}

/**
 * Pull the update for a specific plugin submodule
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 **/
	function __doUpdate() {
		$this->out("\nThe following is a list of all installed submodules");

		$validCommands = array();
		$installedPlugins = $this->__listPlugins();
		foreach ($installedPlugins as $key => $plugin) {
			$this->out(" " . $key+1 . ". " . Inflector::humanize($plugin) . " Plugin");
			$validCommands[] = $key+1;
		}

		$validCommands[] = 'q';
		$enteredPlugin = null;

		while ($enteredPlugin === null) {
			$enteredPlugin = $this->in(__("Enter a number from the list above  or 'q' or nothing to exit", true), null, 'q');
			
			if ($enteredPlugin === 'q') {
				// Quit
				$this->out(__("Exit", true));
				$this->_stop();
			} elseif (in_array($enteredPlugin, $validCommands)) {
				// cd into the plugins directory and update it
				$this->out(Inflector::humanize($plugin) . " Plugin");
				$this->out(shell_exec("cd " . $this->params['working'] . "/plugins/{$installedPlugins[$enteredPlugin-1]} ; git remote update "));
				$this->out(shell_exec("cd " . $this->params['working'] . "/plugins/{$installedPlugins[$enteredPlugin-1]} ; git merge origin/master "));
			} else {
				$enteredPlugin = null;
			}
		}
	}

/**
 * Loads a list of plugins in the current app.
 *
 * @return array
 * @author John David Anderson
 */
	function __listPlugins() {
		$pluginsFolder = new Folder(APP . 'plugins');
		$filesAndDirectories = $pluginsFolder->ls(true, true);
		return $filesAndDirectories[0];
	}

/**
 * List all the plugins in the github plugin account
 *
 * @return array
 * @author Jose Diaz-Gonzalez
 */
	function __listServerPlugins() {
		$githubServer = "http://github.com/api/v2/xml/";
		$githubUser = 'cakephp-plugin-provider';

		Cache::set(array('duration' => '+7 days'));
		if (($pluginList = Cache::read('Plugins.server.list.' . date('W-Y'))) === false) {
			$xmlResponse = new Xml($this->Socket->get("{$githubServer}repos/show/{$githubUser}"));
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
	function __findOriginalRepository($repositoryName) {
		$githubServer = "http://github.com/api/v2/xml/";
		$maintainer = 'cakephp-plugin-provider';

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
			$gitRepository = "git://github.com/{$githubUser}/{$repositoryName}.git";
			Cache::set(array('duration' => '+7 days'));
			Cache::write("Plugins.server.{$repositoryName}.original" . date('W-Y'), $gitRepository);
		}

		return $originalRepository;
	}

/**
 * Returns the url of the zip containing the latest repository commit
 *
 * @param string $repositoryName
 * @return array
 * @author Jose Diaz-Gonzalez
 **/
	function __findZipURL($repositoryName) {
		$githubServer = 'http://github.com/api/v2/xml/';
		$maintainer = 'cakephp-plugin-provider';

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
 * Returns an array containing a repository's network
 *
 * @param string $githubServer
 * @param string $maintainer
 * @param string $repositoryName
 * @return array
 * @author Jose Diaz-Gonzalez
 **/
	function __getNetwork($githubServer, $maintainer, $repositoryName) {
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
 * Returns an array of containing an array of all the branch names
 * and an array containing the latest branch commit hash
 * as well as whether a master branch exists
 *
 * @param string $maintainer
 * @param string $repositoryName
 * @return array
 * @author Jose Diaz-Gonzalez
 **/
	function __findBranches($server, $maintainer, $repositoryName, $master = false) {
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
}
?>