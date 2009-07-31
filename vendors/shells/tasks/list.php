<?php
App::import(array('Folder'));
class ListTask extends Shell {

	function execute() {
		$this->__doList();
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