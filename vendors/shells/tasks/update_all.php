<?php
App::import(array('Folder'));
class UpdateAllTask extends Shell {

	function execute() {
		$this->__doUpdateAll();
	}

/**
 * Pull the updates for all plugin submodules
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 **/
	function __doUpdateAll() {
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