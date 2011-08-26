<?php
/**
 * @file
 *   SCM static implementation
 *
 *   This class is used as dummy SCM
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

require_once 'Scm_base.inc.php';

class VcdeployScmStatic extends VcdeployScm {

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
   * Get userfriendly name of SCM
   */
  public function get_name() {
    return 'Static';
  }

  /**
   * Get update command
   *
   */
  public function update() {
    return FALSE;
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
    return FALSE;
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
    return FALSE;
  }

  /**
   * Get 'activate tag' command
   *
   * @param string $tag
   *
   * @return string
   */
  public function activate_tag($tag) {
    return FALSE;
  }

  /**
   * Get 'set tag' command
   *
   * @param string $tag
   *
   * @return string
   */
  public function set_tag($tag) {
    return FALSE;
  }

  /**
   * Get 'remove tag' command
   *
   * @param string $tag
   *
   * @return string
   */
  public function remove_tag($tag) {
    return FALSE;
  }

  /**
  * Get list of tags command
  *
  * @return string
  */
  public function get_tags() {
    return FALSE;
  }
}
