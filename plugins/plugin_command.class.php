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

$plugin['info'] = 'run commands';
$plugin['root_only'] = TRUE;

$plugin['options']['list'] = array(
                              'short_name'  => '-l',
                              'long_name'   => '--list',
                              'action'      => 'StoreTrue',
                              'description' => 'List all available commands',
                            );

$plugin['args']['command'] = 'command to run. If no command is specified, you get a list of all available commands';

class SldeployPluginCommand extends Sldeploy {

  private $active_command;

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    // show list
    if (isset($this->paras->command->options['list'])) {
      $this->msg('Available commands:');
      foreach ($this->get_available_commands() AS $command) {
        $this->msg('* ' . $command);
      }
    }
    else {

      if (isset($this->paras->command->args['command']) && !empty($this->paras->command->args['command'])) {
        $this->active_command = $this->paras->command->args['command'];
      }
      else {
        throw new Exception('No command specified. Use option -l to list available commands');
      }

      if (array_key_exists($this->active_command, $this->conf['commands'])) {
        $this->run_command();
      }
      else {
        throw new Exception('Unknown command has been used. Use option -l to list available commands');
      }
    }
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

  /**
    * run a system command or a group of commands
    *
    */
  public function run_command() {

    $command = $this->conf['commands'][$this->active_command];
    if (is_array($command)) {
      $runs = 0;
      foreach ($command AS $command_atom) {
        if ($command_atom != $this->active_command && array_key_exists($command_atom, $this->conf['commands'])) {
          if ($runs) {
            $this->msg('---');
          }
          $this->msg('Run ' . $command_atom . '...');
          $this->system($this->conf['commands'][$command_atom], TRUE);
          $runs++;
        }
      }
      $this->msg($runs . ' commands have been executed');
    }
    else {
      $this->system($command, TRUE);
    }
  }
}
