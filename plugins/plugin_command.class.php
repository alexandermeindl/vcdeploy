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
 * @package  sldeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/sldeploy
 */

$plugin['info'] = 'run commands';
$plugin['root_only'] = FALSE;

$plugin['args']['command'] = 'command to run. Use \'command-ls\' to get a list with all available commands';

class SldeployPluginCommand extends Sldeploy {

  /**
   * Current active command
   *
   * @var string
   */
  private $active_command;

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see sldeploy#run()
   */
  public function run() {

    if (isset($this->paras->command->args['command']) && !empty($this->paras->command->args['command'])) {
      $this->active_command = $this->paras->command->args['command'];
    }
    else {
      throw new Exception('No command specified. Use \'command-ls\' to list available commands');
    }

    if (array_key_exists($this->active_command, $this->conf['commands'])) {
      return $this->run_command();
    }
    else {
      throw new Exception('Unknown command has been used. Use \'command-ls\' to list available commands');
    }
  }

  /**
   * Get max steps of this plugin for progress view
   *
   * @param int $init initial value of counter
   *
   * @return int amount of working steps of this plugin
   * @see Sldeploy#progressbar_init()
   */
  public function get_steps($init = 0) {

    $command = $this->conf['commands'][$this->active_command];
    if (is_array($command)) {

    }
    else {
      $init++;
    }
    return $init;
  }

  /**
    * run a system command or a group of commands
    *
    * @return int
    */
  public function run_command() {

    $r = 0;

    $command = $this->conf['commands'][$this->active_command];
    if (is_array($command)) {
      $runs = 0;

      foreach ($command AS $command_atom) {
        if ($command_atom != $this->active_command && array_key_exists($command_atom, $this->conf['commands'])) {
          if ($runs) {
            $this->msg('---');
          }
          $this->show_progress('Run ' . $command_atom . '...');
          $rc = $this->system($this->conf['commands'][$command_atom], TRUE);
          if ($rc['rc']) {
            $r++;
          }
          $runs++;
        }
      }
      $this->msg($runs . ' commands have been executed');
    }
    else {
      $rc = $this->system($command, TRUE);
      $r = $rc['rc'];
    }

    return $r;
  }
}
