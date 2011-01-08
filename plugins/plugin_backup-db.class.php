<?php
/**
 * @file
 *   Backup for projects and independent files/directory
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

$plugin['info'] = 'Create database backup. If no database name is specified a backup of all databases will be created';
$plugin['root_only'] = FALSE;

$plugin['options']['database'] = array(
                              'short_name'  => '-d',
                              'long_name'   => '--database',
                              'action'      => 'StoreString',
                              'description' => 'Only create backup of this database',
                            );

class SldeployPluginBackupDb extends Sldeploy {

  /**
   * Progress bar
   *
   * @var object
   */
  private $bar;

  /**
   * Current project position
   *
   * @var int
   */
  private $current_pos = 0;

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    if (empty($this->conf['backup_dir'])) {
      throw new Exception('Backup directory not specified.');
    }
    elseif (!file_exists($this->conf['backup_dir'])) {
      throw new Exception('Backup directory does not exist.');
    }

    if (isset($this->paras->command->options['database']) && !empty($this->paras->command->options['database'])) {
      $this->create_db_dump($this->paras->command->options['database']);
    }
    else {
      $this->_allDbs();
    }
  }

  /**
   * Create database dump
   *
   * @param string $db_name
   */
  private function _singleDb($db_name) {
    $this->create_db_dump($db_name);
  }

  /**
   * Create database dump of all existing databases
   */
  private function _allDbs() {

    $rc = $this->system($this->conf['mysql_bin'] . " -Bse 'show databases'");

    if (!$rc['rc']) {

      $amount = count($rc['output']);

      if (!isset($this->paras->options['verbose']) || !$this->paras->options['verbose']) {
        $this->bar = new Console_ProgressBar(' %fraction% [%bar%] %percent%  ', '=', ' ', 50, $amount);
      }

      foreach ($rc['output'] AS $db_name) {
        $this->current_pos++;
        // verbose view
        if (isset($this->paras->options['verbose']) && $this->paras->options['verbose']) {
          $this->create_db_dump($db_name);
        }
        else {
          $this->bar->update($this->current_pos);
          $this->create_db_dump($db_name, NULL, FALSE);
        }
      }
    }
    else {
      $this->msg('Could not get list of available databases.');
    }
  }
}
