<?php

/**
 * @file
 *
 * Update system files
 *
 */

$plugin['info']       = 'update system files/configuration';
$plugin['root_only']  = TRUE;

class sldeploy_plugin_update_system extends sldeploy {

  public function run() {

    if (empty($this->conf['system_source'])) {
      $this->msg('system_source not specified.');
      exit(1);
    }
    elseif (!file_exists($this->conf['system_source'])) {
      $this->msg($this->conf['system_source'] .' does not exist');
      exit(2);
    }
    elseif (!is_dir($this->conf['system_source'])) {
      $this->msg($this->conf['system_source'] .' is not a directory');
      exit(3);
    }

    chdir($this->conf['system_source']);

    # update source
    if ($this->conf['source_scm']=='svn') {
      $this->system($this->conf['svn_bin'] .' update', TRUE);
    }
    elseif ($this->conf['source_scm']=='cvs') {
      $this->system($this->conf['cvs_bin'] .' update', TRUE);
    }
    else {
      $this->system($this->conf['git_bin'] .' pull', TRUE);
    }

    # update system
    $this->msg('Update system files...');
    $this->system($this->conf['cp_bin'] .' -ru . /', TRUE);

    // restart services
    if (!empty($this->conf['restart_services'])) {
      $services = explode(' ', $this->conf['restart_services']);
      if (is_array($services)) {
        foreach ($services AS $service) {
          $this->msg('Restart '. $service .'...');
          $this->system('invoke-rc.d '. $service .' restart');
        }
      }
    }
  }

}