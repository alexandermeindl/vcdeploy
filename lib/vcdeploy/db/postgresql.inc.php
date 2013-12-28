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
}
