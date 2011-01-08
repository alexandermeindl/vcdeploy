<?php
/**
 * @file
 *   Richtet eine neue Umgebung ein
 *   Voraussetung damit das funktioniert:
 *   Manuelle Installation von:
 *   apt-get install git
 *   git clone git.squatlabs.com:system_SERVER_NAME /root/git_system
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

$plugin['info'] = 'initial a new system environment';
$plugin['root_only'] = TRUE;

class SldeployPluginInitSystem extends Sldeploy {

    /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    $this->msg('First update to latest packages...');
    switch ($this->conf['system_os']) {
      case 'suse':
        $rc = $this->system('zypper --non-interactive update', TRUE);
        break;
      case 'debian':
        $rc = $this->system('apt-get update', TRUE);
        $rc = $this->system('apt-get upgrade', TRUE);
        break;
      default:
        throw new Exception('init system not support on this platform');
    }

    if (is_array($this->conf['init-system']['dirs'])
      && count($this->conf['init-system']['dirs'])
    ) {

      foreach ($this->conf['init-system']['dirs'] AS $dir) {
        if (file_exists($dir)) {
          $this->msg('Directory ' . $dir . ' already exists.');
        }
        else {
          $this->msg('Creating directory ' . $dir);
          mkdir($dir, 0775, TRUE);
        }
      }
    }

    if (!empty($this->conf['init-system']['packages'])) {
      $this->msg('Install linux packages...');
      if ($this->conf['system_os'] == 'suse') {
        $rc = $this->system('zypper --non-interactive install ' . $this->conf['init-system']['packages'], TRUE);
      }
      else { // debian
        $rc = $this->system('apt-get install -y ' . $this->conf['init-system']['packages'], TRUE);
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
        exit($rc['rc']);
      }

      $this->_setPhpLogFile();
    }
  }

  /**
   * Set PHP log file
   */
  private function _setPhpLogFile() {

    $php_log_file = '/var/log/php_errors';

    if ($this->conf['system_os'] == 'suse') {
      chown($php_log_file, 'wwwrun');
      chgrp($php_log_file, 'www');
    }
    else {
      $this->system('apt-get clean');
      chown($php_log_file, 'www-data');
      chgrp($php_log_file, 'www-data');
    }
  }

}