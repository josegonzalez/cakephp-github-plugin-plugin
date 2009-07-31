<?php
App::import(array('HttpSocket', 'Xml'));
class GitInstallTask extends Shell {

/**
 * HttpSocket instance.
 * @var string
 */
	var $Socket = null;


	function execute() {
		$this->Socket = new HttpSocket();

		$this->__doGitInstall();
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
}
?>