<?php
/**
 * @file
 *   Plugin to reset database
 *
 * This is useful, if you want to fetch a copy an extern
 * installation to your local developer environment.
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

$plugin['info'] = 'Reset database. If no project is specified, all active project databases will be reseted';
$plugin['root_only'] = TRUE;

$plugin['options']['project'] = array(
                              'short_name'  => '-p',
                              'long_name'   => '--project',
                              'action'      => 'StoreString',
                              'description' => 'Only reset database of this project',
                            );

class SldeployPluginResetDb extends Sldeploy {

  /**
   * This function is run with the command
   *
   * @return int
   * @see sldeploy#run()
   */
  public function run() {

    // check for existing projects
    $this->validate_projects();

    // check backup directory if exists and is writable
    $this->prepare_backup_dir();

    if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
      $project_name = $this->paras->command->options['project'];
      if (!array_key_exists($project_name, $this->projects)) {
        throw new Exception('Project "' . $project_name . '" is not configured!');
      }
      $this->set_project($project_name, $this->projects[$project_name]);
      $this->msg('Project: ' . $this->project_name);
      $this->_resetDb();
    }
    else {
      foreach ($this->projects AS $project_name => $project) {
        $this->set_project($project_name, $project);

        $this->msg('Project: ' . $this->project_name);
        $this->_resetDb();
      }
    }

    return 0;
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
    return $init++;
  }

  /**
   * Reset database
   *
   * @return bool
   */
  private function _resetDb() {

    if (isset($this->project['db'])) {

      foreach ($this->project['db'] AS $identifier => $db) {

        $sql_file = $this->get_remote_db_file($identifier, $this->get_source_db($identifier));

        if (!empty($sql_file)) {

          // create backup of existing database
          $this->create_db_dump($db);

          // recreate database
          $this->recreate_db($db);

          $this->msg('Import data...');
          $this->system($this->conf['gunzip_bin'] . ' < ' . $sql_file . ' | ' . $this->conf['mysql_bin'] . ' ' . $db);

          if (isset($this->project['reset_db']['with_db_sanitize']) && $this->project['reset_db']['with_db_sanitize']) {
            $this->sanitize_database_sanitize($db);
          }

          $this->msg('Database ' . $db . ' has been successfully reseted.');
        }
        else {
          $this->msg('SQL file for import could not be identify');
        }
      }

      // run post commands
      if (isset($this->project['reset_db']['post_commands'])) {
        $this->hook_commands($this->project['reset_db']['post_commands'], 'post');
      }
    }
    else {
      $this->msg('Project ' . $this->project_name . ': no database has been specified.');
    }
  }
}