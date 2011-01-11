<?php
/**
 * @file
 *   Run drupal update
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
 * @package  sldeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/sldeploy
 */

$plugin['info'] = 'run drupal updates on all drupal projects';
$plugin['root_only'] = FALSE;

class SldeployPluginUpdateDrupal extends Sldeploy {

  /**
   * This function is run with the command
   *
   * @return int
   * @see sldeploy#run()
   */
  public function run() {

    $rc = 0;

    // check for existing projects
    $this->validate_projects();

    foreach ($this->projects AS $project_name => $project) {
      $this->set_project($project_name, $project);

      if (isset($this->project['drush'])) {
        $this->msg('Drupal updatedb on ' . $this->project_name);
        $r = $this->_drushExec($this->project['drush']);
        if ($r) {
          $rc += $r;
        }
      }
    }

    return $rc;
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
    return $init + 3;
  }

  /**
   * Run drush updatedb
   *
   * @param   string  $script
   *
   * @return void
   */
  private function _drushExec($script) {

    $this->system($script . ' --yes updatedb', TRUE);
    $this->system($script . ' cache-clear all', TRUE);

    $rc = $this->system($script . ' cron', TRUE);

    return $rc['rc'];
  }
}
