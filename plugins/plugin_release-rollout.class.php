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
 * @package  sldeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/sldeploy
 */

$plugin['info'] = 'rollout a new release';
$plugin['root_only'] = TRUE;

$plugin['args']['project'] = 'Project to rollout';

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

class SldeployPluginReleaseRollout extends Sldeploy {

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
   * @see sldeploy#run()
   */
  public function run() {

    // check for existing projects
    $this->validate_projects();

    if (isset($this->paras->command->args['project']) && !empty($this->paras->command->args['project'])) {

      $project_name = $this->paras->command->args['project'];

      if (!array_key_exists($project_name, $this->projects)) {
        throw new Exception('Project "' . $project_name . '" is not configured!');
      }
      $this->set_project($project_name, $this->projects[$project_name]);
    }
    else {
      throw new Exception('No project specified');
    }

    if (isset($this->paras->command->options['tag']) && !empty($this->paras->command->options['tag'])) {
      $this->tag = $this->paras->command->options['tag'];
    }
    elseif (isset($this->project['rollout']['tag'])) {
      $this->tag = $this->project['rollout']['tag'];
    }
    else {
      throw new Exception('No release TAG specified.');
    }

    if (!isset($this->project['path'])) {
      throw new Exception('Project path is required for rollout!');
    }

    // initialize scm
    $this->set_scm('project');

    return $this->_projectRollout();
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
    return $init++;
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

    // backup everything
    $this->_backup();

    // 1. project code
    if (isset($this->project['rollout']['with_project_archive'])
      && $this->project['rollout']['with_project_archive']
      && isset($this->project['rollout']['with_project_scm'])
      && $this->project['rollout']['with_project_scm']
    ) {
      throw new Exception('You cannot use with_project_archive and with_project_scm togther!');
    }
    elseif (isset($this->project['rollout']['with_project_archive']) && $this->project['rollout']['with_project_archive']) {
      $rc = $this->_codeRollout();
    }
    elseif (isset($this->project['rollout']['with_project_scm']) && $this->project['rollout']['with_project_scm']) {
      // update scm project code
      $rc = $this->system($this->scm->update());
      if ($rc['rc']) {
        throw new Exception('Error on updating project code from SCM');
      }
    }

    // 2. databases
    if ((isset($this->paras->command->options['with_db']) && ($this->paras->command->options['with_db']))
      || (isset($this->project['rollout']['with_db']) && $this->project['rollout']['with_db'])
    ) {
      if (!isset($this->paras->command->options['without_db']) || (!$this->paras->command->options['without_db'])) {
        $rc = $this->_dbRollout();
      }
    }

    // 3. data (files/directories)
    if ((isset($this->paras->command->options['with_data']) && ($this->paras->command->options['with_data']))
      || (isset($this->project['rollout']['with_data']) && $this->project['rollout']['with_data'])
    ) {
      if (!isset($this->paras->command->options['without_data']) || (!$this->paras->command->options['without_data'])) {
        $rc = $this->_dataRollout();
      }
    }

    // 4. run post commands
    if (isset($this->project['rollout']['post_commands'])) {
      $this->post_commands($this->project['rollout']['post_commands']);
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

    // TODO: activate it again (it took to long)
    // $this->create_data_dump($this->project['path'], $target_file, $this->project['data_dir']);
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

      // drop database
      $this->system($this->conf['mysqladmin_bin'] . ' -f drop ' . $db);

      $this->msg('Recreating database ' . $db . '...');
      sleep(2);

      $rc = $this->system($this->conf['mysqladmin_bin'] . ' create ' . $db);
      if ($rc['rc']) {
        throw new Exception('Error creating database \'' . $db . '\'!');
      }

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
      $this->system($this->conf['tar_bin'] . ' xfz ' . $tar_file);

    }

    return 0;
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
}
