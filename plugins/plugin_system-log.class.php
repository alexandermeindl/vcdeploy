<?php
/**
 * @file
 *   Plugin for log system changes
 *
 *   - log drupal module changes
 *   - log system package changes
 *   - log etc changes
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

$plugin['info']       = 'log system changes';
$plugin['root_only']  = FALSE;

class sldeploy_plugin_system_log extends sldeploy {

  /**
   * File name for package list
   *
   * @var string
   */
  private $package_file_name = 'packages.txt';

  /**
   * Directory to use for log files
   *
   * @var string
   */
  private $log_dir;

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    if (empty($this->conf['package_manager'])) {
        $this->msg('No package manager defined', 1);
    }
    else if (empty($this->conf['log_source'])) {
        $this->msg('No log_source directory defined', 1);
    }
    else if (!file_exists($this->conf['log_source'])) {
        $this->msg('log_source does not exist', 1);
    }

    # set log_dir
    $this->log_dir = $this->conf['log_source'] .'/'. $this->conf['log_host'];

    // is not working without changing directory with php script :(
    chdir($this->conf['log_source']);
    // make sure we have the latest content, which is required to avoid conflicts
    $this->system($this->conf['git_bin'] .' --work-tree '. $this->conf['log_source'] .' pull', TRUE);

    if ($this->conf['log_etc_dir']) {
      if ($this->current_user == 'root') {
        $this->etc_log();
      }
      else {
        $this->msg('system etc and packages log is only tracked for root');
      }
    }

    $this->package_list();
    $this->drupal_log();

    $this->commit_to_vcm();
  }


  /**
   * Exclude paremeters for rsync
   *
   */
  private function get_exclude_parameters() {

    $paras = array();

    $excludes = explode(' ', $this->conf['log_excludes']);
    foreach($excludes AS $exclude) {
      $paras[] = '--exclude='. $exclude;
    }

    return implode(' ', $paras);
  }

  /**
    * Is the server mac os x or linux
    *
    */
  private function detect_darwin() {

    ob_start();
    phpinfo(1);
    $pinfo = ob_get_contents();
    ob_end_clean();

    if (substr_count(strtolower($pinfo), 'darwin kernel version')>0)  return TRUE;
    else  return FALSE;
  }

  /**
   * Log changes in etc directory
   *
   */
  private function etc_log() {

    # run rsync
    $this->msg('rsync etc directory for tracking...');
    # source directory with added slash (required on mac os x)
    $cmd = $this->conf['rsync_bin'] .' -aH '. $this->get_exclude_parameters() .' '. $this->conf['etc_dir'] .'/ '. $this->log_dir .'/etc';
    $this->system($cmd);

    # track file metafiles
    if ($this->detect_darwin()) $ls_para = '-RlT';
    else                        $ls_para = '-Rl --full-time';

    $rc = $this->system('ls '. $ls_para .' '. $this->log_dir .'/etc');
    if (!$rc['rc']) {
      if (is_array($rc['output'])) {
        $info = '';
        foreach($rc['output'] AS $line) {
          $info .= $line ."\n";
        }
        $filename = $this->log_dir .'/etc_metadata.txt';
        file_put_contents($filename, $info);
      }
    }
    else {
        $this->msg('Cannot calculate etc metadata information', 1);
    }
  }

  /**
   * Log drupal changes
   *
   */
  private function drupal_log() {

    if (count($this->projects)) {
      foreach($this->projects AS $project_name => $project) {
        $this->set_project($project_name, $project);

        if (isset($this->project['drush']))
          $this->msg('Drupal module log on '. $this->project_name);
          $this->drush_modules($project_name, $this->project['drush']);
      }
    }
  }

  /**
   * Get information about drupal modules
   *
   */
  private function drush_modules($project_name, $script) {
    $rc = $this->system($script . ' pm-list');
    if (!$rc['rc']) {
      if (is_array($rc['output'])) {
        $info = '';
        foreach($rc['output'] AS $line) {
          $info .= $line ."\n";
        }
        $filename = $this->log_dir .'/drupal_modules_'. $project_name .'.txt';
        file_put_contents($filename, $info);
      }
    }
  }

  /**
    * Create a list with all installed software packages on the system and commit this
    * list to git
    *
    */
  private function package_list() {

    $this->msg('Create system package list...');
    $cmd_create = '';
    switch ($this->conf['package_manager']) {
      case 'apt':
        $cmd_create = "dpkg-query -W -f '\${Status}\t\${Package} \${Version}\n' | egrep '(ok installed|ok config-files)' | cut -f2,3";
        #$cmd_create = 'dpkg --get-selections "*"';
        break;
      case 'rpm':
        $cmd_create = 'rpm -qa --queryformat "%{name} %{version} %{arch}\n" | sort';
        break;
      case 'port':
        $cmd_create = 'port installed';
        break;
      default:
            $this->msg('Package manager '. $this->conf['package_manager'] .' is not supported', 1);
    }

    // absolute path of package log file
    $package_log_file = $this->log_dir .'/'. $this->package_file_name;

    $this->system($cmd_create .' > '. $package_log_file, TRUE);
  }

  /**
   * Commit changes to vcm
   *
   */
  private function commit_to_vcm() {
    $this->system($this->conf['git_bin'] .' --work-tree '. $this->conf['log_source'] .' add -A '. $this->log_dir, TRUE);
    $this->system($this->conf['git_bin'] .' --work-tree '. $this->conf['log_source'] .' commit -m "Changes found on '. $this->hostname .'"', TRUE);
    $this->system($this->conf['git_bin'] .' --work-tree '. $this->conf['log_source'] .' push', TRUE);
  }
}