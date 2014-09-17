<?php
/**
 * Load configuration files
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

if (file_exists('config.inc.php')) {
    define('CONFIG_DIR', getcwd());
    $conf['config_file'] = CONFIG_DIR . '/config.inc.php';
}
elseif (file_exists($_SERVER['HOME'] . '/.vcdeploy.inc.php')) {
    define('CONFIG_DIR', $_SERVER['HOME']);
    $conf['config_file'] = CONFIG_DIR . '/.vcdeploy.inc.php';
}
elseif (file_exists('/etc/vcdeploy.inc.php')) {
    define('CONFIG_DIR', '/etc');
    $conf['config_file'] = CONFIG_DIR . '/vcdeploy.inc.php';
}
else {
    die("Configuration file config.inc.php/vcdeploy.in.php missing!\n");
}

require $conf['config_file'];

// Load additional configuation files
if (file_exists(CONFIG_DIR . '/vcdeploy.d') && is_dir(CONFIG_DIR . '/vcdeploy.d')) {
    if ($handle = opendir(CONFIG_DIR . '/vcdeploy.d')) {
        while (false !== ($file = readdir($handle))) {
            if (substr($file, -8) == '.inc.php') {
                include_once CONFIG_DIR . '/vcdeploy.d/' . $file;
            }
        }
        closedir($handle);
    }
}
