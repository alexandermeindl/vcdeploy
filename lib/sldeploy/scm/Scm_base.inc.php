<?php
/**
 * @file
 *   SCM base class
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

class SldeployScm {

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
    throw new Exception('get_name is not implement of the current SCM');
  }

  /**
   * Get update command
   */
  public function update() {
    throw new Exception('\'update\' is not implement of the current SCM');
  }

  /**
   * Get commit command
   *
   * @param string $message
   * @param array $files
   *
   * @throws Exception
   * @return string
   */
  public function commit($message, $files) {
    throw new Exception('\'commit\' is not implement of the current SCM');
  }

  /**
   * Get checkout command
   *
   * @param string $directory
   *
   * @throws Exception
   * @return string
   */
  public function checkout($directory = NULL) {
    throw new Exception('\'checkout\' is not implement of the current SCM');
  }

  /**
   * Get 'activate tag' command
   *
   * @param string $tag
   *
   * @return string
   */
  public function activate_tag($tag) {
    throw new Exception('\'activate tag\' is not implement of the current SCM');
  }

  /**
   * Get 'set tag' command
   *
   * @param string $tag
   *
   * @return string
   */
  public function set_tag($tag) {
    throw new Exception('\'set tag\' is not implement of the current SCM');
  }

  /**
   * Get 'remove tag' command
   *
   * @param string $tag
   *
   * @return string
   */
  public function remove_tag($tag) {
    throw new Exception('\'remove tag\' is not implement of the current SCM');
  }
}