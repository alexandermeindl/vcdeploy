<?php
/**
 * @file
 *   Update system files
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

$plugin['info'] = 'rollout system files/configuration';
$plugin['root_only'] = TRUE;

$plugin['options']['without_packages'] = array(
                              'short_name'  => '-W',
                              'long_name'   => '--without_packages',
                              'action'      => 'StoreTrue',
                              'description' => 'Don\'t run package commands: depends and conflicts',
                            );

$plugin['options']['without_commands'] = array(
                              'short_name'  => '-C',
                              'long_name'   => '--without_commands',
                              'action'      => 'StoreTrue',
                              'description' => 'Don\'t run pre_commands and post_commands',
                            );

$plugin['options']['without_permissions'] = array(
                      'short_name'  => '-P',
                      'long_name'   => '--without_permissions',
                      'action'      => 'StoreTrue',
                      'description' => 'Don\'t set permissions',
                    );

$plugin['options']['force'] = array(
                          'short_name'  => '-f',
                          'long_name'   => '--force',
                          'action'      => 'StoreTrue',
                          'description' => 'Overwrite files, even if existing files are newer than source files',
                        );

class VcdeployPluginRolloutSystem extends Vcdeploy implements IVcdeployPlugin {

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see vcdeploy#run()
   */
  public function run() {

    if (empty($this->conf['system_source'])) {
      throw new Exception('system_source not specified.');
    }
    else {

      // convert strings to array
      if (!is_array($this->conf['system_source'])) {
        $this->conf['system_source'] = array('main' => $this->conf['system_source']);
      }

      foreach($this->conf['system_source'] AS $system_name => $system_source) {

        if (!file_exists($system_source)) {
          throw new Exception($system_source . ' does not exist');
        }
        elseif (!is_dir($system_source)) {
          throw new Exception($system_source . ' is not a directory');
        }

        chdir($system_source);

        if ($this->conf['source_scm'] == 'static') {
          // do nothing
          $this->progressbar_init(0);
        }
        else {
          // update source
          $this->progressbar_init(1);

          $this->show_progress('Get repository updates...');
          // initialize scm
          $this->set_scm('system');
          $this->system($this->scm->update(), TRUE);
        }

        // update system
        $this->show_progress('Update system files...');
        $this->_copyFiles($system_source);
      }
    }

    // System package support
    $this->_createDirectories();
    $this->_createSymlinks();

    if (!isset($this->paras->command->options['without_packages']) || !$this->paras->command->options['without_packages']) {
      $this->_packageDepends();
      $this->_packageConflicts();
      $this->_packageGemDepends();
    }
    $this->_modsConfig();
    $this->_vhostsConfig();
    $this->_nginxConfig();
    $this->_servicesConfig();

    if (!isset($this->paras->command->options['without_permissions']) || !$this->paras->command->options['without_permissions']) {
      $this->_setSystemPermissions();
    }

    if (!isset($this->paras->command->options['without_commands']) || !$this->paras->command->options['without_commands']) {
      $this->_preCommands();
    }

    $this->_service('reload');
    $this->_service('restart');

    if (!isset($this->paras->command->options['without_commands']) || !$this->paras->command->options['without_commands']) {
      $this->_postCommands();
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

    // 1.  system file update
    if (is_array($this->conf['system_source'])) {
      $init += count($this->conf['system_source']);
    }
    else {
      $init++;
    }

    // 1. Create missing directories
    //$init += 1;

    $init += $this->_createDirectories(TRUE);
    $init += $this->_createSymlinks(TRUE);

    if (!isset($this->paras->command->options['without_packages']) || !$this->paras->command->options['without_packages']) {
      // 2. Update package sources and add required system packages
      $init += 2;

      // 3. Remove unwanted system packages
      $init += 1;

      // gem package
      $init += 1;
    }

    // 4. modsConfig
    $init += $this->_modsConfig(TRUE);

    // 5. vhostsConfig
    $init += $this->_vhostsConfig(TRUE);

    // 6. nginxConfig
    $init += $this->_nginxConfig(TRUE);

    // 7. serviceConfig
    $init += $this->_servicesConfig(TRUE);

    if (!isset($this->paras->command->options['without_commands']) || !$this->paras->command->options['without_commands']) {
      // 8. preCommands
      $init += $this->_preCommands(TRUE);
    }

    // 9. serviceReload
    $init += $this->_service('reload', TRUE);

    // 10. serviceRestart
    $init += $this->_service('restart', TRUE);

    if (!isset($this->paras->command->options['without_permissions']) || !$this->paras->command->options['without_permissions']) {
      foreach ($this->conf['permissions'] AS $permission) {
        if (isset($permission['mod']) && !empty($permission['mod'])) {
          $init++;
        }
        if (isset($permission['own']) && !empty($permission['own'])) {
          $init++;
        }
      }
    }

    if (!isset($this->paras->command->options['without_commands']) || !$this->paras->command->options['without_commands']) {
      // 11. postCommands
      $init += $this->_postCommands(TRUE);
    }

    return $init;
  }

  /**
   * Copy system files
   *
   */
  private function _copyFiles($system_source) {

    $tmp_dir = '';

    if ($this->conf['source_scm'] == 'svn') {

      // temporary directory for SVN copy
      $tmp_dir = $this->conf['tmp_dir'] . '/'. uniqid('vcdeploy_svn_cp_');
      if ($tmp_dir!='/' && file_exists($tmp_dir)) {
        $this->system('rm -rf ' . $tmp_dir, TRUE);
      }

      // Copy files to temporary location
      $this->system($this->conf['cp_bin'] . ' -r . ' . $tmp_dir, TRUE);

      // Remove SVN directories
      $this->system('find "' . $tmp_dir . '/" -name ".svn" -type d -exec rm -rf {} 2>/dev/null \;', TRUE);

      // Cleanup Mac OS X files
      $this->system('find "' . $tmp_dir . '/" -name ".DS_Store" -type f -exec rm -f {} \;', TRUE);

      // Switch to temporary directory
      chdir($tmp_dir);
    }
    else if (getcwd() != $system_source) {
      // Cleanup Mac OS X files
      $this->system('find "' . $system_source . '/" -name ".DS_Store" -type f -exec rm -f {} \;', TRUE);
      // Switch to source directory
      chdir($system_source);
    }

    // Copy files
    if (isset($this->paras->command->options['force']) && $this->paras->command->options['force']) {
      $this->system($this->conf['cp_bin'] . ' -r . /', TRUE);
    }
    else {
      $this->system($this->conf['cp_bin'] . ' -ru . /', TRUE);
    }

    // Clean up
    if (!empty($tmp_dir) && $tmp_dir!='/' && file_exists($tmp_dir)) {
      $this->system('rm -rf ' . $tmp_dir, TRUE);
    }
    if (getcwd() != $system_source) {
        chdir($system_source);
    }
  }

  /**
   * Configure vhosts: enable or disable vhosts
   *
   * @param bool $try if true, this is a test run without system calls
   *
   * @return int amount of system commands
   */
  private function _vhostsConfig($try = FALSE) {

    if (!isset($this->conf['apache_sites'])) {
      $this->conf['apache_sites'] = '';
    }
    if (!isset($this->conf['apache_sites_enable'])) {
      $this->conf['apache_sites_enable'] = '';
    }

    return $this->_activationRun(
      $this->conf['apache_sites'],
      $this->conf['apache_sites_enable'],
      'vhost_enable',
      'Enable vhost',
      'vhost_disable',
      'Disable vhost',
      $try
    );
  }

  /**
   * Configure nginx sites: enable or disable vhosts
   *
   * @param bool $try if true, this is a test run without system calls
   *
   * @return int amount of system commands
   */
  private function _nginxConfig($try = FALSE) {

    if (!isset($this->conf['nginx_sites'])) {
      $this->conf['nginx_sites'] = '';
    }
    if (!isset($this->conf['nginx_sites_enable'])) {
      $this->conf['nginx_sites_enable'] = '';
    }

    return $this->_activationRun(
        $this->conf['nginx_sites'],
        $this->conf['nginx_sites_enable'],
        'nginx_enable',
        'Enable nginx site',
        'nginx_disable',
        'Disable nginx site',
        $try
    );
  }

  /**
   * Apache modules configuration: enable or disable a module
   *
   * @param bool $try if TRUE, this is a test run without system calls
   *
   * @return int amount of system commands
   */
  private function _modsConfig($try = FALSE) {

    if (!isset($this->conf['apache_mods'])) {
      $this->conf['apache_mods'] = '';
    }
    if (!isset($this->conf['apache_mods_enable'])) {
      $this->conf['apache_mods_enable'] = '';
    }

    return $this->_activationRun(
      $this->conf['apache_mods'],
      $this->conf['apache_mods_enable'],
      'mod_enable',
      'Enable apache module',
      'mod_disable',
      'Disable apache module',
      $try
    );
  }

  /**
   * Configure services: enable or disable services
   *
   * @param bool $try if true, this is test run without system calls
   *
   * @return int amount of system commands
   */
  private function _servicesConfig($try = FALSE) {

    if (!isset($this->conf['services'])) {
      $this->conf['services'] = '';
    }
    if (!isset($this->conf['services_enable'])) {
      $this->conf['services_enable'] = '';
    }

    return $this->_activationRun(
      $this->conf['services'],
      $this->conf['services_enable'],
      'service_enable',
      'Enable service',
      'service_disable',
      'Disable service',
      $try
    );
  }


  /**
   * Configure internal subroutine
   *
   * @param bool $try if true, this is test run without system calls
   *
   * @return int amount of system commands
   */
  private function _activationRun($all_string, $active_string, $enable_command, $enable_message, $disable_command, $disable_message, $try = FALSE) {

    $count = 0;

    // Enable and disable
    if (!empty($all_string)) {
      $all = explode(' ', $all_string);
      $enable = array();
      if (!empty($active_string)) {
        $enable = explode(' ', $active_string);
      }

      if (is_array($all)) {
        foreach ($all AS $entry) {
          if (!empty($entry)) {
            if (!$try) {
              if (in_array($entry, $enable)) {
                $this->show_progress($enable_message . ' ' . $entry . '...');
                $this->_systemCommand($enable_command, $entry);
              }
              else {
                $this->show_progress($disable_message . ' ' . $entry . '...');
                $this->_systemCommand($disable_command, $entry);
              }
            }

            // Count runs
            $count++;
          }
        }
      }
    }

    return $count;
  }

  /**
   * Restart services
   *
   * @param string $command command for init script (e.g. reload or restart)
   * @param bool $try if true, this is test run without system calls
   *
   * @return int amount of system commands
   */
  private function _service($command, $try = FALSE) {

    $count = 0;
    $config_key = 'services_' . $command;

    if (!empty($this->conf[$config_key])) {
      $services = explode(' ', $this->conf[$config_key]);
      if (is_array($services)) {
        foreach ($services AS $service) {
          if (!$try) {
            $this->show_progress(ucfirst($command) . ' service ' . $service . '...');
            $this->_systemCommand($command, $service);
          }
          // Count runs
          $count++;
        }
      }
    }

    return $count;
  }

  /**
   * Run defined command system independent
   *
   * @param string  $command = restart, reload
   *                            service_enable, service_disable,
   *                            vhost_enable, vhost_disable,
   *                            mod_enable, mod_disable
   * @param string $para
   *
   * @return int
   */
  private function _systemCommand($command, $para = NULL) {

    switch ($command) {

      case 'service_enable':
        if ($this->conf['system_os'] == 'suse') {
          $rc = $this->system('chkconfig --add ' . $para);
        }
        elseif ($this->conf['system_os'] == 'centos') {
          $rc = $this->system('chkconfig --add ' . $para);
        }
        else { // debian or ubuntu
          $rc = $this->system('update-rc.d ' . $para . ' defaults');
        }
        // gentoo
        // rc-update add $para default
        break;

      case 'service_disable':
        if ($this->conf['system_os'] == 'suse') {
          $rc = $this->system('chkconfig --del ' . $para);
        }
        else if ($this->conf['system_os'] == 'centos') {
          $rc = $this->system('chkconfig --del ' . $para);
        }
        else { // debian or ubuntu
           $rc = $this->system('update-rc.d -f  ' . $para . ' remove');
        }
        // gentoo
        // rc-update del $para
        break;

      case 'vhost_enable':
        if ($this->conf['system_os'] == 'debian' || $this->conf['system_os'] == 'ubuntu') {
          $rc = $this->system('a2ensite -q  ' . $para);
        }
        else {
          $this->msg('vhost configuration not supported with ' . $this->conf['system_os'] . '.');
          $rc = 1;
        }
        break;

      case 'vhost_disable':
        if ($this->conf['system_os'] == 'debian' || $this->conf['system_os'] == 'ubuntu') {
          $rc = $this->system('a2dissite -q  ' . $para);
        }
        else {
          $rc = $this->msg('vhost configuration not supported with ' . $this->conf['system_os'] . '.');
          $rc = 1;
        }
        break;

      case 'nginx_enable':
        $rc = $this->system('nginx_ensite ' . $para);
        break;

      case 'nginx_disable':
        $rc = $this->system('nginx_dissite ' . $para);
        break;

      case 'mod_enable':
        if ($this->conf['system_os'] == 'debian' || $this->conf['system_os'] == 'ubuntu') {
          $rc = $this->system('a2enmod -q  ' . $para);
        }
        else {
          $this->msg('apache modules configuration not supported with ' . $this->conf['system_os'] . '.');
          $rc = 1;
        }
        break;

      case 'mod_disable':
        if ($this->conf['system_os'] == 'debian' || $this->conf['system_os'] == 'ubuntu') {
          $rc = $this->system('a2dismod -q  ' . $para);
        }
        else {
          $rc = $this->msg('apache modules configuration not supported with ' . $this->conf['system_os'] . '.');
          $rc = 1;
        }
        break;

      case 'restart':
        if ($this->conf['system_os'] == 'suse') {
          $rc = $this->system('rc' . $para . ' restart');
        }
        elseif ($this->conf['system_os'] == 'centos') {
          $rc = $this->system('service ' . $para . ' restart');
        }
        elseif ($this->conf['system_os'] == 'ubuntu') {
          $rc = $this->system('service ' . $para . ' restart');
        }
        else { // debian
          $rc = $this->system('invoke-rc.d ' . $para . ' restart');
        }
        break;

      case 'reload':
        if ($this->conf['system_os'] == 'suse') {
          $rc = $this->system('rc' . $para . ' reload');
        }
        elseif ($this->conf['system_os'] == 'centos') {
          $rc = $this->system('service ' . $para . ' reload');
        }
        elseif ($this->conf['system_os'] == 'ubuntu') {
          $rc = $this->system('service ' . $para . ' reload');
        }
        else { // debian
          $rc = $this->system('invoke-rc.d ' . $para . ' reload');
        }
        break;

      default:
        $this->msg('Unknown systemCommand ' . $command . ' used!');
        break;
    }

    return $rc;
  }

  /**
   * Update package sources and install system packages
   *
   * @return int
   * @throws Exception
   */
  private function _packageDepends() {

    if (!empty($this->conf['packages_depends'])) {
      $this->show_progress('First update to latest packages...');
      switch ($this->conf['system_os']) {
        case 'suse':
          #$rc = $this->system('zypper --non-interactive update', TRUE);
          $this->show_progress('Check for SuSE packages to install...');
          $rc = $this->system('zypper --non-interactive install ' . $this->conf['packages_depends'], TRUE);
          break;
        case 'centos':
          #$rc = $this->system('yum -y update', TRUE);
          $this->show_progress('Check for Redhat packages to install...');
          $rc = $this->system('yum install -y ' . $this->conf['packages_depends'], TRUE);
          break;
        case 'ubuntu':
          $rc = $this->system('aptitude update', TRUE);
          #$rc = $this->system('aptitude full-upgrade', TRUE);
          $this->show_progress('Check for Ubuntu packages to install...');
          $rc = $this->system('aptitude install -y ' . $this->conf['packages_depends'], TRUE);
          break;
        case 'debian':
          $rc = $this->system('apt-get -qq update', TRUE);
          #$rc = $this->system('apt-get upgrade', TRUE);
          $this->show_progress('Check for Debian packages to install...');
          $rc = $this->system('apt-get install -yqq ' . $this->conf['packages_depends'], TRUE);
          break;
        default:
          throw new Exception('Package depends system not support on this platform');
      }

      if ($rc['rc']) {
        if (!empty($rc['output'])) {
          $this->msg('An error occured while installing packages:');
          foreach ($rc['output'] AS $line) {
            $this->msg($line);
          }
        }
        else {
          $this->msg('An error occured while installing packages (rc=' . $rc['rc'] . ')');
        }
        return $rc['rc'];
      }
    }
  }

  /**
   * Update package sources and install system packages
   *
   * @return int
   * @throws Exception
   */
  private function _packageConflicts() {

    if (!empty($this->conf['packages_conflicts'])) {
      $this->show_progress('Check for linux packages to remove...');

      switch ($this->conf['system_os']) {
        case 'suse':
          $rc = $this->system('zypper --non-interactive remove ' . $this->conf['packages_conflicts'], TRUE);
          break;
        case 'centos':
          $rc = $this->system('yum uninstall -y ' . $this->conf['packages_conflicts'], TRUE);
          break;
        case 'ubuntu':
          $rc = $this->system('aptitude remove -y ' . $this->conf['packages_conflicts'], TRUE);
          break;
        case 'debian':
          $rc = $this->system('apt-get --purge remove -yqq ' . $this->conf['packages_conflicts'], TRUE);
          break;
        default:
          throw new Exception('Package depends system not support on this platform');
          break;
      }

      if ($rc['rc']) {
        if (!empty($rc['output'])) {
          $this->msg('An error occured while installing packages:');
          foreach ($rc['output'] AS $line) {
            $this->msg($line);
          }
        }
        else {
          $this->msg('An error occured while installing packages (rc=' . $rc['rc'] . ')');
        }
        return $rc['rc'];
      }
    }
  }

  /**
   * Install gem packages
   *
   * @throws Exception
   * @return int amount of system commands
   */
  private function _packageGemDepends() {

    if (!empty($this->conf['gem_packages_depends'])) {

      $this->show_progress('Install ruby gem packages...');
      $rc = $this->system('gem install ' . $this->conf['gem_options'] . ' ' . $this->conf['gem_packages_depends'], TRUE);

      if ($rc['rc']) {
        if (!empty($rc['output'])) {
          $this->msg('An error occured while installing gem packages:');
          foreach ($rc['output'] AS $line) {
            $this->msg($line);
          }
        }
        else {
          $this->msg('An error occured while installing gem packages (rc=' . $rc['rc'] . ')');
        }
        return $rc['rc'];
      }
    }
  }

  /**
   * Create directories
   *
   * @param bool $try if TRUE, this is a test run without system calls
   * @throws Exception
   * @return int amount of system commands
   */
  private function _createDirectories($try = FALSE) {

    $rc = 0;

    if (isset($this->conf['dirs']) &&
      is_array($this->conf['dirs']) &&
      count($this->conf['dirs'])
    ) {

      foreach ($this->conf['dirs'] AS $dir) {
        $rc++;
        if (!$try) {
          if (file_exists($dir)) {
            $this->show_progress('Directory ' . $dir . ' already exists.');
          }
          else {
            $this->show_progress('Creating directory ' . $dir);
            mkdir($dir, 0775, TRUE);
          }
        }
      }
    }

    return $rc;
  }

  /**
   * Create symbolic links
   *
   * @param bool $try if TRUE, this is a test run without system calls
   * @throws Exception
   * @return int amount of system commands
   */
  private function _createSymlinks($try = FALSE) {

    $rc = 0;

    if (isset($this->conf['symlinks']) &&
      is_array($this->conf['symlinks']) &&
      count($this->conf['symlinks'])
    ) {

      foreach ($this->conf['symlinks'] AS $target => $source) {
        $rc++;
        if (!$try) {
          if (file_exists($target)) {
            $this->show_progress('Symlink target ' . $target . ' already exists.');
          }
          else {
            $parent_dir= dirname($target);
            $basename= basename($target);
            if (file_exists($parent_dir)) {
              // change to parent directory
              $current_dir = getcwd();

              try {
                chdir($parent_dir);
              } catch (Exception $e) {
                echo $e->getMessage();
              }

              $this->show_progress('Creating symlink ' . $source . ' => ' . $target);
              $rc = $this->system('ln -s ' . $source . ' ' . $basename);
              chdir($current_dir);
              if ($rc['rc']) {
                throw new Exception('Error creating symlink "' . $target . '"');
              }
            }
            else {
              $this->msg('Cannot create symlink ' . $target. ', because parent directory does not exist.');
            }
          }
        }
      }
    }
    return $rc;
  }

  /**
   * Run pre commands
   *
   * @param bool $try if TRUE, this is a test run without system calls
   * @throws Exception
   * @return int amount of system commands
   */
  private function _preCommands($try = FALSE) {
		$rc = 0;
    if (isset($this->conf['pre_commands'])) {
      $rc = $this->hook_commands($this->conf['pre_commands'], 'pre', $try);
    }
		return $rc;
  }

  /**
   * Run post commands
   *
   * @param bool $try if TRUE, this is a test run without system calls
   * @throws Exception
   * @return int amount of system commands
   */
  private function _postCommands($try = FALSE) {
		$rc = 0;
    if (isset($this->conf['post_commands'])) {
      $rc = $this->hook_commands($this->conf['post_commands'], 'post', $try);
    }
		return $rc;
  }

  /**
   * Set system permissions for files and directories
   *
   * @throws Exception
   */
  private function _setSystemPermissions() {
    if (is_array($this->conf['permissions']) && count($this->conf['permissions'])) {
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
}
