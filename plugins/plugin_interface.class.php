<?php
/**
 * @file
 *   Interface for Plugins
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
 * @package  sldeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/sldeploy
 */


interface ISldeployPlugin {

  /**
   * This function is run with the command
   *
   * @return int if no error occurred, return 0
   * @see SlDeployLoader#run_plugin()
   */
  public function run();

  /**
   * Get max steps of this plugin for progress view
   *
   * @param int $init initial value of counter
   *
   * @return int amount of working steps of this plugin
   * @see Sldeploy#progressbar_init()
   */
  public function get_steps($init = 0);
}