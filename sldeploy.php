#!/usr/bin/php
<?php
/**
 * @file
 *   Executable script sldeploy
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

error_reporting(E_ALL);


if (!defined('__DIR__')) {
  $ipos = strrpos(__FILE__, '/');
  define('__DIR__', substr(__FILE__, 0, $ipos) . '/');
  unset($ipos);
}

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/lib');

require 'Log.php';
require 'sldeploy/loader.class.php';
require 'sldeploy/exception_handler.inc.php';
require 'sldeploy/config_default.inc.php';

if (file_exists('config.inc.php')) {
  $conf['config_file'] = chdir() . '/config.inc.php';
}
elseif (file_exists($_SERVER['HOME'] . '/.sldeploy.inc.php')) {
  $conf['config_file'] = $_SERVER['HOME'] . '/.sldeploy.inc.php';
}
elseif (file_exists('/etc/sldeploy.inc.php')) {
  $conf['config_file'] = '/etc/sldeploy.inc.php';
}
else {
  die("Configuration file config.inc.php missing!\n");
}

require $conf['config_file'];

// set exception handler
$logger = Log::factory('file', $conf['log_file'], basename(__FILE__));
set_exception_handler('exceptionHandler');

$sldeploy = new SlDeployLoader($conf);

exit($sldeploy->parser());
