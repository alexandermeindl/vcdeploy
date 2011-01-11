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
 * @package  sldeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/sldeploy
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
   * @return int
   * @throws Exception
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

    return 0;
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
   * Get max steps of this plugin for progress view
   *
   * @param int $init initial value of counter
   *
   * @return int amount of working steps of this plugin
   * @see Sldeploy#progressbar_init()
   */
  public function get_steps($init = 0) {

    $rc = $this->system($this->conf['mysql_bin'] . " -Bse 'show databases'");

    // 2 times, because of gzip compression
    return $init + count($rc['output']) * 2;
  }

  /**
   * Create database dump of all existing databases
   *
   * @return void
   */
  private function _allDbs() {

    $rc = $this->system($this->conf['mysql_bin'] . " -Bse 'show databases'");

    if (!$rc['rc']) {

      $this->progressbar_init();

      foreach ($rc['output'] AS $db_name) {
          $this->create_db_dump($db_name);
      }
    }
    else {
      $this->msg('Could not get list of available databases.');
    }
  }
}
