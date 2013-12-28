<?php
/**
 * @file
 *   DB PostgreSQL implementation
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

require_once 'Db_base.inc.php';

class VcdeployDbPostgresql extends VcdeployDb {

  const priv_user = 'postgres';

  /**
   * Constructor
   *
   * @param array $conf
   * @param array $project
   */
  public function __construct($conf, $project = NULL) {
    parent::__construct($conf, $project);
  }

  /**
   * Get userfriendly name of PostgreSQL
   *
   * @return string
   */
  public function get_name() {
    return 'postgresql';
  }

  /**
   * Run as privileged user
   *
   */
  protected function run_priv($command) {
    return 'su - -c "' . $command . '" ' . self::priv_user;
  }

  /**
   * Get list of all available databases
   *
   * @return string
   */
  public function get_db_list() {
    return $this->run_priv($this->conf['psql_bin']  . ' -l -t | cut -d\'|\' -f1 | sed -e \'s/ //g\' -e \'/^\$/d\' | grep -vE \'^-|^List|^Name|template[0|1]\'');
  }

  /**
   * Get database dump command
   *
   * @param string $db_name
   * @param string $filename
   *
   * @return string
   */
  public function get_dump($db_name, $filename) {
    return $this->run_priv($this->conf['pg_dump_bin'] . ' ' . $this->conf['postgresqldump_options'] . ' ' . $db_name . '>' . $filename);
  }

  /**
   * Get database restore command
   *
   * @param string $db_name
   * @param string $filename
   * @param bool $with_uncompress
   *
   * @return string
   */
  public function get_restore($db_name, $filename, $with_uncompress=false) {
    if ($with_uncompress) {
      $command = $this->conf['gunzip_bin'] . ' < ' . $filename . ' | ' . $this->conf['psql_bin'] . ' -d ' . $db_name;
    }
    else {
      $command = $this->conf['psql_bin'] . ' ' . $db_name . ' < ' . $filename;
    }
    return $this->run_priv($command);
  }

  /**
   * Get database exists command
   *
   * @param string $db_name
   *
   * @return string
   */
  public function get_db_exists($db_name) {
    return $this->run_priv($this->conf['psql_bin'] . ' -l | grep ' . $db_name . ' | wc -l');
  }

  /**
   * Get database create command
   *
   * @param string $db_name
   * @param string $owner
   *
   * @return string
   */
  public function get_db_create($db_name, $owner='') {
    return $this->run_priv($this->conf['psql_bin'] . ' -c "create database \'' . $db_name . '\' with owner \'' . $owner . '\' encoding=\'utf8\' template template0"');
  }

  /**
   * Get database drop command
   *
   * @param string $db_name
   *
   * @return string
   */
  public function get_db_drop($db_name) {
    return $this->run_priv($this->conf['dropdb_bin'] . ' ' . $db_name);
  }

  /**
   * Get table drop command
   *
   * @param string $db_name
   * @param string $table
   *
   * @return string
   */
  public function get_table_drop($db_name, $table) {
    return $this->run_priv($this->conf['psql_bin'] . ' -c "DROP TABLE IF EXISTS ' . $table . '"' . ' ' . $db_name);
  }

  /**
   * Get table truncate command
   *
   * @param string $db_name
   * @param string $table
   *
   * @return string
   */
  public function get_table_truncate($db_name, $table) {
    return $this->run_priv($this->conf['psql_bin'] . ' -c "TRUNCATE ' . $table . '" ' . $db_name);
  }

  /**
   * Get SQL query command
   *
   * @param string $db_name
   * @param string $sql
   *
   * @return string
   */
  public function get_query($db_name, $sql) {
    return $this->run_priv($this->conf['psql_bin'] . ' -c "' . $sql . '" ' . $db_name);
  }

  /**
   * Get user create command
   *
   * @param string $db_host
   * @param string $db_name
   * @param string $user
   * @param string $password
   *
   * @return string
   */
  public function get_user_create($db_host, $db_name, $user, $password) {
    return $this->run_priv($this->conf['psql_bin'] . ' -e "GRANT ALL PRIVILEGES ON ' . $db_name . ' to \'' . $user . '\'@\'' . $db_host. '\' IDENTIFIED BY \'' . $password. '\' WITH GRANT OPTION"');
  }

  /**
   * Get user drop command
   *
   * @param string $db_host
   * @param string $user
   *
   * @return string
   */
  public function get_user_drop($db_host, $user) {
      return $this->run_priv($this->conf['psql_bin'] . ' -c "DROP ROLE IF EXISTS ' . $user . '"');
  }
}
