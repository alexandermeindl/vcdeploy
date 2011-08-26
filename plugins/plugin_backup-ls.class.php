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

$plugin['info'] = 'List all available backups';
$plugin['root_only'] = FALSE;

class VcdeployPluginBackupLs extends Vcdeploy implements IVcdeployPlugin {

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

    return $this->_listBackups();
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

  /**
   * List all existing backups
   *
   * @return int
   */
  private function _listBackups() {

    $lines = array();

    $d = dir($this->conf['backup_dir']);

    while (FALSE !== ($entry = $d->read())) {
      if ($entry != '.' && $entry != '..') {
        $line = $this->_getBackupLine($entry);
        if (!empty($line)) {
          $lines[] = $line;
        }
      }
    }
    $d->close();

    rsort($lines);
    foreach ($lines AS $line) {
      $this->msg($line);
    }

    return 0;
  }

  /**
   * Get one line of a backup file
   *
   * @param string $entry
   *
   * @return string
   */
  private function _getBackupLine($entry) {

    if (substr($entry, -7) == '.sql.gz') {
      $suffix = $this->conf['backup_dir'] . '/' . $entry . ' (db)';
      $name = substr($entry, 3, strlen($entry) - 23);
    }
    elseif (substr($entry, -7) == '.tar.gz') {
      $suffix = $this->conf['backup_dir'] . '/' . $entry . ' (files)';
      $name = substr($entry, 0, -20);
    }
    else {
      return FALSE;
    }

    $date = substr($entry, -19, 8);
    $time = substr($entry, -11, 4);

    $year = substr($date, 0, 4);
    $month = substr($date, 4, 2);
    $day = substr($date, 6, 2);
    $hours = substr($time, 0, 2);
    $minutes = substr($time, 2);

    return $year . '-' . $month . '-' . $day . ' ' . $hours . ':' . $minutes . ' - ' . $name . ' - ' . $suffix;
  }
}
