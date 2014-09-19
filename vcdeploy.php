#!/usr/bin/php
<?php
/**
 * Executable script vcdeploy
 *
 * PHP version 5.3
 *
 * @category  Console
 * @package   Vcdeploy
 * @author    Alexander Meindl <a.meindl@alphanodes.com>
 * @copyright 2014 Alexander Meindl
 * @license   http://www.mozilla.org/MPL Mozilla Public License Version 1.1
 * @link      https://github.com/alexandermeindl/vcdeploy
 */

error_reporting(E_ALL);


if (!defined('__DIR__')) {
    $ipos = strrpos(__FILE__, '/');
    define('__DIR__', substr(__FILE__, 0, $ipos) . '/');
    unset($ipos);
}

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/lib');

require 'vendor/autoload.php';
#require 'Log.php';
require 'vcdeploy/loader.class.php';
require 'vcdeploy/exception_handler.inc.php';

// load configuration
$conf = array();
require 'vcdeploy/config_default.inc.php';
require 'vcdeploy/load_config.php';

// set exception handler
$logger = Log::factory('file', $conf['log_file'], basename(__FILE__));
set_exception_handler('exceptionHandler');

$vcdeploy = new VcDeployLoader($conf);

exit($vcdeploy->parser());
