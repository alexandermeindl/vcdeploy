<?php
/**
 * Exception handler
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

/**
 * Exception handler
 *
 * @param object $exception
 */
function exceptionHandler($exception)
{
    global $logger;

    echo $exception->getMessage() . "\n";
    $logger->log($exception->getMessage(), PEAR_LOG_ALERT);
    exit(1);
}
