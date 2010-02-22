<?php
/**
 * @file
 *   Main class of sl-deploy script
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
 */

class sldeploy {

  /**
   * Version of sldeploy
   *
   * @var int
   */
  private $version = '0.10';

  /**
   * Base directory
   *
   * @var string
   */
  public $base_dir;

  /**
   * Plugin directory
   *
   * @var string
   */
  public $plugin_dir;

  /**
   * Configuration
   *
   * @var array
   */
  public $conf;

  /**
   * Debug mode
   *
   * @var bool
   */
  public $debug = false;

  /**
   * Hostname of script server
   *
   * @var string
   */
  protected $hostname;

  /**
   * Active plugin
   *
   * (see $this->check_paras for available plugins)
   *
   * @var string
   */
  protected $plugin_name;

  /**
   * User name for SSH connection
   *
   * @required for ssh_system
   * @var string
   */
  public $ssh_user;

  /**
   * Server name for SSH connection
   *
   * @required for ssh_system
   * @var string
   */
  public $ssh_server;

  /**
   * A list of all available plugins
   *
   * @var array
   */
  public $plugins;

  /**
   * A list of all active projects
   *
   * All projects specified in the configuration file with
   * active = TRUE
   *
   * @var array
   */
  public $projects;

  /**
   * Current project name
   *
   * Project name is the project array key in configuration file
   *
   * @var string
   */
  public $project_name;

  /**
   * This is the indicator for the common project key
   *
   * @var string
   */
  protected $common_project_name = '_common_';

  /**
   * Current project settings
   *
   * @var array
   */
  public $project;

  /**
   *
   * @var string
   */
  public $date_stamp;

  /**
   * User, which execute sldeploy
   *
   * @var string
   */
  public $current_user;

  public function __construct($conf, $write_to_log=FALSE) {

    $this->conf         = $conf;
    $this->hostname     = $this->get_hostname();
    $this->base_dir     = dirname($_SERVER['SCRIPT_NAME']);
    $this->plugin_dir   = $this->base_dir .'/plugins';

    $this->date_stamp   = date('YmdHi');

    $rc        = $this->system('whoami');
    $this->current_user = $rc['output'][0];

    $paras              = getopt('p:rvqjh');

    if (isset($this->conf['debug']) && $this->conf['debug'] ) {
      $this->debug = true;
    }

    if (($this->conf['write_to_log']) && (!$write_to_log)) {
      $this->conf['write_to_log'] = FALSE;
    }

    if ($this->check_paras($paras)) {
      if (array_key_exists('h', $paras)) {
        $this->help();
        exit(0);
      }
      else {
        $this->plugin_name = $paras['p'];
        if (array_key_exists('r', $paras)) $this->with_report = TRUE;
        if (array_key_exists('v', $paras)) $this->debug       = TRUE;
        if (array_key_exists('q', $paras)) $this->quiet       = TRUE;
      }
    }
    else {
      $this->msg('wrong usage');
      $this->help();
      exit(1);
    }
  }

  /**
   * Print script help to console
   */
  public function help() {

    $this->msg('sldeploy '. $this->version);
    $this->msg("\nUsage: -p PLUGIN [OPTION]\n");
    $this->msg("PLUGIN is required and has to be one of the following values:");

    $plugins = $this->get_plugin_info();
    foreach ($plugins AS $plugin_name => $plugin_info) {
      $this->msg($plugin_name ."\t". $plugin_info);
    }

    $this->msg("\nList of optional parameters:");
    $this->msg("-h\tdisplay this help");
    $this->msg("-q\tsuppress messages (expect error message)");
    $this->msg("-r\tsend status report (e.g. email)");
  }


  private function get_plugin_info() {

    $plugins = array();

    foreach($this->plugins AS $plugin_name) {
      $plugin['info'] = '';
      require_once $this->plugin_dir .'/plugin_'. $plugin_name .'.class.php';

      if ($this->check_plugin_permission($plugin['root_only'])) {
        if (!empty($plugin['info'])) {
          $plugins[$plugin_name] = $plugin['info'];
        }
        else {
          $plugins[$plugin_name] = 'No plugin info defined.';
        }
      }
    }

    return $plugins;
  }

  /**
    * Get all available modules
    *
    */
  private function set_plugins() {

    $this->plugins = array();

    $d = dir($this->plugin_dir);
    while (false !== ($entry = $d->read())) {
      if ((substr($entry, 0, 7) == 'plugin_') && (substr($entry, -10) == '.class.php')) {
        $this->plugins[] = substr($entry, 7, -10);
      }
    }
    $d->close();
  }

  /**
   * Check parameters, if there are valid
   *
   * m (required) = modules
   *
   * @param   array   $paras
   * @return  bool    true, if parameters are valid
   */
  private function check_paras($paras) {

    $this->set_plugins();

    if (!is_array($paras))                        return;
    if (array_key_exists('h', $paras))            return true;
    if ((!array_key_exists('p', $paras)) ||
        (!in_array($paras['p'], $this->plugins))) return;

/*
      switch ($paras['p']) {
          case 'build':
              if (!array_key_exists('C', $paras)) return;
              break;
      }
*/
      return TRUE;
  }

  /**
   * Set current project to
   *
   * $this->project_name
   * $this->project
   *
   */
  public function set_project($project_name, $project) {
    $this->project_name = $project_name;
    $this->project      = $project;

    if (isset($this->project['ssh_user'])) {
      $this->ssh_user = $this->project['ssh_user'];
    }
    if (isset($this->project['ssh_server'])) {
      $this->ssh_server = $this->project['ssh_server'];
    }
  }

  /**
   * Set active projects to $this->projects
   *
   */
  public function set_projects() {
    global $project;

    $this->projects = array();

    if (is_array($project) && count($project)) {

      foreach($project AS $project_name => $project_settings) {
        if (($project_name!=$this->common_project_name) && ($project_settings['active'])) {

          // set default configuration, if available
          if (isset($project[$this->common_project_name])) {
            // set project configuration
            $this->projects[$project_name] = array_merge($project[$this->common_project_name], $project_settings);
          }
          else {
            // set project configuration
            $this->projects[$project_name] = $project_settings;
          }

          // set command configuration, if available
          if (isset($this->projects[$project_name][$this->plugin_name])) {
            $this->projects[$project_name] = array_merge($this->projects[$project_name], $this->projects[$project_name][$this->plugin_name]);
            unset($this->projects[$project_name][$this->plugin_name]);
          }

          if (!isset($this->projects[$project_name]['remote_tmp_dir'])) {
            $this->projects[$project_name]['remote_tmp_dir'] = $this->conf['tmp_dir'];
          }
        }
      }
    }
  }

  /**
   * Run specified plugin
   *
   * @return  int - return code of plugin
   */
  public function run() {

    require_once $this->plugin_dir .'/plugin_'. $this->plugin_name .'.class.php';

    $c = 'sldeploy_plugin_'. str_replace('-', '_', $this->plugin_name);
    $app = new $c($this->conf, TRUE);

    $app->set_projects();

    // if "run_batch" exists, use this instead of "run"
    if (method_exists($app, 'run_batch')) {
      $this->msg('Batch mode');

      $plugins = $app->run_batch();
      foreach ($plugins AS $plugin_name) {

        if ($plugin_name==$this->plugin_name) {
          $this->msg('run_batch misconfiguration. batch mode itself can not be a child.', 1);
        }

        $this->msg('Run '. $plugin_name .' at '. $this->hostname .'...');

        require_once $this->plugin_dir .'/plugin_'. $plugin_name .'.class.php';

        // make sure, we are in base directory (possible change in a plugin)
        chdir($this->base_dir);

        $c = 'sldeploy_plugin_'. str_replace('-', '_', $plugin_name);
        $app = new $c($this->conf, TRUE);

        if (!$this->check_plugin_permission($plugin['root_only'])) {
          $this->msg('Only root can run this plugin', 2);
        }

        $rc = $app->run();
        if ($rc) {
          $this->msg('An error occured (rc='. $rc .')');
        }
      }

      if (!$rc) {
        $this->msg('Deploy finished.');
      }
    }
    else {

      if (!$this->check_plugin_permission($plugin['root_only'])) {
        $this->msg('Only root can run this plugin', 2);
      }

      $this->msg('Run '. $this->plugin_name .' at '. $this->hostname .'...');

      $rc = $app->run();
      if ($rc) {
        $this->msg('An error occured (rc='. $rc .')');
      }
      else {
        $this->msg('Deploy finished.');
      }
    }

    return $rc;
  }

  /**
   * Get hostname
   * @return  string
   */
  protected function get_hostname() {
    $hi = $this->system('hostname');
    return $hi['output'][0];
  }

  /**
   * Execute system call
   *
   * @param   string  $command    - command to execute
   * @return  string              - command output
   */
  public function system($command, $passthru=FALSE) {
    if ($this->debug) {
      $this->msg('system: '. $command);
    }

    if ($passthru) {
      passthru($command, $rc);
      $output = '';
    }
    else {
      exec($command, $output, $rc);
    }

    return array('output' => $output, 'rc' => $rc);
  }

  /**
   * Print message to console
   */
  public function msg($msg, $error_code=0) {
    echo $msg ."\n";
    if ($this->conf['write_to_log']) {
      @file_put_contents($this->conf['log_file'], date('c'). ' '. $msg ."\n", FILE_APPEND);
    }
    if ($error_code!==0) {
      exit($error_code);
    }
  }

  public function check_plugin_permission($root_only) {

    $rc = TRUE;

    if ($root_only) {

      if ($this->current_user != 'root') {
        $rc = FALSE;
      }
    }

    return $rc;
  }

  /**
   * Run this post commands after plugin action (if supported by plugin)
   *
   * @return bool
   */
  public function post_commands($post_commands) {

    if (is_array($post_commands)) {

      foreach ($post_commands AS $command) {

        if (isset($this->project['drush'])) {
          $command = str_replace('[drush]', $this->project['drush'], $command);
        }

        $this->msg('Running post command: '. $command);
        $this->system($command);
      }
    }
  }

  public function ssh_check() {
    if (empty($this->ssh_user)) {
      $this->msg('ssh_system error: ssh_user is missing', 2);
    }
    elseif (empty($this->ssh_server)) {
      $this->msg('ssh_system error: ssh_server is missing', 3);
    }
  }

  /**
   * Get remote file with ssh
   *
   * @param string  $remote_file
   * @param string  $local_file
   */
  public function ssh_get_file($remote_file, $local_file) {

    $this->ssh_check();
    $remote_file = $this->ssh_user. '@'. $this->ssh_server .':'. $remote_file;

    $this->msg('Transfer file...');
    $rc = $this->system($this->conf['scp_bin'] .' '. $remote_file . ' '. $local_file, TRUE);
    if ($rc['rc']) {
      $this->msg('File could not be transfered. ('. $remote_file .')', 5);
    }
  }

  /**
   * Tranfer file to remote server with ssh
   *
   * @param string  $local_file
   * @param string  $remote_file
   */
  public function ssh_put_file($local_file, $remote_file) {

    $this->ssh_check();
    $remote_file = $this->ssh_user. '@'. $this->ssh_server .':'. $remote_file;

    $this->msg('Transfer file...');
    $rc = $this->system($this->conf['scp_bin'] .' '. $local_file . ' '. $remote_file, TRUE);
    if ($rc['rc']) {
      $this->msg('File could not be transfered. ('. $local_file .')', 5);
    }
  }

  /**
   * Execute system call over SSH
   *
   * @require $this->ssh_user
   * @require $this->ssh_server
   *
   * @param   string  $command  - command to execute
   * @return  string            - command output
   */
  public function ssh_system($command, $passthru=FALSE) {

    $this->ssh_check();

    $ssh_command    = $this->conf['ssh_bin'] .' '. $this->ssh_user .'@'. $this->ssh_server;

    return $this->system($ssh_command .' "'. $command .'"', $passthru);
  }
}
