<?php
/**
 * @file
 *   Set file and directory permissions
 *
 * Permissions
 *
 * name = filename or directory name
 * rec  = recursive: yes, files (files only) dirs (directories only) or no [default]
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
 */

$plugin['info']       = 'set file and directory permissions';
$plugin['root_only']  = TRUE;

class sldeploy_plugin_permission extends sldeploy {

  public function run() {

    $this->msg('Set permissions...');

    if (is_array($this->conf['permissions'])) {

      foreach ($this->conf['permissions'] AS $permission) {

        if ($permission['name'] == '/') {
          $this->msg('Permission should never ever set tor / (recursive)!', 2);
        }
        elseif (empty($permission['name'])) {
          $this->msg('Missing name (directory) for this entry.');
        }
        else {

          if (!empty($permission['mod'])) {
            $this->set_permissions('mod',
                                    $permission['name'],
                                    $permission['mod'],
                                    $permission['rec']);
          }

          if (!empty($permission['own'])) {
            $this->set_permissions('own',
                                    $permission['name'],
                                    $permission['own'],
                                    $permission['rec']);
          }
        }
      }
    }
  }

  /**
    *
    * Set permissions
    *
    * @params string $mode
    * @params string $directory
    * @params string $value
    * @params string $recursive
    *
    */
  private function set_permissions($mode, $directory, $value, $recursive=NULL) {

    if ($mode=='own') $command = 'chown';
    else              $command = 'chmod';

    if (!empty($value)) {

      $this->msg('Set permissions ('. $value .') to '. $directory .'...');

      switch ($recursive) {

        case 'files':
          $this->system('find '. $directory .' -type f -exec '. $command .' '. $value .' {} \;');
          break;

        case 'dirs':
          $this->system('find '. $directory .' -type d -exec '. $command .' '. $value .' {} \;');
          break;

        case 'yes':
          $this->system($command .' -R '. $value .' '. $directory);
          break;

        default: // not recursive
          $this->system($command .' '. $value .' '. $directory);
      }
    }
  }

}