<?php
/**
 * Github Plugin management shell.
 *
 * @package default
 */
class GithubPluginShell extends Shell {

/**
 * Tasks that are attached to this shell
 *
 * @var string
 **/
	var $tasks = array('List', 'View', 'Search', 'GitInstall', 'ZipInstall', 'UpdateAll', 'UpdateSpecific');
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
				$this->List->execute();
				break;
			case 'v' :
				$this->View->execute();
				break;
			case 's' :
				$this->Search->execute();
				break;
			case 'g' :
				$this->GitInstall->execute();
				break;
			case 'z' :
				$this->ZipInstall->execute();
				break;
			case 'p' :
				$this->UpdateAll->execute();
				break;
			case 'u' :
				$this->UpdateSpecific->execute();
				break;
			case 'q' :
				$this->out(__("Exit", true));
				$this->_stop();
				break;
		}
	}
}
?>