<?php
/**
 * @file
 *   Plugin to reset database
 *
 * This is useful, if you want to fetch a copy an extern installation to your local developer environment.
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

$plugin['info']       = 'reset database';
$plugin['root_only']  = TRUE;

class sldeploy_plugin_reset_db extends sldeploy {

  const db_para = '-c --skip-opt --disable-keys --set-charset --add-locks --lock-tables --create-options --add-drop-table';

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    if (count($this->projects)) {
      foreach($this->projects AS $project_name => $project) {
        $this->set_project($project_name, $project);

        $this->msg('Project: '. $this->project_name);
        $this->reset_db();
      }
    }
    else {
      $this->msg('No project configuration found', 1);
    }
  }

  /**
   * Reset database
   *
   * @return bool
   */
  private function reset_db() {

    if (!empty($this->project['db'])) {

      $sql_file = $this->get_sql_file();

      if (!empty($sql_file)) {

        // create database of existing database
        $this->db_backup();

        // drop database
        $this->system($this->conf['mysqladmin_bin'] .' -f drop '. $this->project['db']);

        $this->msg('Recreating database '. $this->project['db'] .'...');
        sleep(2);

        system($this->conf['mysqladmin_bin'] .' create '. $this->project['db']);

        $this->msg('Import data...');
        $this->system($this->conf['gunzip_bin'] .' < '. $sql_file .' | '. $this->conf['mysql_bin'] .' '. $this->project['db']);

        $this->post_commands($this->project['post_commands']);

        $this->msg('Database '. $this->project['db'] .' has been successfully reseted.');
      }
      else {
        $this->msg('SQL file for import could not be identify');
    }
  }
  else {
      $this->msg('Project '. $this->project_name .': no database has been specified.');
    }
  }

  private function get_sql_file() {

    switch ($this->project['transfer_mode']) {

      case 'local':
        $sql_file = $this->project['sql_backup'] .'.gz';
        break;

      case 'remote':

        $remote_file = $this->project['remote_tmp_dir'] .'/'. $this->project['db'] .'.sql';

        $this->msg('Create Dump on remote server...');
        $rc = $this->ssh_system($this->conf['mysqldump_bin'] .' '. $this->project['db'] .' > '. $remote_file, TRUE);
        if ($rc['rc']) {
          $this->msg('Error creating remote dump.', 1);
        }

        $this->msg('Compress remote file...');
        $rc = $this->ssh_system($this->conf['gzip_bin'] .' -f '. $remote_file, TRUE);
        if ($rc['rc']) {
          $this->msg('Error compress remote file.', 1);
        }

        $sql_file = $this->conf['tmp_dir'] .'/'. $this->project['db'] .'.sql.gz';

        $this->ssh_get_file($this->project['remote_tmp_dir'] .'/'. $this->project['db'] .'.sql.gz',
                            $sql_file);
        break;

      default:
        $sql_file = '';
    }

    return $sql_file;
  }

  private function db_backup() {

    $this->msg('creating database dump of '. $this->project['db']);
    $target_file = $this->conf['backup_dir'] .'/'. $this->project['db'] . '-'. $this->date_stamp .'.sql';
    $this->system($this->conf['nice_bin'] .' -10 '. $this->conf['mysqldump_bin'] .' '. self::db_para .' '. $this->project['db'] .' > '. $target_file);

    $this->gzip_file($target_file);
  }

  private function gzip_file($file) {
    $this->msg('compressing '. $file);
    $this->system($this->conf['nice_bin'] .' -n 15 '. $this->conf['gzip_bin'] .' '. $file);
  }

}