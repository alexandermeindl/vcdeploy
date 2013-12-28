<?php
/**
 * @file
 *   DB base class
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

class VcdeployDb {

  /**
   * Configuration
   *
   * @var array
   */
  protected $conf;

  /**
   * Current project settings
   *
   * @var array
   */
  protected $project;

  /**
   * Constructor
   *
   * @param array $conf
   * @param array $project
   *
   * @return void
   */
  public function __construct($conf, $project = NULL) {
    $this->conf = $conf;
    if (isset($project)) {
      $this->project = $project;
    }
  }

  /**
   * Get userfriendly name of SCM
   */
  public function get_name() {
    throw new Exception('get_name is not implement of the current DB type');
  }

  /**
   * Get list of all available databases
   *
   * @return string
   */
  public function get_db_list() {
    throw new Exception('get_db_list is not implement of the current DB type');
  }

  /**
   * Get database dump
   *
   * @param string $db_name
   * @param string $target_file
   *
   * @return string
   */
  public function get_dump($db_name, $target_file) {
    throw new Exception('get_dump is not implement of the current DB type');
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
    throw new Exception('get_restore is not implement of the current DB type');
  }

  /**
   * Get database exists command
   *
   * @param string $db_name
   *
   * @return string
   */
  public function get_db_exists($db_name) {
    throw new Exception('get_db_exists is not implement of the current DB type');
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
    throw new Exception('get_db_create is not implement of the current DB type');
  }

  /**
   * Get database drop command
   *
   * @param string $db_name
   *
   * @return string
   */
  public function get_db_drop($db_name) {
    throw new Exception('get_db_drop is not implement of the current DB type');
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
    throw new Exception('get_table_drop is not implement of the current DB type');
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
    throw new Exception('get_table_truncate is not implement of the current DB type');
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
    throw new Exception('get_query is not implement of the current DB type');
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
    throw new Exception('get_user_create is not implement of the current DB type');
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
    throw new Exception('get_user_drop is not implement of the current DB type');
  }
}
