<?php
/**
 * @file
 *   DB MySQL implementation
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

class VcdeployDbMysql extends VcdeployDb {

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
   * Get userfriendly name of MySQL
   *
   * @return string
   */
  public function get_name() {
    return 'mysql';
  }

  /**
   * Get list of all available databases
   *
   * @return string
   */
  public function get_db_list() {
    return $this->conf['mysql_bin'] . " -Bse 'show databases'";
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
    return $this->conf['mysqldump_bin'] . ' ' . $this->conf['mysqldump_options'] . ' ' . $db_name . '>' . $filename;
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
      return $this->conf['gunzip_bin'] . ' < ' . $filename . ' | ' . $this->conf['mysql_bin'] . ' ' . $db_name;
    }
    else {
      return $this->conf['mysql_bin'] . ' ' . $db_name . ' < ' . $filename;
    }
  }

  /**
   * Get database exists command
   *
   * @param string $db_name
   *
   * @return string
   */
  public function get_db_exists($db_name) {
    return $this->conf['mysql_bin'] . " -Be \"SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . $db_name . "'\"";
  }

  /**
   * Get database create command
   *
   * @param string $db_name
   *
   * @return string
   */
  public function get_db_create($db_name) {
    return $this->conf['mysql_bin'] . ' -Be "CREATE DATABASE ' . $db_name . '"';
  }

  /**
   * Get database drop command
   *
   * @param string $db_name
   *
   * @return string
   */
  public function get_db_drop($db_name) {
    return $this->conf['mysql_bin'] . ' -Be "DROP DATABASE IF EXISTS ' . $db_name . '"';
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
    return $this->conf['mysql_bin'] . ' ' . $db_name . ' -e "DROP TABLE IF EXISTS ' . $table . '"';
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
    return $this->conf['mysql_bin'] . ' ' . $db_name . ' -e "TRUNCATE TABLE ' . $table . '"';
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
    return $this->conf['mysql_bin'] . ' ' . $db_name . ' -e "' . $sql . '"';
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
    return $this->conf['mysql_bin'] . ' -e "GRANT ALL PRIVILEGES ON ' . $db_name . '.* TO \'' . $user . '\'@\'' . $db_host. '\' IDENTIFIED BY \'' . $password. '\' WITH GRANT OPTION"';
  }

  /**
   * Get user drop command
   *
   * @param string $db_host
   * @param string $db_name
   * @param string $user
   *
   * @return string
   */
  public function get_user_drop($db_host, $db_name, $user) {
    return $this->conf['mysql_bin'] . ' mysql -e "DROP USER \'' . $user .'\'@\'' . $db_host .'\'"';
  }
}
