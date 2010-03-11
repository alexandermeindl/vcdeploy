<?php
/**
 * @file
 *   Backup for projects and independent files/directory
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

$plugin['info']       = 'Create backup of files';
$plugin['root_only']  = FALSE;

class sldeploy_plugin_backup_files extends sldeploy {

  /**
   * This function is run with the command
   *
   * @see sldeploy#run()
   */
  public function run() {

    $this->msg('Run backups...');
    $this->backup_files();
  }

  /**
   * Create backup of specified directories
   *
   */
  private function backup_files() {

    if (is_array($this->conf['backup_daily'])) {

      foreach($this->conf['backup_daily'] AS $name => $values) {

        if (!isset($values['dir'])) {
          $this->msg('Missing dir for '. $name .' backup set!');
        }
        else if (!file_exists($values['dir'])) {
          $this->msg('Backup target directory does not exist: '. $values['dir']);
        }
        else {

          if (!isset($values['excludes'])) {
            $values['excludes'] = array();
          }

          if (isset($values['multi']) && ($values['multi'])) { // MULTI

            if (!isset($values['multi_excludes'])) {
              $values['multi_excludes'] = array();
            }

            $source_dirs = $this->get_multi_source_dirs($values['dir'], $values['multi_excludes']);
            foreach($source_dirs AS $source_name => $source_dir) {
              $backup_name = $name .'_'. $source_name;
              $this->msg('creating backup for '. $backup_name .' ('. $source_dir .')...');
              $this->create_backup($backup_name, $source_dir, $values['excludes']);
            }
          }
          else {                                               // SINGLE
            $this->msg('creating backup for '. $name .' ('. $values['dir'].')...');
            $this->create_backup($name, $values['dir'], $values['excludes']);
          }
        }
      }
    }
  }

  /**
   * Create backup of a directory
   *
   * @param string  $name
   * @param string  $source_dir
   * @param array   $excludes
   */
  private function create_backup($name, $source_dir, $excludes) {

    $target_file = $this->conf['backup_dir'] .'/'. $name .'-'. $this->date_stamp .'.tar';

    // change to parent directory
    $dir        = basename($source_dir);
    $parent_dir = dirname($source_dir);

    chdir($parent_dir);

    $command = $this->conf['tar_bin'] .' cplf '. $target_file;
    if (isset($excludes) && is_array($excludes)) {
      $command .= ' --exclude="'. implode('" --exclude="', $excludes) .'"';
    }
    $command .= ' '. $dir;

    $this->set_nice('low');
    $rc = $this->system($command);

    if (!$rc['rc']) {
      $this->gzip_file($target_file);
    }
    else {
      $this->msg('Error on creating backup '. $name .' (dir: '. $source_dir .')');
    }
  }

  /**
   * Get directories for multi mode
   *
   * @param string  $source_dir
   * @param array   $excludes
   */
  private function get_multi_source_dirs($source_dir, $excludes) {

    $dirs = array();

    $d = dir($source_dir);
    while (false !== ($entry = $d->read())) {
      if ($entry!='.' && $entry!='..' && is_dir($source_dir .'/'. $entry)) {
        $ok = TRUE;
        reset($excludes);
        foreach($excludes AS $exclude) {
          if ($entry == $exclude) {
            $ok = FALSE;
            break;
          }
        }

        if ($ok) {
          $dirs[$entry] = $source_dir .'/'. $entry;
        }
      }
    }
    $d->close();

    return $dirs;
  }
}
