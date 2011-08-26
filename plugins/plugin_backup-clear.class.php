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
 * @package  vcdeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/vcdeploy
 */

$plugin['info'] = 'remove old backups';
$plugin['root_only'] = FALSE;

class VcdeployPluginBackupClear extends Vcdeploy implements IVcdeployPlugin {

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see vcdeploy#run()
   */
  public function run() {

    if (empty($this->conf['backup_dir'])) {
      throw new Exception('Backup directory not specified.');
    }
    elseif (!file_exists($this->conf['backup_dir'])) {
      throw new Exception('Backup directory does not exist.');
    }

    $this->progressbar_init();
    return $this->_clearBackups();
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
    return $init + 3;
  }

  /**
   * Clear exisiting backups
   *
   * @return int
   */
  private function _clearBackups() {

    $this->msg('Remove old backups on ' . $this->hostname . ':');

    $max_minutes = $this->conf['backup_max_days'] * 24 * 60;

    $this->show_progress('Removing old tar.gz files...');
    $this->system('find "' . $this->conf['backup_dir'] . '/" ! -mmin -' . $max_minutes . ' -name "*.tar.gz*" -type f -exec rm {} \;', TRUE);
    $this->show_progress('Removing old sql.gz files...');
    $this->system('find "' . $this->conf['backup_dir'] . '/" ! -mmin -' . $max_minutes . ' -name "*sql.gz*" -type f -exec rm {} \;', TRUE);

    // delete files, which no longer in use
    $this->show_progress('Removing tar files...');
    $rc = $this->system('find "' . $this->conf['backup_dir'] . '/" -name "*.tar" -type f -exec rm {} \;', TRUE);

    return $rc['rc'];
  }
}
