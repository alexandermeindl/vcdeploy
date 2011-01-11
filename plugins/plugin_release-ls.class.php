<?php
/**
 * @file
 *   Plugin to rollout a project release
 *
 * Workflow:
 *
 *  - rollout project files
 *  - rollout database
 *  - rollout directories
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

$plugin['info'] = 'List all available releases';
$plugin['root_only'] = FALSE;

$plugin['args']['project'] = 'Project to list releases';

class SldeployPluginReleaseLs extends Sldeploy {

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see sldeploy#run()
   */
  public function run() {

    // check for existing projects
    $this->validate_projects();

    $project_name = $this->paras->command->args['project'];

    if (!array_key_exists($project_name, $this->projects)) {
      throw new Exception('Project "' . $project_name . '" is not configured!');
    }
    $this->set_project($project_name, $this->projects[$project_name]);

    if (!isset($this->project['release']['release_dir'])) {
      throw new Exception('\'release_dir\' is not specified.');
    }

    return $this->_listReleases();
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
   * List all existing Releases to a project
   *
   * @return int
   */
  private function _listReleases() {

    $files = $this->_findReleases();

    rsort($files);
    foreach ($files AS $line) {
      $this->msg('- ' . $line);
    }

    return 0;
  }

  /**
   * Get existing tags of project releases
   *
   * @array tags
   */
  private function _findReleases() {

    $lines = array();

    $d = dir($this->project['release']['release_dir']);
    $prefix_length = strlen($this->project['release']['prefix']);

    while (FALSE !== ($entry = $d->read())) {
      if ($entry != '.' && $entry != '..') {

        if ((substr($entry, 0, $prefix_length) == $this->project['release']['prefix'])
          && (substr($entry, -4) != '.md5')
        ) {
          $lines[] = $entry;
        }
      }
    }
    $d->close();

    return $lines;
  }
}
