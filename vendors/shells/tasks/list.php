<?php
App::import(array('Folder', 'HttpSocket', 'Xml'));
class ListTask extends Shell {

/**
 * HttpSocket instance.
 * @var string
 */
	var $Socket = null;


	function execute() {
		// Todo
	}

	function installed() {
		$this->__doListInstalled();
	}

	function available($maintainer = null) {
		$maintainer = ($maintainer == null) ? 'cakephp-plugin-provider' : $maintainer;
		$this->Socket = new HttpSocket();

		$this->__doListAvailable($maintainer);
	}

/**
 * List all the plugins installed
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 */
	function __doListInstalled() {
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
	function __doListAvailable($maintainer = null) {
		$maintainer = ($maintainer == null) ? 'cakephp-plugin-provider' : $maintainer;

		$this->out("\nThis is a list of currently active plugins on the server.");
		$availablePlugins = $this->__listServerPlugins($maintainer);
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
	function __listServerPlugins($maintainer = null) {
		$githubServer = "http://github.com/api/v2/xml/";
		$maintainer = ($maintainer == null) ? 'cakephp-plugin-provider' : $maintainer;
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
}
?>