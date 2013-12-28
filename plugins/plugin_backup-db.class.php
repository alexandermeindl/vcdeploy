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
 * @package  vcdeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/vcdeploy
 */

$plugin['info'] = 'Create database backup. If no parameter is specfied all project database will be backuped';
$plugin['root_only'] = FALSE;

$plugin['options']['project'] = array(
                              'short_name'  => '-p',
                              'long_name'   => '--project',
                              'action'      => 'StoreString',
                              'description' => 'Create backup of this project database(s)',
);

$plugin['options']['database'] = array(
                              'short_name'  => '-d',
                              'long_name'   => '--database',
                              'action'      => 'StoreString',
                              'description' => 'Only create backup of this database',
                            );

$plugin['options']['all_databases'] = array(
                              'short_name'  => '-A',
                              'long_name'   => '--all-databases',
                              'action'      => 'StoreTrue',
                              'description' => 'Create backup of all existing databases',
                            );

class VcdeployPluginBackupDb extends Vcdeploy implements IVcdeployPlugin {

  /**
   * Databases for created backup
   *
   * @var array
   */
  private $_databases;

  /**
    * Excluded databases for all backup tables
    */
	private $_excluded_tables;

  /**
   * This function is run with the command
   *
   * @return int
   * @throws Exception
   * @see vcdeploy#run()
   */
  public function run() {

    // combination of parameters are forbitten
    if (isset($this->paras->command->options['project']) && isset($this->paras->command->options['database'])) {
      throw new Exception('You cannot use --project and --database together');
    }
    else if (isset($this->paras->command->options['database']) && isset($this->paras->command->options['all_databases'])) {
      throw new Exception('You cannot use --database and --all-database together');
    }
    else if (isset($this->paras->command->options['project']) && isset($this->paras->command->options['all_databases'])) {
      throw new Exception('You cannot use --project and --all-database together');
    }

    // check backup directory if exists and is writable
    $this->prepare_backup_dir();

    // initialize db
    $this->set_db();

    if (isset($this->paras->command->options['database']) && !empty($this->paras->command->options['database'])) {
      $this->_setDatabaseNames('db', $this->paras->command->options['database']);
    }
    else if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
      // check for existing projects
      $this->validate_projects();
      $this->_setDatabaseNames('project', $this->paras->command->options['project']);
    }
    else if (isset($this->paras->command->options['all_databases']) && ($this->paras->command->options['all_databases'])) {
      $this->_setDatabaseNames('dbs');
    }
    else {
      // check for existing projects
      $this->validate_projects();
      $this->_setDatabaseNames('projects');
    }

    if (count($this->_databases)) {
      $this->progressbar_init();
      $this->_createDbBackups();
      return 0;
    }
    else {
      return 1;
    }
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

    $db_count = count($this->_databases);

    // 2 times, because of gzip compression
    return $init + $db_count * 2;
  }

  /**
   * Set excluded tables
   */
  private function _setExcludedTables() {
    $this->_excluded_tables = array('performance_schema', 'information_schema');
  }

  /**
   * Create backups for all calculated databases
   *
   * @throws Exception
   */
  private function _createDbBackups() {

    $this->_setExcludedTables();

    foreach ($this->_databases AS $db_name) {
      if ($this->db_exists($db_name))  {
        if (!in_array($db_name, $this->_excluded_tables)) {
          $this->create_db_dump($db_name);
        }
      }
      else {
        throw new Exception('Database ' . $db_name . ' does not exist');
      }
    }
  }

  /**
   * Set database names for creating backup to $this->databases
   *
   * @param  string $mode
   * @param  string $name name of datbase or project (only used for mode project/db)
   * @throws Exception
   */
  private function _setDatabaseNames($mode, $name = null) {

    // Reset databases
    $this->_databases = array();

    switch ($mode) {
      case 'project':
        if (!array_key_exists($name, $this->projects)) {
          throw new Exception('Project "' . $name . '" is not configured!');
        }
        $this->set_project($name, $this->projects[$name]);
        // reinitialize db
        $this->set_db();
        $this->_databases = $this->project['db'];
        break;
      case 'projects':
        foreach ($this->projects AS $project_name => $project) {
          $this->set_project($project_name, $project);
          // reinitialize db
          $this->set_db();
          if (isset($this->project['db'])) {
            foreach($this->project['db'] AS $db_name) {
              $this->_databases[] = $db_name;
            }
          }
          else {
            $this->msg('Project ' . $this->project_name . ': no database has been specified.');
          }
        }
        break;
      case 'db':
        $this->_databases = array($name);
        break;
      case 'dbs':
        $rc = $this->system($this->db->get_db_list());
        if (!$rc['rc']) {
          $this->_databases = $rc['output'];
        }
        else {
          throw new Exception('Could not get list of available databases.');
        }
        break;
      default:
        throw new Exception('Unknown mode for _getDatabaseNames: ' . $mode);
    }
  }
}
