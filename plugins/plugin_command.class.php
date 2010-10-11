<?php
/**
 * @file
 *   commands
 *
 *
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 *
 */

$plugin['info']       = 'run commands';
$plugin['root_only']  = TRUE;

class sldeploy_plugin_command extends sldeploy {

	private $active_command;

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

		global $argv;

		if (isset($argv[3]) && !empty($argv[3])) {
			$this->active_command = $argv[3];
		}
		else {
			$this->msg('No command specified. Use one of the following commands: '. implode(', ', $this->get_available_commands()), 1);
		}

		if (array_key_exists($this->active_command, $this->conf['commands'])) {
			$this->run_command();
		}
		else {
			$this->msg('Unknown command has been used. Use one of the following commands: '. implode(', ', $this->get_available_commands()));
		}
  }

	/**
	  * Get all available commands
	  *
	  * @return array
	  */
	function get_available_commands() {
		$commands = array();
    foreach($this->conf['commands'] AS $command_name => $command) {
			$commands[] = $command_name;
		}
		return $commands;
	}

	/**
	  * run a system command or a group of commands
	  *
	  */
	function run_command() {

		$command = $this->conf['commands'][$this->active_command];
		if (is_array($command)) {
			$runs=0;
			foreach($command AS $command_atom) {
				if ($command_atom!=$this->active_command && array_key_exists($command_atom, $this->conf['commands'])) {
					if ($runs) {
						$this->msg('---');
					}
					$this->msg('Run '. $command_atom .'...');
					$this->system($this->conf['commands'][$command_atom], TRUE);
					$runs++;
				}
			}
			$this->msg($runs .' commands have been executed');
		}
		else {
			$this->system($command, TRUE);
		}
	}
}
