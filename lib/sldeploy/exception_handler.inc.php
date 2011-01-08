<?php

/**
 * Exception handler
 *
 * @param object $exception
 */
function exceptionHandler($exception) {

  global $logger;

  echo $exception->getMessage() . "\n";
  $logger->log($exception->getMessage(), PEAR_LOG_ALERT);
  exit(1);
}
