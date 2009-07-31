<?php
App::import(array('Folder'));
class UpdateSpecificTask extends Shell {

	function execute() {
		$this->__doUpdateSpecific();
	}

/**
 * Pull the update for a specific plugin submodule
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 **/
	function __doUpdateSpecific() {
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
}
?>