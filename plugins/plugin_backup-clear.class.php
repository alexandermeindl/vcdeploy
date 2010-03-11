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

$plugin['info']       = 'remove old backups';
$plugin['root_only']  = FALSE;

class sldeploy_plugin_backup_clear extends sldeploy {

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

    $this->clear_backups();
  }

  private function clear_backups() {

    $this->msg('Remove old backups on '. $this->hostname .'...');

    $max_minutes = $this->conf['backup_max_days'] * 24 * 60;

    $this->system('find "'. $this->conf['backup_dir'] .'/" ! -mmin -'. $max_minutes .' -name "*.tar.gz*" -type f -exec rm {} \;', TRUE);
    $this->system('find "'. $this->conf['backup_dir'] .'/" ! -mmin -'. $max_minutes .' -name "*sql.gz*" -type f -exec rm {} \;', TRUE);

    # delete files, which no longer in use
    $this->system('find "'. $this->conf['backup_dir'] .'/" -name "*.tar" -type f -exec rm {} \;', TRUE);
  }
}
