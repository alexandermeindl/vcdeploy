<?php

require_once 'Scm_base.inc.php';

class SldeployScmBzr extends SldeployScm {

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
    return 'Bazaar';
  }

  /**
   * Get update command
   *
   */
  public function update() {
    return $this->conf['bzr_bin'] . ' pull';
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

    if (!is_array($files)) {
      throw new Exception('commit error: files has to be an array');
    }

    return $this->conf['bzr_bin'] . ' commit -m "' . $message . '" ' . implode(' ', $files);
  }

  /**
   * Get checkout command
   *
   * @param string $directory
   * @throws Exception
   * @return string
   */
  public function checkout($directory = NULL) {

    if (!isset($this->project['scm']['url'])) {
      throw new Exception('bzr url not defined (scm->url)');
    }

    $command = $this->conf['bzr_bin'] . ' clone ' . $this->project['scm']['url'];
    if (isset($directory)) {
      $command .= ' ' . $directory;
    }

    return $command;
  }
}