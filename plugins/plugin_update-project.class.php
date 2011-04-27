<?php
/**
 * @file
 *   Update repositories of projects
 *
 * @example
 *
 * $project['xhprof']['path'] = '/www/xhprof';
 * $project['xhprof']['scm']['type'] = 'git';
 * $project['xhprof']['scm']['url'] = 'https://github.com/preinheimer/xhprof.git';
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
 *       - bzr support
 *
 * @package  sldeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/sldeploy
 */

$plugin['info'] = 'update all project repositories';
$plugin['root_only'] = FALSE;

class SldeployPluginUpdateProject extends Sldeploy {

  /**
   * This function is run with the command
   *
   * @return void
   * @see sldeploy#run()
   */
  public function run() {

    // check for existing projects
    $this->validate_projects();

    $this->progressbar_init();

    foreach ($this->projects AS $project_name => $project) {

      $this->set_project($project_name, $project);
      $this->progressbar_step();

      if ($this->project['scm']['type'] == 'static') {
        // do nothing
        $rc = 0;
      }
      else {
        // initialize scm
        $this->set_scm('project');

        // 1. run pre commands
        if (isset($this->project['update_project']['pre_commands'])) {
          $this->hook_commands($this->project['update_project']['pre_commands'], 'pre');
        }
        // 2. Update repository
        $rc = $this->_updateRepository($this->scm->update(), $this->scm->get_name());

        // 3. run post commands
        if (isset($this->project['update_project']['post_commands'])) {
          $this->hook_commands($this->project['update_project']['post_commands'], 'post');
        }
      }

      if ($rc) {
        return $rc;
      }
    }

    // Make sure sldeploy is executable
    chmod($this->base_dir . '/sldeploy', 0775);
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
    return $init + count($this->projects);
  }

  /**
   * Update repository
   *
   * @params string $command
   * @params string $info
   *
   * @return int
   * @throws Exception
   */
  private function _updateRepository($command, $info) {

    if (!empty($this->project['path'])) {

      $this->show_progress('Updating ' . $info . ' Repository for project ' . $this->project_name . ' (' . $this->get_progressbar_pos() . '/' . $this->get_steps() . ')...', FALSE);

      if (file_exists($this->project['path'])) {
        if (is_dir($this->project['path'])) {
          chdir($this->project['path']);
          $rc = $this->system($command, TRUE);
          return $rc['rc'];
        }
        else {
          throw new Exception($this->project['path'] . ' is not a directory');
        }
      }
    }
    else {
      $this->msg('No path specified for project ' . $this->project_name);
    }

    return 0;
  }
}