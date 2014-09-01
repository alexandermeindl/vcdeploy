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
 * @package  vcdeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/vcdeploy
 */

$plugin['info'] = 'List all available commands';
$plugin['root_only'] = FALSE;

class VcdeployPluginCommandLs extends Vcdeploy implements IVcdeployPlugin {

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see vcdeploy#run()
   */
  public function run() {

    $commands = $this->get_available_commands();

    if (count($commands)) {
      // show list
      $this->msg('Available commands:');
      foreach ($this->get_available_commands() AS $command) {
        $this->msg('* ' . $command);
      }
    }
    else {
      $this->msg('No commands defined in vcdeploy configuration file.');
    }

    return 0;
  }

  /**
   * Get max steps of this plugin for progress view
   *
   * @param int $init initial value of counter
   *
   * @return int amount of working steps of this plugin
   * @see Vcdeploy#progressbar_init()
   */
  public function get_steps($init = 0) {
    return ++$init;
  }

  /**
    * Get all available commands
    *
    * @return array
    */
  public function get_available_commands() {
    $commands = array();
    foreach ($this->conf['commands'] AS $command_name => $command) {
      $commands[] = $command_name;
    }
    return $commands;
  }
}
