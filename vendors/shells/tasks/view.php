<?php
App::import(array('HttpSocket', 'Xml'));
class ViewTask extends Shell {

/**
 * HttpSocket instance.
 * @var string
 */
	var $Socket = null;


	function execute() {
		$this->Socket = new HttpSocket();

		$this->__doView();
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
}
?>