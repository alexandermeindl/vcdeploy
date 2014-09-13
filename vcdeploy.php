#!/usr/bin/php
<?php
/**
 * @file
 *   Executable script vcdeploy
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
 * @package  vcdeploy
 * @author  Alexander Meindl
 * @link    https://github.com/alexandermeindl/vcdeploy
 */

error_reporting(E_ALL);


if (!defined('__DIR__')) {
  $ipos = strrpos(__FILE__, '/');
  define('__DIR__', substr(__FILE__, 0, $ipos) . '/');
  unset($ipos);
}

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/lib');

require 'Log.php';
require 'vcdeploy/loader.class.php';
require 'vcdeploy/exception_handler.inc.php';
require 'vcdeploy/config_default.inc.php';

// load configuation files
require 'vcdeploy/load_config.php';

// set exception handler
$logger = Log::factory('file', $conf['log_file'], basename(__FILE__));
set_exception_handler('exceptionHandler');

$vcdeploy = new VcDeployLoader($conf);

exit($vcdeploy->parser());
