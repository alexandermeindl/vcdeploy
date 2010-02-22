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
 */

$plugin['info']       = 'run drupal updates on all drupal projects';
$plugin['root_only']  = FALSE;

class sldeploy_plugin_update_drupal extends sldeploy {

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    if (count($this->projects)) {
      foreach($this->projects AS $project_name => $project) {
        $this->set_project($project_name, $project);

        if (isset($this->project['drush']))
          $this->msg('Drupal updatedb on '. $this->project_name);
          $this->drush_exec($this->project['drush']);
      }
    }
    else {
      $this->msg('No project configuration found', 1);
    }
  }

  /**
   * Run drush updatedb
   *
   * @param   string  $script
   */
  private function drush_exec($script) {

    $this->system($script . ' --yes updatedb', TRUE);
    $this->system($script . ' cache-clear all', TRUE);
    $this->system($script . ' cron', TRUE);
  }

}
