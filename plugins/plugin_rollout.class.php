<?php
/**
 * @file
 *   Plugin to rollout a project release
 *
 * Workflow:
 *
 *  - rollout project files
 *  - rollout database
 *  - rollout directories
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

$plugin['info'] = 'rollout a new release';
$plugin['root_only'] = FALSE;

$plugin['options']['project'] = array(
                              'short_name'  => '-p',
                              'long_name'   => '--project',
                              'action'      => 'StoreString',
                              'description' => 'Rollout this project only (if skipped, all projects will be rolled out)',
);

$plugin['options']['tag'] = array(
                              'short_name'  => '-t',
                              'long_name'   => '--tag',
                              'action'      => 'StoreString',
                              'description' => 'Use this tag for rollout. If no tag is set with this option, default is used [rollout][tag]',
                            );

$plugin['options']['with_db'] = array(
                                        'short_name'  => '-d',
                                        'long_name'   => '--with_db',
                                        'action'      => 'StoreTrue',
                                        'description' => 'Rollout database with this release (overwrites [rollout][with_db]',
                                      );

$plugin['options']['without_db'] = array(
                                          'short_name'  => '-D',
                                          'long_name'   => '--without_db',
                                          'action'      => 'StoreTrue',
                                          'description' => 'Do not create database dump file with this release (overwrites [rollout][with_db]',
                                        );

$plugin['options']['with_data'] = array(
                                        'short_name'  => '-f',
                                        'long_name'   => '--with_data',
                                        'action'      => 'StoreTrue',
                                        'description' => 'Create data dump with this release (overwrites [rollout][with_data]',
                                      );

$plugin['options']['without_data'] = array(
                                          'short_name'  => '-F',
                                          'long_name'   => '--without_data',
                                          'action'      => 'StoreTrue',
                                          'description' => 'Do not create data dump with this release (overwrites [rollout][with_data]',
                                        );

$plugin['options']['with_backup'] = array(
                                          'short_name'  => '-b',
                                          'long_name'   => '--with_backup',
                                          'action'      => 'StoreTrue',
                                          'description' => 'Create a backup before the rollout (default with setting without_backup=FALSE)',
                                        );

$plugin['options']['without_backup'] = array(
                                          'short_name'  => '-B',
                                          'long_name'   => '--without_backup',
                                          'action'      => 'StoreTrue',
                                          'description' => 'Do not create a backup before the rollout (default with setting without_backup=TRUE)',
                                        );

class VcdeployPluginRollout extends Vcdeploy implements IVcdeployPlugin {

  /**
   * Release TAG
   *
   * @var string
   */
  private $tag;

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

    // check backup directory if exists and is writable
    if ($this->is_backup_required()) {
      $this->prepare_backup_dir();
    }

    $this->progressbar_init();

    if (isset($this->paras->command->options['tag']) && !empty($this->paras->command->options['tag'])) {
      $this->tag = $this->paras->command->options['tag'];
    }
    elseif (isset($this->project['rollout']['tag'])) {
      $this->tag = $this->project['rollout']['tag'];
    }
    elseif ($this->_isTagRequired()) {
      throw new Exception('No release TAG specified.');
    }

    if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
      $project_name = $this->paras->command->options['project'];
      if (!array_key_exists($project_name, $this->projects)) {
        throw new Exception('Project "' . $project_name . '" is not configured!');
      }
      $this->set_project($project_name, $this->projects[$project_name]);
      $this->progressbar_step();
      $this->msg('Project: ' . $this->project_name);

      if (!isset($this->project['path'])) {
        throw new Exception('Project path is required for rollout!');
      }

      // initialize scm
      $this->set_scm('project');
      $rc = $this->_projectRollout();
    }
    else {

      foreach ($this->projects AS $project_name => $project) {

        $this->set_project($project_name, $project);
        $this->progressbar_step();

        if (!isset($this->project['path'])) {
          throw new Exception('Project path is required for rollout!');
        }

        //initialize scm
        $this->set_scm('project');
        $rc = $this->_projectRollout();

        if ($rc) {
          return $rc;
        }
      }
    }

    // Make sure vcdeploy is executable
    chmod($this->base_dir . '/vcdeploy', 0775);

    return $rc;
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
    if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
      $rc = 1;
    }
    else {
      $rc = count($this->projects);
    }

    // with backup
    if ($this->is_backup_required()) {
      $rc *= 2;
    }

    // with permissions
    if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
      $project_name = $this->paras->command->options['project'];
      $rc += $this->_countPermissions($this->projects[$project_name]);
    }
    else {
      foreach ($this->projects AS $project_name => $project) {
        $rc += $this->_countPermissions($project);
      }
    }

    return $init + $rc;
  }

  /**
   * Get number of permission loops
   *
   */
  private function _countPermissions($project) {
    $count = 0;
    if (isset($project['permissions']) && is_array($project['permissions'])) {

      foreach ($project['permissions'] AS $permission) {
        if (isset($permission['mod']) && !empty($permission['mod'])) {
          $count++;
        }
        if (isset($permission['own']) && !empty($permission['own'])) {
          $count++;
        }
      }
    }
    return $count;
  }

  /**
   * Rollout project
   *
   * Three type of archive files are supported
   *
   * ['rollout']['with_project_archive'] = TRUE
   * an archive file of the project code is used
   * to rollout (and not an VCS update command)
   *
   * ['rollout']['with_db'] = TRUE
   * database dump of the release (tag) will replace the project database
   *
   * ['rollout']['with_data'] = TRUE
   * directories/files (data) of the release (tag) will replace the project data
   *
   * @return int
   * @throws Exception
   */
  private function _projectRollout() {

    // Create backup, if required
    if ($this->is_backup_required()) {
      $this->_backup();
    }

    if (isset($this->project['rollout']['with_project_archive'])
      && $this->project['rollout']['with_project_archive']
      && isset($this->project['rollout']['with_project_scm'])
      && $this->project['rollout']['with_project_scm']
    ) {
      throw new Exception('You cannot use with_project_archive and with_project_scm together!');
    }
    else {

      $this->show_progress('Updating ' . $this->scm->get_name() . ' Repository for project ' . $this->project_name . ' (' . $this->get_progressbar_pos() . '/' . $this->get_steps() . ')...', FALSE);


      // 1. run pre commands
      if (isset($this->project['rollout']['pre_commands'])) {
        $this->hook_commands($this->project['rollout']['pre_commands'], 'pre');
      }

      // 2. project code
      if (isset($this->project['rollout']['with_project_archive']) && $this->project['rollout']['with_project_archive']) {
        $rc = $this->_codeRollout();
      }
      elseif (!isset($this->project['rollout']['with_project_scm']) || $this->project['rollout']['with_project_scm']) {
        $rc = $this->_scmRollout();
      }

      // 3. databases rollout
      if ((isset($this->paras->command->options['with_db']) && ($this->paras->command->options['with_db']))
        || (isset($this->project['rollout']['with_db']) && $this->project['rollout']['with_db'])
      ) {
        if (!isset($this->paras->command->options['without_db']) || (!$this->paras->command->options['without_db'])) {
          $rc = $this->_dbRollout();
        }
      }

      // 4. data (files/directories)
      if ((isset($this->paras->command->options['with_data']) && ($this->paras->command->options['with_data']))
        || (isset($this->project['rollout']['with_data']) && $this->project['rollout']['with_data'])
      ) {
        if (!isset($this->paras->command->options['without_data']) || (!$this->paras->command->options['without_data'])) {
          if ($this->current_user != 'root') {
            throw new Exception('with_data requires to run script with root privileges for project ' . $this->project_name);
          }
          $rc = $this->_dataRollout();
        }
      }

      // 5. run post commands
      if (isset($this->project['rollout']['post_commands'])) {
        $this->hook_commands($this->project['rollout']['post_commands'], 'post');
      }

      // 6. Permissions (has to be after post commands to make sure all created files are affected)
      if (isset($this->project['permissions']) && is_array($this->project['permissions'])) {

        if ($this->current_user != 'root') {
          throw new Exception('permission commands requires to run script with root privileges for project ' . $this->project_name);
        }
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

    return $rc;
  }

  /**
   * Make backup of data, database and project code
   *
   * @return void
   */
  private function _backup() {

    // create backup of existing data
    if (isset($this->project['data_dir'])) {
      $this->create_project_data_backup();
    }

    // create backups of databases
    if (isset($this->project['db'])) {

      if (!is_array($this->project['db'])) {
        $dbs = array($this->project['db']);
      }
      else {
        $dbs = $this->project['db'];
      }

      // create backups of all existing project databases
      foreach ($dbs AS $db) {
        $this->create_db_dump($db);
      }
    }

    // create backup of project code
    $target_file = $this->conf['backup_dir']
                        . '/' . $this->project_name . '-' . $this->date_stamp . '.tar';

    // Create dump (this can take a long time)
    if (isset($this->project['data_dir'])) {
      $this->create_data_dump($this->project['path'], $target_file, $this->project['data_dir']);
    }
    else {
      // without exceptions
      $this->create_data_dump($this->project['path'], $target_file);
    }
  }

  /**
   * Rollout database
   *
   * The existing database will be replaced
   * with the database from the release tag
   *
   * @return int
   * @throws Exception
   */
  private function _dbRollout() {

    foreach ($this->project['db'] AS $identifier => $db) {

      $sql_file = $this->_getRestoreSqlFile($identifier);

      // recreate database
      $this->db_recreate($db);

      $this->msg('Rolling out database...');
      $this->system($this->conf['gunzip_bin'] . ' < ' . $sql_file . ' | ' . $this->conf['mysql_bin'] . ' ' . $db);

      $this->msg('Database ' . $db . ' has been successfully reseted.');
    }

    return 0;
  }

  /**
   * Get database restore file
   *
   * @param string $db
   *
   * @return string
   * @throws Exception
   */
  private function _getRestoreSqlFile($db) {

    $file = $this->project['rollout']['releases_dir']
              . '/' . $this->project['rollout']['prefix']
              . $db
              . '-' . $this->tag . '.sql.gz';

    if (!file_exists($file)) {
      throw new Exception('Database release file \'' . $file . '\' does not exist.');
    }

    return $file;
  }

  /**
   * Get data restore file
   *
   * @param string $name
   *
   * @return string
   * @throws Exception
   */
  private function _getRestoreDataFile($name) {

    $file = $this->project['rollout']['releases_dir']
              . '/' . $this->project['rollout']['prefix']
              . $name
              . '-' . $this->tag . '.tar.gz';

    if (!file_exists($file)) {
      throw new Exception('Data release file \'' . $file . '\' does not exist.');
    }

    return $file;
  }

  /**
   * Files/directory database
   *
   * Existing directories/files will be replaced
   * with the archive of the release tag
   *
   * @return int
   */
  private function _dataRollout() {

    // remove existing target directories
    foreach ($this->project['data_dir'] AS $identifier => $dir) {

      $tar_file = $this->_getRestoreDataFile($identifier);

      $this->msg('Removing data directory \'' . $identifier . '\'...');
      $this->remove_directory($dir);

      $this->msg('Rolling out data directory \'' . $identifier . '\'...');

      // Restore Tar file
      chdir(dirname($dir)); // go to parent directory
      $this->system($this->conf['tar_bin'] . ' -xz --no-same-owner -f ' . $tar_file);
    }

    return 0;
  }

  /**
  * Check if TAG is required to run rollout
  *
  * Only SCM rollout without TAG is possible
  *
  * @return bool
  */
  private function _isTagRequired() {

  // required for TAG files
    if (isset($this->project['rollout']['with_project_archive'])
          && $this->project['rollout']['with_project_archive']) {
        return TRUE;
  }

  // required for db data
    if ((isset($this->paras->command->options['with_db']) && ($this->paras->command->options['with_db']))
    || (isset($this->project['rollout']['with_db']) && $this->project['rollout']['with_db'])
    ) {
    return TRUE;
      }

      // required for files data
    if ((isset($this->paras->command->options['with_data']) && ($this->paras->command->options['with_data']))
    || (isset($this->project['rollout']['with_data']) && $this->project['rollout']['with_data'])
    ) {
    return TRUE;
      }

    return FALSE;
  }

  /**
   * Rollout project code
   *
   * The existing project code will be replaced
   * with the archive of the release tag
   *
   * @return bool
   */
  private function _codeRollout() {

    throw new Exception('Not implemented.');

    return TRUE;
  }

  /**
   * Rollout from SCM
   *
   * @throws Exception
   */
  private function _scmRollout() {

    if ($this->project['scm']['type']!='static') {

      if (file_exists($this->project['path'])) {
        if (is_dir($this->project['path'])) {
          chdir($this->project['path']);
        }
        else {
          throw new Exception($this->project['path'] . ' is not a directory');
        }
      }

      // update scm project code
      $rc = $this->system($this->scm->update(), TRUE);
      if ($rc['rc']) {
        throw new Exception('SCM type static is not supported with rollout');
      }
      // only if TAG is defined
      if ($this->tag) {
        $rc = $this->system($this->scm->activate_tag($this->tag));
        if ($rc['rc']) {
          throw new Exception('Error switching to tag \'' . $this->tag . '\'');
        }
      }
      $rc = $rc['rc'];
    }
    else {
      throw new Exception('Error rollout from scm: static is not supported!');
    }
  }
}
