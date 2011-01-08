<?php

require_once 'Scm_base.inc.php';

class SldeployScmStatic extends SldeployScm {

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
    return 'Static';
  }

  /**
   * Get update command
   *
   */
  public function update() {
    return FALSE;
  }

  /**
   * Get commit command
   *
   * @param string $message
   * @param array $files
   * @throws Exception
   * @return string
   */
  public function commit($message, $files) {
    return FALSE;
  }

  /**
   * Get checkout command
   *
   * @param string $directory
   * @throws Exception
   * @return string
   */
  public function checkout($directory = NULL) {
    return FALSE;
  }
}