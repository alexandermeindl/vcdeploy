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
 * @package  sldeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/sldeploy
 */

$plugin['info'] = 'update system files/configuration';
$plugin['root_only'] = TRUE;

class SldeployPluginUpdateSystem extends Sldeploy {

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see sldeploy#run()
   */
  public function run() {

    if (empty($this->conf['system_source'])) {
      throw new Exception('system_source not specified.');
    }
    elseif (!file_exists($this->conf['system_source'])) {
      throw new Exception($this->conf['system_source'] . ' does not exist');
    }
    elseif (!is_dir($this->conf['system_source'])) {
      throw new Exception($this->conf['system_source'] . ' is not a directory');
    }

    chdir($this->conf['system_source']);

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
    $this->system($this->conf['cp_bin'] . ' -ru . /', TRUE);

    $this->_vhostsConfig();
    $this->_servicesConfig();
    $this->_servicesRestart();

    return 0;
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

    // 1.  system file update
    $init++;

    // 2. vhostsConfig
    $init += $this->_vhostsConfig(TRUE);

    // 3. serviceConfig
    $init += $this->_servicesConfig(TRUE);

    // 4. serviceRestart
    $init += $this->_servicesRestart(TRUE);

    return $init;
  }

  /**
   * Configure vhosts: enable or disable vhosts
   *
   * @param bool $try if true, this is test run without system calls
   *
   * @return int amount of system commands
   */
  private function _vhostsConfig($try = FALSE) {

    $count = 0;

    // Enable and disable apache vhosts
    if (!empty($this->conf['apache_sites'])) {
      $vhosts = explode(' ', $this->conf['apache_sites']);
      $vhosts_enable = array();
      if (!empty($this->conf['apache_sites_enable'])) {
        $vhosts_enable = explode(' ', $this->conf['apache_sites_enable']);
      }

      if (is_array($vhosts)) {
        foreach ($vhosts AS $vhost) {
          if (!empty($vhost)) {
            if (!$try) {
              if (in_array($vhost, $vhosts_enable)) {
                  $this->show_progress('Enable vhost ' . $vhost . '...');
                  $this->_systemCommand('vhost_enable', $vhost);
              }
              else {
                $this->show_progress('Disable vhost ' . $vhost . '...');
                $this->_systemCommand('vhost_disable', $vhost);
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
   * Configure services: enable or disable services
   *
   * @param bool $try if true, this is test run without system calls
   *
   * @return int amount of system commands
   */
  private function _servicesConfig($try = FALSE) {

    $count = 0;

    // Enable and disable services
    if (!empty($this->conf['services'])) {
      $services = explode(' ', $this->conf['services']);
      $services_enable = array();
      if (!empty($this->conf['services_enable'])) {
        $services_enable = explode(' ', $this->conf['services_enable']);
      }

      if (is_array($services)) {
        foreach ($services AS $service) {
          if (!empty($service)) {
            if (!$try) {
              if (in_array($service, $services_enable)) {
                $this->show_progress('Enable service ' . $service . '...');
                $this->_systemCommand('service_enable', $service);
              }
              else {
                $this->show_progress('Disable service ' . $service . '...');
                $this->_systemCommand('service_disable', $service);
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
   * @param bool $try if true, this is test run without system calls
   *
   * @return int amount of system commands
   */
  private function _servicesRestart($try = FALSE) {

    $count = 0;

    if (!empty($this->conf['services_restart'])) {
      $services = explode(' ', $this->conf['services_restart']);
      if (is_array($services)) {
        foreach ($services AS $service) {
          if (!$try) {
            $this->show_progress('Restart service ' . $service . '...');
            $this->_systemCommand('restart', $service);
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
   * @param string  $command = restart, service_enable,
   *                            service_disable, vhost_enable, vhost_disable
   * @param string $para
   *
   * @return int
   */
  private function _systemCommand($command, $para = NULL) {

    switch ($command) {

      case 'service_enable':
        if ($this->conf['system_os'] == 'suse') {
          $rc = $this->system('chkconfig httpd ' . $para . ' --add');
        }
        else { // debian
          $rc = $this->system('update-rc.d ' . $para . ' defaults');
        }
        // gentoo
        // rc-update add $para default
        break;

      case 'service_disable':
        if ($this->conf['system_os'] == 'suse') {
          $rc = $this->system('chkconfig httpd ' . $para . ' --del');
        }
        else { // debian
           $rc = $this->system('update-rc.d -f  ' . $para . ' remove');
        }
        // gentoo
        // rc-update del $para
        break;

      case 'vhost_enable':
        if ($this->conf['system_os'] == 'debian') {
          $rc = $this->system('a2ensite -q  ' . $para);
        }
        else {
          $this->msg('vhost configuration not supported with ' . $this->conf['system_os'] . '.');
          $rc = 1;
        }
        break;

      case 'vhost_disable':
        if ($this->conf['system_os'] == 'debian') {
          $rc = $this->system('a2dissite -q  ' . $para);
        }
        else {
          $rc = $this->msg('vhost configuration not supported with ' . $this->conf['system_os'] . '.');
          $rc = 1;
        }
        break;

      case 'restart':
        if ($this->conf['system_os'] == 'suse') {
          $rc = $this->system('rc' . $para . ' restart');
        }
        else { // debian
          $rc = $this->system('invoke-rc.d ' . $para . ' restart');
        }
        break;

      default:
        $this->msg('Unknown systemCommand ' . $command . ' used!');
        break;
    }

    return $rc;
  }
}