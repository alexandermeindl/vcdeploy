<?php
/**
 * @file
 *   Plugin to setup drupal installation
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

$plugin['info'] = 'Setup a drupal installation';
$plugin['root_only'] = TRUE;

$plugin['args']['project'] = 'Name of project to setup';
$plugin['args']['command'] = 'Name of subcommand
- reset-files: clear private and public download directories
- reset-settings: copy drupal settings.php to target directory
- reset-db: drop and create database to cleanup
- install: install drupal
- reset: run \'reset-settings\', \'reset-files\' and \'reset-db\'
- reinstall: run \'reset-settings\', \'reset-files\' and \'install\'';

class VcdeployPluginSetupDrupal extends Vcdeploy implements IVcdeployPlugin {

  /**
   * Drupal base directory
   *
   * This is relative to project path
   *
   * @var string
   */
  private $drupal_base_dir = 'htdocs';

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

    $project_name = $this->paras->command->args['project'];
    $command = $this->paras->command->args['command'];

    if (!array_key_exists($project_name, $this->projects)) {
      throw new Exception('Project "' . $project_name . '" is not configured!');
    }
    $this->set_project($project_name, $this->projects[$project_name]);

    if (isset($this->project['setup_drupal']['base_dir'])) {
      $this->drupal_base_dir = $this->project['setup_drupal']['base_dir'];
    }

    $commands = array('reset-files', 'reset-settings', 'reset-db', 'install', 'reinstall', 'reset');

    if (in_array($command, $commands)) {
      $method = 'run' . str_replace(' ', '', ucwords(str_replace('-', ' ', $command)));
      $this->$method();
    }
    else {
      throw new Exception('Subcommand \'' . $command . '\' unknown.');
    }

    return 0;
  }

  /**
   * Reset drupal files directories
   *
   * Reset all data directories of a drupal projects (private and public download directories)
   *
   * @throws Exception
   */
  private function runResetFiles() {

    $this->msg('Run reset files directories...');

    foreach ($this->project['data_dir'] AS $identifier => $dir) {

      $this->msg('Deleting all files in ' . $dir . '...');

      // 1. Remove directory
      if (file_exists($dir)) {
        $this->remove_directory($dir);
      }

      // 2. Recreate directory
      if (!mkdir($dir, 0755)) {
        throw new Exception('Failed to create data directory: ' . $dir);
      }

      // 3. detect which .htaccess content has to be written
      // If data directory is within drupal base directory, private access is set - otherwise public access
      $base_dir = $this->project['path'] . '/' . $this->drupal_base_dir;
      if (substr_count($dir, $base_dir) && substr($identifier, 0, 7)!='private') {
        // within drupal document root - > public directory
        $htaccess_content = "SetHandler Drupal_Security_Do_Not_Remove_See_SA_2006_006\nOptions None\nOptions +FollowSymLinks\n";
      }
      else {
        // outsite drupal document root - > private directory
        $htaccess_content = "SetHandler Drupal_Security_Do_Not_Remove_See_SA_2006_006\nDeny from all\nOptions None\nOptions +FollowSymLinks\n";
      }
      // 4. Add .htaccess file
      if (!file_put_contents($dir . '/.htaccess', $htaccess_content)) {
        throw new Exception('Failed to write .htaccess file to ' . $dir . '/.htaccess');
      }
    }
  }

  /**
   * Reset drupal settings.php
   *
   * Copy settings.php of a source directory to the sites directory
   * of drupal installation
   *
   * @throws Exception
   */
  private function runResetSettings() {

    $this->msg('Run reset settings...');

    $site_dir = $this->project['path'] . '/' . $this->drupal_base_dir . '/sites/' . $this->getSitesSubdir();
    $settings_php = $site_dir . '/settings.php';

    // 1. Create site directory, if directory does not exist

    if (!file_exists($site_dir)) {
      $this->msg('Creating site directory: ' . $site_dir);
      if (!mkdir($site_dir, 0755)) {
        throw new Exception('Failed to create site directory: ' . $site_dir);
      }
    }

    // only run the following steps, if settings.php source is configured
    if (isset($this->project['setup_drupal']['settings_source'])) {

      // 2. check if source file is provided
      if (!file_exists($this->project['setup_drupal']['settings_source'])) {
        throw new Exception('settings.php source file does not exists: ' . $this->project['setup_drupal']['settings_source']);
      }

      // 3. make sure write permissions on target directory/file
      if (!chmod($site_dir, 0775)) {
        throw new Exception('chmod failed to drupal site directory: ' . $site_dir);
      }
      if (file_exists($settings_php) && !chmod($settings_php, 0664)) {
        throw new Exception('chmod failed to drupal settings.php: ' . $settings_php);
      }

      // 4. copy file
      if (!copy($this->project['setup_drupal']['settings_source'], $settings_php)) {
        throw new Exception('Failed to copy settings_source to drupal site directory!');
      }
    }
  }

  /**
   * Reset drupal database
   *
   * @throws Exception
   */
  private function runResetDb() {

    $this->msg('Run reset db...');

    foreach ($this->project['db'] AS $identifier => $db) {
      $this->db_recreate($db);
    }
  }

  /**
   * Run drupal installation with drush
   *
   */
  private function runInstall() {

    $this->msg('Run drupal installation of ' . $this->project_name . '...');

    // 1. Run pre commands
    if (isset($this->project['setup_drupal']['pre_commands'])) {
      $this->hook_commands($this->project['setup_drupal']['pre_commands'], 'pre');
    }

    // 2. Run install
    $command  = '[drush] --yes si ' . $this->project['setup_drupal']['install_profile'] . ' ';
    $command .= '--account-name=' . $this->project['setup_drupal']['account_name'] . ' ';
    $command .= '--account-pass=' . $this->project['setup_drupal']['account_pass'] . ' ';
    $command .= '--sites-subdir=' . $this->getSitesSubdir() . ' ';

    $this->hook_commands(array($command), 'install');

    // 3. Run post commands
    if (isset($this->project['setup_drupal']['post_commands'])) {
      $this->hook_commands($this->project['setup_drupal']['post_commands'], 'post');
    }

    // 4. Set file permissions (has to be after post commands to make sure all created files are affected)
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
   * Run reinstall of drupal
   *
   * 1. $this->runResetSettings()
   * 2. $this->runResetFiles()
   * 3. $this->runInstall()
   *
   * @see $this->runResetSettings, $this->runResetFiles, $this->runInstall
   */
  private function runReinstall() {
    $this->runResetSettings();
    $this->runResetFiles();
    // $this->runResetDb(); // not required, because database dropped with drush
    $this->runInstall();
  }

  /**
   * Execute all reset commands
   *
   * 1. $this->runResetSettings()
   * 2. $this->runResetFiles()
   * 3. $this->runResetDb()
   *
   * @see $this->runResetSettings, $this->runResetFiles, $this->runResetDb
   */
  private function runReset() {
    $this->runResetSettings();
    $this->runResetFiles();
    $this->runResetDb();
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

  private function getSitesSubdir() {
    if (isset($this->project['setup_drupal']['sites_subdir'])) {
      return $this->project['setup_drupal']['sites_subdir'];
    }
    else {
      return 'default';
    }
  }
}
