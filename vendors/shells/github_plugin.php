<?php

App::import(array('HttpSocket', 'File', 'Xml'));
/**
 * Github Plugin management shell.
 *
 * @package default
 */
class PlugShell extends Shell {

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
		$validCommands = array('l', 'v', 'i', 'p', 'u', 'q');

		while (empty($this->command)) {
			$this->out("Github Plugin Server");
			$this->out("---------------------------------------------------------------");
			$this->out("[L]ist Installed Plugins");
			$this->out("[V]iew Available Plugins");
			$this->out("[I]nstall Plugin as Submodule or from Zip");
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
			case 'i' :
				$this->__doInstall();
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
		foreach ($this->__listServerPlugins() as $key => $plugin) {
			$this->out($key+1 . ". " . $plugin['name'] . " Plugin");
		}
	}

	/**
	 * Install a function from github using git
	 *
	 * @return void
	 * @author Jose Diaz-Gonzalez
	 */
	function __doInstall() {
		$validCommands = array();
		$availablePlugins = $this->__listServerPlugins();

		foreach ($availablePlugins as $key => $plugin) {
			$this->out($key+1 . ". " . Inflector::humanize($plugin['name']) . " Plugin");
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
				// Lets construct the repoURL
				$repoURL = "git://github.com/" . $availablePlugins[$enteredPlugin-1]['owner'] . "/" . $availablePlugins[$enteredPlugin-1]['name'] . ".git";
				
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
							$this->out("Adding Submodule...");
							$this->out(shell_exec("cd {$topLevelDirectory} ; git submodule add {$repoURL} {$pluginRelativePath}/{$pluginName}"));
							$this->out("Initializing Submodule...");
							$this->out(shell_exec("cd {$topLevelDirectory} ; git submodule init"));
							$this->out("Updating Submodule...");
							$this->out(shell_exec("cd {$topLevelDirectory} ; git submodule update"));
						}
					} else {
						$this->out("Adding Submodule...");
						$this->out(shell_exec("git submodule add {$repoURL} plugins/{$pluginName}"));
						$this->out("Initializing Submodule...");
						$this->out(shell_exec("git submodule init"));
						$this->out("Updating Submodule...");
						$this->out(shell_exec("git submodule update"));
					}
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
		$githubUser = 'josegonzalez';
		$xmlResponse = new Xml(
			$this->Socket->get(
				$githubServer . 'repos/show/' . $githubUser));
		$arrayResponse = Set::reverse($xmlResponse);
		return $arrayResponse['Repositories']['Repository'];
	}
}
?>