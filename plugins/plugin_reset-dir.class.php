<?php
/**
 * @file
 *   Plugin to reset a directory
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

$plugin['info']       = 'reset directory';
$plugin['root_only']  = TRUE;

class sldeploy_plugin_reset_dir extends sldeploy {

  /**
   * Entry name of current directory
   *
   * @var string
   */
  private $dir_name;

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    if (count($this->projects)) {
      foreach($this->projects AS $project_name => $project) {
        $this->set_project($project_name, $project);
        $this->dir_name = 'reset_dir-'. $this->project_name;

        $this->msg('Project: '. $this->project_name);
        $this->reset_dir();
      }
    }
    else {
      $this->msg('No project configuration found', 1);
    }
  }

  /**
   * Reset directory with content to specified source
   *
   * @return bool
   */
  public function reset_dir() {

    if (!empty($this->project['target_dir'])) {

      $tar_file = $this->get_tar_file();

      if (!empty($tar_file)) {

        // create database of existing database
        $this->dir_backup();

        // remove existing target directory
        if ($this->project['target_dir']!='/') {
          $this->system('rm -r '. $this->project['target_dir']);
        }
        else {
          $this->msg('Never use / as target directory!', 2);
        }

        // Restore Tar file
        chdir(dirname($this->project['target_dir'])); // go to parent directory
        $this->system($this->conf['tar_bin'] .' xfz '. $tar_file);

        if (isset($this->project['post_commands'])) {
          $this->post_commands($this->project['post_commands']);
        }

        $this->msg('Directory '. $this->project['target_dir'] .' has been successfully restored.');
      }
      else {
        $this->msg('TAR file for reset could not be identify');
      }
    }
    else {
      $this->msg('Project '. $this->project_name .': no target_dir has been specified.');
    }
  }

  private function get_tar_file() {

    switch ($this->project['transfer_mode']) {

      case 'local':
        $tar_file = $this->project['source_dir_file'] .'.gz';
        break;

      case 'remote':

        $remote_file = $this->project['remote_tmp_dir'] .'/'. $this->dir_name .'.tar.gz';

        $this->msg('Create TAR file on remote server ('. $this->ssh_server .')...');

        $dir = basename($this->project['source_remote_dir']);

        $rc = $this->ssh_system('cd '. dirname($this->project['source_remote_dir']) .' && '. $this->conf['tar_bin'] .' cfz '. $remote_file .' '. $dir);
        if ($rc['rc']) {
          $this->msg('Error creating tar file.', 2);
        }

        $tar_file = $this->conf['tmp_dir'] .'/'. $this->dir_name .'.tar.gz';

        $this->ssh_get_file($this->project['remote_tmp_dir'] .'/'. $this->dir_name .'.tar.gz',
                            $tar_file);
        break;

      default:
        $tar_file = '';
    }

    return $tar_file;
  }

  private function dir_backup() {

    $this->msg('creating backup of target directory '. $this->project['target_dir']);

    $target_file = $this->conf['backup_dir'] .'/'. $this->dir_name . '-'. $this->date_stamp .'.tar.gz';
    $this->system($this->conf['nice_bin'] .' -n 15 '. $this->conf['tar_bin'] .' cfz '. $target_file .' '. $this->project['target_dir'], TRUE);
  }

}