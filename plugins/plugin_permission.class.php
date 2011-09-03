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

$plugin['info'] = 'set file and directory permissions. If no parameter is specfied all project permissions will be applied';
$plugin['root_only'] = TRUE;

$plugin['options']['project'] = array(
                              'short_name'  => '-p',
                              'long_name'   => '--project',
                              'action'      => 'StoreString',
                              'description' => 'Set permissions to specified project',
);

$plugin['options']['system'] = array(
                              'short_name'  => '-S',
                              'long_name'   => '--system',
                              'action'      => 'StoreTrue',
                              'description' => 'Set permission on system file/directories (non-project settings)',
);

class VcdeployPluginPermission extends Vcdeploy implements IVcdeployPlugin {

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see vcdeploy#run()
   */
  public function run() {

    // combination of parameters are forbitten
    if (isset($this->paras->command->options['project']) && isset($this->paras->command->options['system'])) {
      throw new Exception('You cannot use --project and --system together');
    }

    $this->msg('Set permissions...');

    if (isset($this->paras->command->options['system']) && !empty($this->paras->command->options['system'])) {
      if (is_array($this->conf['permissions']) && count($this->conf['permissions'])) {
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
    }
    else if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
      $project_name = $this->paras->command->options['project'];
      if (!array_key_exists($project_name, $this->projects)) {
        throw new Exception('Project "' . $project_name . '" is not configured!');
      }
      $this->progressbar_init();
      $this->set_project($project_name, $this->projects[$project_name]);
      $this->_setProjectPermissions();
    }
    else { // all projects
      $this->progressbar_init();
      foreach ($this->projects AS $project_name => $project) {
        $this->set_project($project_name, $project);
        $this->_setProjectPermissions();
      }
    }

    return 0;
  }

  /**
   * Set project permissions
   *
   * @throws Exception
   */
  private function _setProjectPermissions() {

    if (!isset($this->project['path'])) {
      throw new Exception('Project path is required for set project permissions!');
    }

    if (isset($this->project['permissions']) && is_array($this->project['permissions'])) {
      foreach ($this->project['permissions'] AS $permission) {
        if (isset($permission['mod']) && !empty($permission['mod'])) {
          $this->set_permissions('mod', $permission, $this->project['path']);
        }
        if (isset($permission['own']) && !empty($permission['own'])) {
          $this->set_permissions('own', $permission, $this->project['path']);
        }
      }
    }
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
    $rc = 0;
    // with permissions
    if (isset($this->paras->command->options['system']) && !empty($this->paras->command->options['system'])) {
      foreach ($this->conf['permissions'] AS $permission) {
        if (isset($permission['mod']) && !empty($permission['mod'])) {
          $rc++;
        }
        if (isset($permission['own']) && !empty($permission['own'])) {
          $rc++;
        }
      }
    }
    else if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
      $project_name = $this->paras->command->options['project'];
      $rc += $this->count_project_permissions($this->projects[$project_name]);
    }
    else {
      foreach ($this->projects AS $project_name => $project) {
        $rc += $this->count_project_permissions($project);
      }
    }

    return $rc + $init;
  }
}