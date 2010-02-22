<?php
/**
 * @file
 *   Run other plugins as a batch
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
 */

$plugin['info']       = 'Run update-project, update-system, update-drupal and permission plugins';
$plugin['root_only']  = FALSE;

class sldeploy_plugin_update_all extends sldeploy {

  public function run_batch() {
    return array('update-project', 'update-system', 'update-drupal', 'permission');
  }
}