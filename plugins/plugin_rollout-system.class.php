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

        if (isset($this->paras->command->options['force']) && $this->paras->command->options['force']) {
          $this->system($this->conf['cp_bin'] . ' -r . /', TRUE);
        }
        else {
          $this->system($this->conf['cp_bin'] . ' -ru . /', TRUE);
        }
      }
    }

    $this->_modsConfig();
    $this->_vhostsConfig();
    $this->_nginxConfig();
    $this->_servicesConfig();
    $this->_service('reload');
    $this->_service('restart');

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

    // 2. modsConfig
    $init += $this->_modsConfig(TRUE);

    // 3. vhostsConfig
    $init += $this->_vhostsConfig(TRUE);

    // 4. nginxConfig
    $init += $this->_nginxConfig(TRUE);

    // 5. serviceConfig
    $init += $this->_servicesConfig(TRUE);

    // 6. serviceReload
    $init += $this->_service('reload', TRUE);

    // 7. serviceRestart
    $init += $this->_service('restart', TRUE);

    return $init;
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
}