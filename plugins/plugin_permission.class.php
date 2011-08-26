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
 * @package  vcdeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/vcdeploy
 */

$plugin['info'] = 'set file and directory permissions';
$plugin['root_only'] = TRUE;

class VcdeployPluginPermission extends Vcdeploy implements IVcdeployPlugin {

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see vcdeploy#run()
   */
  public function run() {

    $this->msg('Set permissions...');

    if (is_array($this->conf['permissions'])) {

      $this->progressbar_init();

      foreach ($this->conf['permissions'] AS $permission) {
        if (isset($permission['mod']) && !empty($permission['mod'])) {
          $this->set_permissions('mod', $permission);
        }

        if (isset($permission['own']) && !empty($permission['own'])) {
          $this->set_permissions('own', $permission);
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
   * @see Vcdeploy#progressbar_init()
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
}