<?php
/**
 * @file
 *   Loader class of vcdeploy
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
 *
 */

require 'Console/CommandLine.php';
require 'Console/ProgressBar.php';
require 'vcdeploy/vcdeploy.class.php';

class VcDeployLoader {

  /**
   * Application version
   *
   * @var string
   */
  protected $version = '0.52';

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
  public $debug = FALSE;

  /**
   * Plugin directory
   *
   * @var string
   */
  public $plugin_dir;

  /**
   * Active plugin
   *
   * @var string
   */
  protected $plugin_name;

  /**
   * Plugin infos
   *
   * @var array
   */
  protected $plugin_infos;

  /**
   * A list of all available plugins
   *
   * @var array     key => name of plugin
   *                value => path to plugin
   *
   */
  public $plugins;

  /**
   * Constructor
   *
   * @param array $conf
   * @param bool $write_to_log
   *
   * @return void
   */
  public function __construct($conf) {

    global $logger;

    $this->conf = $conf;
    $this->hostname = $this->get_hostname();
    $this->base_dir = dirname(dirname(__DIR__));
    $this->plugin_dir = $this->base_dir . '/plugins';

    include_once $this->plugin_dir . '/plugin_interface.class.php';

    $this->logger = $logger;

    exec('whoami', $output);
    $this->current_user = $output[0];

    if (isset($this->conf['debug']) && $this->conf['debug'] ) {
      $this->debug = TRUE;
    }
  }

  /**
    * Get all available modules
    *
    * @return void
    */
  private function _setPlugins() {

    $this->plugins = array();

    $this->_setPluginFromDir($this->plugin_dir);

    // overwrite vcdeploy plugins with custom plugins
    if (isset($this->conf['custom_plugins'])) {

      if (substr($this->conf['custom_plugins'], 0, 1) == '/') {
        $custom_plugin_path = $this->conf['custom_plugins'];
      }
      else {
        $custom_plugin_path = $this->base_dir . '/' . $this->conf['custom_plugins'];
      }

      if (file_exists($custom_plugin_path)) {
        $this->_setPluginFromDir($custom_plugin_path);
      }
      else {
        throw new Exception('custom_plugins directory does not exist (' . $custom_plugin_path . ')');
      }
    }

    $this->plugin_infos = $this->_getPluginInfo();
  }

  /**
   * Set plugin info to $this->plugins
   *
   * @params string $dir
   * @see $this->_setPlugins()
   */
  private function _setPluginFromDir($dir) {

    $d = dir($dir);
    while (FALSE !== ($entry = $d->read())) {
      if ((substr($entry, 0, 7) == 'plugin_') && (substr($entry, -10) == '.class.php')) {
        if ($entry != 'plugin_interface.class.php') {
          $plugin_name = substr($entry, 7, -10);
          $this->plugins[$plugin_name] = $dir;
        }
      }
    }
    $d->close();
  }

  /**
   * Get plugin information
   *
   * @return array
   */
  private function _getPluginInfo() {

    $plugins = array();

    foreach ($this->plugins AS $plugin_name => $plugin_path) {
      unset($plugin);
      include_once $plugin_path . '/plugin_' . $plugin_name . '.class.php';
      if ($this->_checkPluginStatus($plugin)) {
        $plugins[$plugin_name] = $plugin;
        if (!isset($plugin['info'])) {
          $plugins[$plugin_name]['description'] = 'No plugin info defined.';
        }
      }
    }

    return $plugins;
  }

  /**
   * Check access for a plugin
   *
   * @param array $plugin
   * @return bool  TRUE, if status is enabled for execution
   */
  private function _checkPluginStatus(&$plugin) {

    $rc = TRUE;

    if (isset($plugin['disable']) && $plugin['disable']) {
      $rc = FALSE;
    }
    elseif (isset($plugin['root_only']) && !$this->check_plugin_permission($plugin['root_only'])) {
      $rc = FALSE;
    }

    if (isset($plugin['disable'])) {
      unset($plugin['disable']);
    }

    if (isset($plugin['root_only'])) {
      unset($plugin['root_only']);
    }

    return $rc;
  }

  /**
   * Get hostname
   *
   * @return  string
   */
  protected function get_hostname() {
    exec('hostname', $output);
    return $output[0];
  }

  /**
   * Check plugin permission
   *
   * @param bool $root_only
   *
   * @return bool
   */
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
   * Build commands for command line parser
   *
   * @param object $parser
   *
   * @return void
   */
  private function _buildSubCommands(&$parser) {

    foreach ($this->plugin_infos AS $name => $plugin) {

      unset($bar);
      $bar = $parser->addCommand($name, array('description' => $plugin['info']));

      if (isset($plugin['args']) && is_array($plugin['args'])) {
        foreach ($plugin['args'] AS $arg_name => $arg_description) {
          $bar->addArgument($arg_name, array('description' => $arg_description));
        }
      }

      if (isset($plugin['options']) && is_array($plugin['options'])) {
        foreach ($plugin['options'] AS $option_name => $option) {
          $bar->addOption($option_name, $option);
        }
      }
    }
  }

  /**
   * Check if script already running with same plugin
   *
   * @return bool
   */
  private function _checkAlreadyRunning() {

    $lock_file = $this->conf['tmp_dir'] . '/.vcdeploy_' . $this->current_user . '_' . $this->plugin_name . '.lck';

    if (file_exists($lock_file)) {
      exec('ps', $output);
      if (is_array($output)) {
        $cnt = 0;
        foreach ($output AS $process) {
          if (substr_count($process, 'vcdeploy ' . $this->plugin_name) > 0) {
            $cnt++;
            if ($cnt > 1) {
              return TRUE;
            }
          }
        }
        unlink($lock_file);
      }
    }

    touch($lock_file);
  }

  /**
   * Get class name from plugin name
   *
   * @param string $class_name
   *
   * @return string
   */
  private function _getPluginClass($plugin_name) {

    // convert plugin to class
    $words = ucwords(str_replace('-', ' ', $plugin_name));
    $name = str_replace(' ', '', $words);

    return 'VcdeployPlugin' . $name;
  }

  /**
   * Vcdeploy parser
   *
   * This method starts vcdeploy to run a plugin
   *
   * @return int
   */
  public function parser() {


    // create the parser
    $parser = new Console_CommandLine(array(
        'description' => 'vcdeploy - version controlled deployment script powered by http://www.alphanodes.com',
        'version'     => $this->version,
    ));

    // add a global option to make the program verbose
    $parser->addOption(
      'verbose',
      array(
        'short_name'  => '-v',
        'long_name'   => '--verbose',
        'action'      => 'StoreTrue',
        'description' => 'turn on verbose output',
      )
    );

    // add a global option to make the program verbose
    $parser->addOption(
      'debug',
      array(
        'short_name'  => '-d',
        'long_name'   => '--debug',
        'action'      => 'StoreTrue',
        'description' => 'turn on verbose output',
      )
    );

    // add a global option to make the program verbose
    $parser->addOption(
      'quit',
      array(
        'short_name'  => '-q',
        'long_name'   => '--quit',
        'action'      => 'StoreTrue',
        'description' => 'suppress messages (expect error message)',
      )
    );

    $parser->addOption(
      'projects',
      array(
        'short_name'  => '-p',
        'long_name'   => '--projects',
        'action'      => 'StoreTrue',
        'description' => 'show list of all active projects',
      )
    );

    $parser->addOption(
      'allprojects',
      array(
        'short_name'  => '-P',
        'long_name'   => '--allprojects',
        'action'      => 'StoreTrue',
        'description' => 'show list of all projects (active and inactive)',
      )
    );

    $this->_setPlugins();

    $this->_buildSubCommands($parser);

    // run the parser
    try {
      $result = $parser->parse();

      if ($result->options['projects']) {
        // show projects
        $vcdeploy = new Vcdeploy($this->conf, NULL, $result, $this->version);
        $projects = $vcdeploy->get_projects();
        foreach ($projects AS $project_name => $project_settings) {
          print($project_name . "\n");
        }
      }
      elseif ($result->options['allprojects']) {
        // show all projects
        $vcdeploy = new Vcdeploy($this->conf, NULL, $result, $this->version);
        $projects = $vcdeploy->get_all_projects();
        foreach ($projects AS $project_name) {
          print($project_name . "\n");
        }
      }
      elseif ($result->command_name) {

        $plugin_info = $this->plugin_infos[$result->command_name];

        // 1. batch_before commands
        if (isset($plugin_info['batch_before']) && is_array($plugin_info['batch_before'])) {
          foreach ($plugin_info['batch_before'] AS $batch_before) {
            $this->plugin_name = $batch_before;
            $this->run_plugin($result, TRUE);
          }
        }

        // 2. plugin (run) command
        $this->plugin_name = $result->command_name;
        $rc = $this->run_plugin($result);

        // 3. batch_before commands
        if (isset($plugin_info['batch_after']) && is_array($plugin_info['batch_after'])) {
          foreach ($plugin_info['batch_after'] AS $batch_after) {
            $this->plugin_name = $batch_after;
            $rc = $this->run_plugin($result);
          }
        }

        return $rc;
      }
      else {
        $parser->displayUsage();
      }
    } catch (Exception $exc) {
        $parser->displayError($exc->getMessage());
    }
  }

  /**
   * Run specified plugin
   *
   * @param  object $result
   * @param  bool $no_message
   *
   * @return  int return code of plugin
   * @throws Exception
   */
  public function run_plugin($result, $no_message = FALSE) {

    // if script is already running, stop it
    if ($this->_checkAlreadyRunning()) {
      throw new Exception('vcdeploy is already running with plugin ' . $this->plugin_name);
    }

    include_once $this->plugins[$this->plugin_name] . '/plugin_' . $this->plugin_name . '.class.php';

    $c = $this->_getPluginClass($this->plugin_name);
    $app = new $c($this->conf, $this->plugin_name, $result, $this->version);

    $app->set_projects();

    if (!array_key_exists($this->plugin_name, $this->plugin_infos)) {
      throw new Exception('Only root can run this plugin');
    }

    print('Run ' . $this->plugin_name . ' at ' . $this->hostname . "...\n");

    $rc = $app->run();
    if ($rc) {
      print('An error occured (rc=' . $rc . ")\n");
    }
    elseif (!$no_message) {
      print("Successfully finished.\n");
    }

    return $rc;
  }
}
