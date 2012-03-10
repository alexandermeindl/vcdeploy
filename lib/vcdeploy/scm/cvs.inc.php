<?php
/**
 * @file
 *   SCM cvs implementation
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

class VcdeployScmCvs extends VcdeployScm {

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
    return 'CVS';
  }

  /**
   * Get update command
   *
   */
  public function update() {
    return $this->conf['cvs_bin'] . ' update';
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

    return $this->conf['cvs_bin'] . ' commit -m "' . $message . '" ' . implode(' ', $files);
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
      throw new Exception('cvs url not defined (scm->url)');
    }

    $command = $this->conf['cvs_bin'] . ' checkout ' . $this->project['scm']['url'];
    if (isset($directory)) {
      $command .= ' ' . $directory;
    }

    return $command;
  }
}