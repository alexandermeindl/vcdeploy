<?php
/**
 * @file
 *   SCM bazaar implementation
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

class VcdeployScmBzr extends VcdeployScm {

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
    return 'Bazaar';
  }

  /**
   * Get update command
   *
   */
  public function update() {
    return $this->conf['bzr_bin'] . ' pull';
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

    if (!is_array($files)) {
      throw new Exception('commit error: files has to be an array');
    }

    return $this->conf['bzr_bin'] . ' commit -m "' . $message . '" ' . implode(' ', $files);
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

    if (!isset($this->project['scm']['url'])) {
      throw new Exception('bzr url not defined (scm->url)');
    }

    $command = $this->conf['bzr_bin'] . ' clone ' . $this->project['scm']['url'];
    if (isset($directory)) {
      $command .= ' ' . $directory;
    }

    return $command;
  }

  /**
   * Get 'activate tag' command
   *
   * @param string $tag
   *
   * @return string
   */
  public function activate_tag($tag) {
    return $this->conf['bzr_bin'] . ' switch ' . $tag;
  }

  /**
   * Get 'set tag' command
   *
   * @param string $tag
   *
   * @return string
   */
  public function set_tag($tag) {
    return $this->conf['bzr_bin'] . ' tag ' . $tag;
  }

  /**
   * Get 'remove tag' command
   *
   * @param string $tag
   *
   * @return string
   */
  public function remove_tag($tag) {
    return $this->conf['bzr_bin'] . ' tag --delete ' . $tag;
  }

  /**
  * Get list of tags command
  *
  * @return string
  */
  public function get_tags() {
    return $this->conf['bzr_bin'] . ' tag -l';
  }
}