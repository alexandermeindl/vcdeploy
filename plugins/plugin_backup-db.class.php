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

$plugin['info']       = 'Create backup of all databases';
$plugin['root_only']  = FALSE;

class sldeploy_plugin_backup_db extends sldeploy {

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    if (empty($this->conf['backup_dir'])) {
      $this->msg('Backup directory not specified', 1);
    }
    else if (!file_exists($this->conf['backup_dir'])) {
      $this->msg('Backup directory does not exist', 1);
    }

    $this->all_dbs();
  }

  private function create_db_backup($db_name) {

    $target_file = $this->conf['backup_dir'] .'/db-'. $db_name .'-'. $this->date_stamp .'.sql';

    $this->msg('Creating database dump of '. $db_name .'...');

    $this->set_nice('high');
    $this->system($this->conf['mysqldump_bin'] .' '. $this->conf['mysqldump_options'] .' '. $db_name .'>'. $target_file);
    $this->gzip_file($target_file);
  }

  private function all_dbs() {

    $rc = $this->system($this->conf['mysql_bin'] ." -Bse 'show databases'");

    if (!$rc['rc']) {
      foreach($rc['output'] AS $db_name) {
        $this->create_db_backup($db_name);
      }
    }
    else {
      $this->msg('Could not get list of available databases.');
    }
  }
}
