<?php
/**
 * @file
 *   Set file and directory permissions
 *
 * Permissions
 *
 * name = filename or directory name
 * rec  = recursive: yes,
 *                    files (files only)
 *                    dirs (directories only) or
 *                    no [default]
 * mod  = permissions
 * own  = owner
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

$plugin['info'] = 'set file and directory permissions';
$plugin['root_only'] = TRUE;

class SldeployPluginPermission extends Sldeploy {

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see sldeploy#run()
   */
  public function run() {

    $this->msg('Set permissions...');

    if (is_array($this->conf['permissions'])) {

      $this->progressbar_init();

      foreach ($this->conf['permissions'] AS $permission) {
        if ($permission['name'] == '/') {
          throw new Exception('Permission should never ever set tor / (recursive)!');
        }
        elseif (empty($permission['name'])) {
          $this->msg('Missing name (directory) for this entry.');
        }
        else {

          if (isset($permission['mod']) && !empty($permission['mod'])) {
            $this->_setPermissions('mod', $permission);
          }

          if (isset($permission['own']) && !empty($permission['own'])) {
            $this->_setPermissions('own', $permission);
          }
        }
      }
    }

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

    foreach ($this->conf['permissions'] AS $permission) {
      if (isset($permission['mod']) && !empty($permission['mod'])) {
        $init++;
      }
      if (isset($permission['own']) && !empty($permission['own'])) {
        $init++;
      }
    }

    return $init;
  }

  /**
    * Set permissions
    *
    * @param string $mode
    * @param array $permission (name = directory, mod = value, rec = recursive
    *
    * @return void
    */
  private function _setPermissions($mode, $permission) {

    if (!isset($permission['name']) || empty($permission['name'])) {
      throw new Exception('name value (directory) is required for permissions.');
    }
    elseif (!isset($permission['mod']) || empty($permission['mod'])) {
      throw new Exception('mod value is required for permissions.');
    }

    if ($mode == 'own') {
      $command = 'chown';
    }
    else {
      $command = 'chmod';
    }

    if (isset($permission['rec']) && $permission['rec']) {
      $recursive = TRUE;
    }
    else {
      $recursive = FALSE;
    }

    $this->show_progress('Set permissions (' . $permission['mod'] . ') to ' . $permission['name'] . '...');

    switch ($recursive) {

      case 'files':
        $this->system('find ' . $permission['name'] . ' -type f -exec ' . $command . ' ' . $permission['mod'] . ' {} \;');
        break;

      case 'dirs':
        $this->system('find ' . $permission['name'] . ' -type d -exec ' . $command . ' ' . $permission['mod'] . ' {} \;');
        break;

      case 'yes':
        $this->system($command . ' -R ' . $permission['mod'] . ' ' . $permission['name']);
        break;

      default: // not recursive
        $this->system($command . ' ' . $permission['mod'] . ' ' . $permission['name']);
    }
  }
}