<?php

require_once 'Scm_base.inc.php';

class SldeployScmGit extends SldeployScm {

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
   *
   * @return string
   */
  public function get_name() {
    return 'Git';
  }

  /**
   * Get checkout command
   *
   * @return string
   */
  public function update() {
    return $this->conf['git_bin'] . ' pull';
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

    return $this->conf['git_bin'] . ' commit -m "' . $message . '" ' . implode(' ', $files);
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
      throw new Exception('git url not defined (scm->url)');
    }

    $command = $this->conf['git_bin'] . ' clone ' . $this->project['scm']['url'];
    if (isset($directory)) {
      $command .= ' ' . $directory;
    }

    return $command;
  }

  /**
   * Get 'set tag' command
   *
   * @return string
   */
  public function set_tag($tag) {
    return $this->conf['git_bin'] . ' tag ' . $tag;
  }

  /**
   * Get 'remove tag' command
   *
   * @return string
   */
  public function remove_tag($tag) {
    return $this->conf['git_bin'] . ' tag -d '. $tag;
  }
}