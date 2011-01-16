<?php
/**
 * @file
 *   configuration information of sldeploy
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
 * TODO: - branch support for git
 *     - bzr support
 *
 * @package  sldeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/sldeploy
 */

$plugin['info'] = 'status messages and information of sldeploy environment';
$plugin['root_only'] = FALSE;

class SldeployPluginStatus extends Sldeploy implements ISldeployPlugin {

  /**
   * This function is run with the command
   *
   * @return int
   * @see sldeploy#run()
   */
  public function run() {

    $this->msg("Version:\t\t" . $this->version);
    $this->msg("SCM:\t\t\t" . $this->conf['source_scm']);
    $this->msg("System OS:\t\t" . $this->conf['system_os']);
    $this->msg("Config file:\t\t" . $this->conf['config_file']);
    $this->msg("System source:\t\t" . $this->conf['system_source']);
    $this->msg("Backup dir:\t\t" . $this->conf['backup_dir'] . $this->_checkDir($this->conf['backup_dir']));
    $this->msg("Project (active):\t" . count($this->get_projects()));
    $this->msg("Project (all):\t\t" . count($this->get_all_projects()));

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
   * Check directory
   * - if it exist
   * - if it is a directory
   * - if sldeloy has write permission
   *
   * @param string $dir
   * @return string if a problem exist, message is returned
   */
  private function _checkDir($dir) {

    $msg = '';

    if (!file_exists($dir)) {
      $msg = 'does not exist';
    }
    elseif (!is_dir($dir)) {
      $msg = 'is not a directory';
    }
    elseif (!is_writable($dir)) {
      $msg = 'is not writable';
    }

    if (empty($msg)) {
      return $msg;
    }
    else {
      return ' (' . $msg . ')';
    }
  }
}