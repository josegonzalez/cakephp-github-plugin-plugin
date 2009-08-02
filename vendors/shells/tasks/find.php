<?php
App::import(array('HttpSocket', 'Xml'));
class FindTask extends Shell {

/**
 * HttpSocket instance.
 * @var string
 */
	var $Socket = null;

	function execute() {
		$this->Socket = new HttpSocket();

		$this->__doFind();
	}

/**
 * Find a specific plugin
 *
 * @return void
 * @author Jose Diaz-Gonzalez
 **/
	function __doFind() {
		$query = $this->in(__("Enter a search term or 'q' or nothing to exit", true), null, 'q');
		$this->out("Grabbing all plugins...");
		$availablePlugins = $this->__listServerPlugins();

		$this->out("Searching all plugins for query...");
		$results = array();
		foreach ($availablePlugins as $plugin) {
			if (((isset($plugin['name'])) and (stristr($plugin['name'], $query) !== false)) or ((isset($plugin['description'])) and (stristr($plugin['description'], $query) !== false))) {
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