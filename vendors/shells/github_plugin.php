<?php

App::import(array('HttpSocket', 'File', 'Xml'));
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
				$zipURL = $this->__findZipURL($availablePlugins[$enteredPlugin-1]['name']);
				$this->out("Repository Zip URL is {$zipURL}");
				// Get the name under which the user would like to place this plugin
				$pluginName = $this->in(__("Enter a name for this plugin or 'q' to exit", true), null, 'q');
				
				if ($pluginName === 'q') {
					$this->out(__("Exit", true));
					$this->_stop();
				} else {

					$this->out("\nFetching plugin package...");
					$data = $this->Socket->get($zipURL);

					$this->out("Done.");
					debug($data);die;
					$files = explode($this->fileDelimiter, $data);

					if(count($files) < 1) {
						$this->out("There seems to be a problem with the plugin package you selected. Please try again later.");
						die();
					}

					$this->out("");

					foreach($files as $file) {
						$parts = explode($this->metaDelimiter, $file);
						if(count($parts) < 2) {
							continue;
						}

						$path = trim(APP . 'plugins' . DS . $selectedPluginName . $parts[0]);
						$this->out("Writing: $path");

						$newFolder = new Folder();
						$newFolder->create(dirname($path));

						$newFile = new File($path);
						$newFile->write($parts[1]);
					}

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
	 * @return void
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
	 * @return void
	 * @author Jose Diaz-Gonzalez
	 */
	function __listServerPlugins() {
		$githubServer = "http://github.com/api/v2/xml/";
		$githubUser = 'cakephp-plugin-provider';
		$xmlResponse = new Xml(
			$this->Socket->get(
				$githubServer . 'repos/show/' . $githubUser));
		$arrayResponse = Set::reverse($xmlResponse);
		return $arrayResponse['Repositories']['Repository'];
	}

	/**
	 * Returns the url of the original repository
	 *
	 * @return string
	 * @author Jose Diaz-Gonzalez
	 **/
	function __findOriginalRepository($repositoryName) {
		$githubServer = "http://github.com/api/v2/xml/";
		$maintainer = 'cakephp-plugin-provider';
		$xmlResponse = new Xml(
			$this->Socket->get(
				"{$githubServer}repos/show/{$maintainer}/{$repositoryName}/network"));
		$arrayResponse = Set::reverse($xmlResponse);
		foreach ($arrayResponse['Network']['Network'] as $repository) {
			if ($repository['fork']['value'] === 'false') {
				$maintainer = $repository['owner'];
				$repositoryName = $repository['name'];
				break;
			}
		}
		return "git://github.com/{$githubUser}/{$repositoryName}.git";
	}

/**
 * Returns the url of the zip containing the latest repository commit
 *
 * @return string
 * @author Jose Diaz-Gonzalez
 **/
	function __findZipURL($repositoryName) {
		$githubServer = 'http://github.com/api/v2/xml/';
		$maintainer = 'cakephp-plugin-provider';

		$xmlResponse = new Xml(
			$this->Socket->get(
				"{$githubServer}repos/show/{$maintainer}/{$repositoryName}/network"));
		$arrayResponse = Set::reverse($xmlResponse);

		foreach ($arrayResponse['Network']['Network'] as $repository) {
			if ($repository['fork']['value'] === 'false') {
				$maintainer = $repository['owner'];
				$repositoryName = $repository['name'];
				break;
			}
		}

		$link = "http://github.com/{$maintainer}/{$repositoryName}/zipball/master";

		$branches = $this->__findBranches($githubServer, $maintainer, $repositoryName, true);

		if($branches['master']) {
			$useBranch = $branches['hash']['master'];
		} else {
			// Lets just use the first branch available
			$branchKey = $branches['branches'][0];
			$useBranch = $branches['hash'][$branchKey];
		}

		// The following might need to be downloaded via the socket class in order to ensure that the zip is created
		// $zipLink = "http://waitdownload.github.com/{$maintainer}-{$repositoryName}-{$useBranch}.zip";
		$zipLink = "http://download.github.com/{$maintainer}-{$repositoryName}-{$useBranch}.zip";
		return $zipLink;
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
		$xmlResponse = new Xml(
			$this->Socket->get(
				"{$server}repos/show/{$maintainer}/{$repositoryName}/branches"));
		$arrayResponse = Set::reverse($xmlResponse);

		$response = array();
		$response['branches'] = array_keys($arrayResponse['Branches']);
		$response['hash'] = $arrayResponse['Branches'];
		$response['master'] = false;

		if ($master) {
			foreach ($response['branches'] as $branch) {
				if ($branch == 'master') {
					$response['master'] = true;
					break;
				}
			}
		}

		return $response;
	}
}
?>