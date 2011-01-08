<?php

require 'Console/CommandLine.php';
require 'Console/ProgressBar.php';
require 'sldeploy/sldeploy.class.php';

class SlDeployLoader {

  /**
   * Application version
   *
   * @var string
   */
  protected $version = '0.40';

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
   * (see $this->_checkParas for available plugins)
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
   * @var array
   */
  public $plugins;

  /**
   * Constructor
   *
   * @param array $conf
   * @param bool $write_to_log
   */
  public function __construct($conf) {

    global $logger;

    $this->conf = $conf;
    $this->hostname = $this->get_hostname();
    $this->base_dir = dirname($_SERVER['SCRIPT_NAME']);
    $this->plugin_dir = $this->base_dir . '/plugins';

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
    */
  private function _setPlugins() {

    $this->plugins = array();

    $d = dir($this->plugin_dir);
    while (FALSE !== ($entry = $d->read())) {
      if ((substr($entry, 0, 7) == 'plugin_') && (substr($entry, -10) == '.class.php')) {
        $this->plugins[] = substr($entry, 7, -10);
      }
    }
    $d->close();

    $this->plugin_infos = $this->_getPluginInfo();
  }

  /**
   * Get plugin informatin
   *
   */
  private function _getPluginInfo() {

    $plugins = array();

    foreach ($this->plugins AS $plugin_name) {
      unset($plugin);
      include_once $this->plugin_dir . '/plugin_' . $plugin_name . '.class.php';

      if ($this->check_plugin_permission($plugin['root_only'])) {
        unset($plugin['root_only']);
        $plugins[$plugin_name] = $plugin;
        if (!isset($plugin['info'])) {
          $plugins[$plugin_name]['description'] = 'No plugin info defined.';
        }
      }
    }

    return $plugins;
  }

  /**
   * Get hostname
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
   * Check parameters, if there are valid
   *
   * m (required) = modules
   *
   * @param   array   $paras
   * @return  bool    true, if parameters are valid
   */
  private function _checkParas($paras) {

    $this->_setPlugins();

    if (!is_array($paras)) {
      return;
    }
    if (array_key_exists('h', $paras)) {
      return TRUE;
    }
    if ((!array_key_exists('p', $paras)) || (!in_array($paras['p'], $this->plugins))) {
        return;
    }

    return TRUE;
  }

  /**
   * Build commands for command line parser
   *
   * @param object $parser
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

    $lock_file = $this->conf['tmp_dir'] . '/.sldeploy_' . $this->current_user . '_' . $this->plugin_name . '.lck';

    if (file_exists($lock_file)) {
      exec('ps', $output);
      if (is_array($output)) {
        $cnt = 0;
        foreach ($output AS $process) {
          if (substr_count($process, 'sldeploy ' . $this->plugin_name) > 0) {
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
   * @return string
   */
  private function _getPluginClass($plugin_name) {

    // convert plugin to class
    $words = ucwords(str_replace('-', ' ', $plugin_name));
    $name = str_replace(' ', '', $words);

    return 'SldeployPlugin' . $name;
  }

  /**
   * Sldeploy parser
   *
   * This method starts sldeploy to run a plugin
   *
   * @return int
   */
  public function parser() {


    // create the parser
    $parser = new Console_CommandLine(array(
        'description' => 'sldeploy - php deployment script by http://www.squatlabs.com',
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
        $sldeploy = new Sldeploy($this->conf, NULL, $result, $this->version);
        $projects = $sldeploy->get_projects();
        foreach ($projects AS $project_name => $project_settings) {
          print($project_name . "\n");
        }
      }
      elseif ($result->options['allprojects']) {
        // show all projects
        $sldeploy = new Sldeploy($this->conf, NULL, $result, $this->version);
        $projects = $sldeploy->get_all_projects();
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
   * @return  int - return code of plugin
   */
  public function run_plugin($result, $no_message = FALSE) {

    // if script is already running, stop it
    if ($this->_checkAlreadyRunning()) {
      throw new Exception('sldeploy is already running with plugin ' . $this->plugin_name);
    }

    include_once $this->plugin_dir . '/plugin_' . $this->plugin_name . '.class.php';

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
