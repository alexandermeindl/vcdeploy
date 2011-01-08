<?php
/**
 * @file
 *   Update repositories of projects
 *
 * @example
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
 */

$plugin['info'] = 'update all project repositories';
$plugin['root_only'] = FALSE;

class SldeployPluginUpdateProject extends Sldeploy {

  /**
   * Progress bar
   *
   * @var object
   */
  private $bar;

  /**
   * Amount of projects to update
   *
   * @var int
   */
  private $project_amount = 0;

  /**
   * Current project position
   *
   * @var int
   */
  private $current_pos = 0;

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    // check for existing projects
    $this->validate_projects();

    // set amount of projects
    $this->project_amount = count($this->projects);

    if (!isset($this->paras->options['verbose']) || !$this->paras->options['verbose']) {
      $this->bar = new Console_ProgressBar(' %fraction% [%bar%] %percent%  ', '=', ' ', 50, $this->project_amount);
    }

    foreach ($this->projects AS $project_name => $project) {

      $this->set_project($project_name, $project);
      $this->current_pos++;

      if ($this->project['scm']['type']=='static') {
        // do nothing
        $rc = 0;
      }
      else {
        // initialize scm
        $this->set_scm('project');
        $rc = $this->_updateRepository($this->scm->update(), $this->scm->get_name());
      }

      if ($rc) {
        return $rc;
      }
    }

    // Make sure sldeploy is executable
    chmod($this->base_dir . '/sldeploy', 0775);
  }

  /**
   * @params string $command
   * @params string $info
   *
   */
  private function _updateRepository($command, $info) {

    if (!empty($this->project['path'])) {

      // verbose view
      if (isset($this->paras->options['verbose']) && $this->paras->options['verbose']) {
        $this->msg('Updating ' . $info . ' Repository for project ' . $this->project_name . ' (' . $this->current_pos . '/' . $this->project_amount . ')...');
      }
      else {
        $this->bar->update($this->current_pos);
      }

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
  }
}