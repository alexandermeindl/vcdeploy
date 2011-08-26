<?php
/**
 * @file
 *   Backup rsync
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

$plugin['info'] = 'Rsync backup files with remote system';
$plugin['root_only'] = FALSE;

class VcdeployPluginBackupRsync extends Vcdeploy implements IVcdeployPlugin {

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see vcdeploy#run()
   */
  public function run() {

    if (!isset($this->conf['backup_remote_host']) || empty($this->conf['backup_remote_host'])) {
      throw new Exception('backup_remote_host is not specified!');
    }
    elseif (!isset($this->conf['backup_remote_dir']) || empty($this->conf['backup_remote_dir'])) {
      throw new Exception('backup_remote_dir is not specified!');
    }

    $this->msg('Sync data between ' . $this->hostname . ' and ' . $this->conf['backup_remote_host'] . '...');

    $remote_dir = $this->conf['backup_remote_host'] . ':' . $this->conf['backup_remote_dir'] . '/';
    $command = $this->conf['rsync_bin'] . ' -e ssh -avzp --exclude "*.journal" --exclude ".nfs*" --exclude "*.tar" --delete';

    $rc = $this->system($command . ' ' . $this->conf['backup_dir'] . '/ ' . $remote_dir);

    return $rc['rc'];
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
    return $init++;
  }
}
