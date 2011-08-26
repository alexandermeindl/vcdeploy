<?php
/**
 * @file
 *   Plugin to reset a directory
 *
 * This is useful, if you want to fetch a copy an extern installatio
 * to your local developer environment.
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

$plugin['info'] = 'Reset directory. If no project is specified, all active projects files/directories will be reseted.';
$plugin['root_only'] = TRUE;

$plugin['options']['project'] = array(
                              'short_name'  => '-p',
                              'long_name'   => '--project',
                              'action'      => 'StoreString',
                              'description' => 'Only reset data of this project',
                            );

$plugin['options']['with_backup'] = array(
                                          'short_name'  => '-b',
                                          'long_name'   => '--with_backup',
                                          'action'      => 'StoreTrue',
                                          'description' => 'Create a backup before the sync (default with setting without_backup=FALSE)',
);

$plugin['options']['without_backup'] = array(
                                          'short_name'  => '-B',
                                          'long_name'   => '--without_backup',
                                          'action'      => 'StoreTrue',
                                          'description' => 'Do not create a backup before the sync (default with setting without_backup=TRUE)',
                                        );

class VcdeployPluginResetDir extends Vcdeploy implements IVcdeployPlugin {

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see vcdeploy#run()
   */
  public function run() {

    // check for existing projects
    $this->validate_projects();

    // check backup directory if exists and is writable
    if ($this->is_backup_required()) {
      $this->prepare_backup_dir();
    }

    if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
      $project_name = $this->paras->command->options['project'];
      if (!array_key_exists($project_name, $this->projects)) {
        throw new Exception('Project "' . $project_name . '" is not configured!');
      }
      $this->set_project($project_name, $this->projects[$project_name]);
      $this->msg('Project: ' . $this->project_name);
      $this->_resetDir();
    }
    else {
      foreach ($this->projects AS $project_name => $project) {
        $this->set_project($project_name, $project);

        $this->msg('Project: ' . $this->project_name);
        $this->_resetDir();
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
    return $init++;
  }

  /**
   * Reset directory with content to specified source
   *
   * @return bool
   */
  private function _resetDir() {

    if (isset($this->project['data_dir'])) {

      foreach ($this->project['data_dir'] AS $identifier => $dir) {

        $tar_file = $this->get_source_data_file($identifier, $this->get_source_data($identifier));

        if (!empty($tar_file)) {

          // create backup of existing data
          if ($this->is_backup_required()) {
            $this->create_project_data_backup();
          }
          else {
            $this->msg('Backup deactivated.');
          }

          // remove existing target directory
          if (file_exists($dir)) {
            $this->remove_directory($dir);
          }

          // Restore Tar file
          chdir(dirname($dir)); // go to parent directory
          $this->system($this->conf['tar_bin'] . ' -xz --no-same-owner -f ' . $tar_file);

          if (isset($this->project['reset_dir']['post_commands'])) {
            $this->hook_commands($this->project['reset_dir']['post_commands'], 'post');
          }

          $this->msg('Directory ' . $identifier . ' has been successfully restored.');
        }
        else {
          $this->msg('TAR file for reset could not be identify');
        }
      }
    }
    else {
      $this->msg('Project ' . $this->project_name . ': no data_dir has been specified.');
    }
  }
}