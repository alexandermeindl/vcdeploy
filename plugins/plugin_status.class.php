<?php
/**
 * @file
 *   configuration information of sldeploy
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
 *     - bzr support
 *
 */

$plugin['info'] = 'status messages and information of sldeploy environment';
$plugin['root_only'] = FALSE;

class SldeployPluginStatus extends Sldeploy {

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    $this->msg("Version:\t\t" . $this->version);
    $this->msg("SCM:\t\t\t" . $this->conf['source_scm']);
    $this->msg("System OS:\t\t" . $this->conf['system_os']);
    $this->msg("Config file:\t\t" . $this->conf['config_file']);
    $this->msg("System source:\t\t" . $this->conf['system_source']);
    $this->msg("Backup dir:\t\t" . $this->conf['backup_dir']);
    $this->msg("Project (active):\t" . count($this->get_projects()));
    $this->msg("Project (all):\t\t" . count($this->get_all_projects()));
  }
}