<?php

/**
 * Main class of sldeploy script
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
 * Class Vcdeploy
 */
class Vcdeploy
{
    /**
     * Application version VcDeployLoader->version
     *
     * @var string
     */
    protected $version;

    /**
     * Base directory
     *
     * @var string
     */
    public $base_dir;

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
     * SSH connection configuration
     *
     * @required for ssh_system
     * @var array
     *    valid keys: user, port, host, verbose
     */
    public $ssh;

    /**
     * A list of all active projects
     *
     * All projects specified in the configuration file with
     * active = true
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
     * User, which execute vcdeploy
     *
     * @var string
     */
    public $current_user;

    /**
     * Nice level for system command
     *
     * @var int
     */
    public $nice = 0;

    /**
     * Log to file
     *
     * @var object
     */
    public $logger;

    /**
     * Database class
     *
     * Set with $this->set_db()
     *
     * @var object
     */
    protected $db;

    /**
     * SCM class
     *
     * Set with $this->set_scm()
     *
     * @var object
     */
    protected $scm;

    /**
     * Active plugin
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * Command line parameters
     *
     * @var object
     */
    protected $paras;

    /**
     * Progress bar
     *
     * @var object
     */
    private $progressbar;

    /**
     * Current position for progress bar
     *
     * @var int
     * @see progressbar_update
     */
    private $progressbar_current_pos = 0;

    /**
     * Constructor
     *
     * @param array $conf configuration from configuration file
     * @param string $plugin_name name of the active plugin
     * @param object $paras command line parameters
     * @param string $version vcdeploy application version
     *
     */
    public function __construct($conf, $plugin_name, $paras, $version)
    {
        global $logger;

        $this->conf = $conf;
        $this->version = $version;
        $this->plugin_name = $plugin_name;
        $this->paras = $paras;
        $this->hostname = $this->get_hostname();
        $this->base_dir = dirname($_SERVER['SCRIPT_NAME']);

        $this->date_stamp = date('YmdHi');

        $this->logger = $logger;

        $rc = $this->system('whoami');
        $this->current_user = $rc['output'][0];

        if (isset($this->conf['debug']) && $this->conf['debug']) {
            $this->debug = true;
        }
    }

    /**
     * Show progress bar in non-verbose mode, otherwise show $msg
     *
     * @param string $msg
     * @param bool $with_step
     *
     * @return void
     */
    public function show_progress($msg, $with_step = true)
    {
        // show verbose message
        if ((isset($this->paras->options['verbose']) && $this->paras->options['verbose']) || !is_object($this->progressbar)) {
            $this->msg($msg);
        } else {
            $this->progressbar_update($with_step);
        }
    }

    /**
     * Update progressbar
     *
     * @param  bool $with_step
     *
     * @return void
     */
    public function progressbar_update($with_step = true)
    {
        if (!isset($this->paras->options['verbose']) || !$this->paras->options['verbose']) {

            if ($with_step) {
                $this->progressbar_step();
            }

            $this->progressbar->update($this->progressbar_current_pos);
        }
    }

    /**
     * Add next step to progress bar
     *
     * @return void
     */
    public function progressbar_step()
    {
        $this->progressbar_current_pos++;
    }

    /**
     * Get current progress counter
     *
     * @return int
     */
    public function get_progressbar_pos()
    {
        return $this->progressbar_current_pos;
    }

    /**
     * Initialize progressbar
     *
     * only active on non-verbose mode
     *
     * maximum steps are fetch from $this->get_progress_steps()
     *
     * @param int $init initial value of counter
     *
     * @return void
     */
    public function progressbar_init($init = 0)
    {
        // initialize progress bar for non-verbose
        if (!isset($this->paras->options['verbose']) || !$this->paras->options['verbose']) {
            $this->progressbar = new Console_ProgressBar(' %fraction% [%bar%] %percent%  ', '=', ' ', 50, $this->get_steps($init));
        }
    }

    /**
     * Set $this->db object for database operations
     *
     * @return void
     * @throws Exception
     */
    protected function set_db()
    {
        if (!empty($this->project) && isset($this->project['dbtype'])) {
            $db_type = $this->project['dbtype'];
        } else {
            $db_type = $this->conf['dbtype'];
        }
        include_once 'vcdeploy/db/' . $db_type . '.inc.php';
        $class = 'VcdeployDb' . ucwords($db_type);
        if (!empty($this->project)) {
            $this->db = new $class($this->conf, $this->project);
        } else {
            $this->db = new $class($this->conf);
        }
    }

    /**
     * Set $this->scm object for SCM operations
     *
     * @param string $mode values: system, project
     *
     * @return void
     * @throws Exception
     */
    protected function set_scm($mode = 'system')
    {
        switch ($mode) {
            case 'system':
                $scm_type = $this->conf['source_scm'];
                break;
            case 'project':
                if (!is_array($this->project)) {
                    throw new Exception('set_scm error: scm mode project requires $this->project');
                }
                $scm_type = $this->project['scm']['type'];
                break;
            default:
                throw new Exception('set_scm error: unknown scm mode \'' . $mode . '\'');
        }

        include_once 'vcdeploy/scm/' . $scm_type . '.inc.php';
        $class = 'VcdeployScm' . ucwords($scm_type);
        if ($mode == 'project') {
            $this->scm = new $class($this->conf, $this->project);
        } else {
            $this->scm = new $class($this->conf);
        }
    }


    private function set_subprojects()
    {
        $this->project['subprojects'] = array();
        if (isset($this->project['depends']) && !empty($this->project['depends'])) {
            $subprojects = explode(',', str_replace("\n", '', $this->project['depends']));
            foreach ($subprojects AS $subproject) {
                $subproject = trim($subproject);
                if (!array_key_exists($subproject, $this->projects)) {
                    throw new Exception('Project ' . $subproject . ' is not defined but required for parent project ' . $this->project_name);
                }
                $this->project['subprojects'][] = $subproject;
            }
        }

        if (in_array($this->project_name, $this->project['subprojects'])) {
            throw new Exception('Parent project ' . $this->project_name . ' must not be in the projects dependency list itelse');
        }
    }

    /**
     * Set current project to
     *
     * $this->project_name
     * $this->project
     *
     * @param  string $project_name name of project
     * @param  array $project project details
     * @param array $parent_project
     *
     * @throws Exception
     */
    public function set_project($project_name, $project, $parent_project = null)
    {
        /**
         * Check if project exists
         */
        if (!array_key_exists($project_name, $this->projects)) {
            throw new Exception('Project "' . $project_name . '" is not configured!');
        }

        $this->project_name = $project_name;
        $this->project = $project;

        $this->set_subprojects();

        if (isset($this->project['ssh'])) {
            $this->ssh = $this->project['ssh'];
        }

        if (isset($parent_project) && isset($parent_project['path'])) {
            $this->project['path'] = str_replace('[parent_path]', $parent_project['path'], $this->project['path']);
        }

        // if not set, default is git
        if (!isset($this->project['scm']['type'])) {
            $this->project['scm']['type'] = 'git';
        }

        // convert strings to array
        if (isset($this->project['db']) && !is_array($this->project['db'])) {
            $this->project['db'] = array('db' => $this->project['db']);
        }
        if (isset($this->project['source_db']) && !is_array($this->project['source_db'])) {
            $this->project['source_db'] = array('db' => $this->project['source_db']);
        }

        if (isset($this->project['data_dir']) && !is_array($this->project['data_dir'])) {
            $this->project['data_dir'] = array('files' => $this->project['data_dir']);
        }
        if (isset($this->project['source_data_dir']) && !is_array($this->project['source_data_dir'])) {
            $this->project['source_data_dir'] = array('files' => $this->project['source_data_dir']);
        }
    }

    /**
     * Get active and inactive projects
     *
     * @return array with project names
     */
    public function get_all_projects()
    {
        global $project;

        $projects = array();

        if (is_array($project) && count($project)) {
            foreach ($project AS $project_name => $project_settings) {
                if ($project_name != $this->common_project_name) {
                    $projects[] = $project_name;
                }
            }
        }
        return $projects;
    }

    /**
     * Get project array
     *
     * @param string $project_name
     * @return array
     */
    public function get_project($project_name)
    {
        return $this->projects[$project_name];
    }

    /**
     * Get active projects
     *
     * Returns an array with all active projects, which are defined.
     *
     * @param bool $onlyParentProjects
     * @return array
     * @throws Exception
     */
    public function get_projects($onlyParentProjects = false)
    {
        global $project;

        if (is_array($this->projects)) {
            return $this->projects;
        } else {

            $projects = array();

            if (is_array($project) && count($project)) {

                foreach ($project AS $project_name => $project_settings) {
                    if (($project_name != $this->common_project_name)
                        && (!isset($project_settings['active'])
                            || (isset($project_settings['active']) && $project_settings['active']))
                        && (!$onlyParentProjects || ($onlyParentProjects && !isset($project_settings['subproject'])
                                || (isset($project_settings['subproject']) && !$project_settings['subproject'])))
                    ) {

                        // set default configuration, if available
                        if (isset($project[$this->common_project_name])) {
                            // set project configuration
                            $projects[$project_name] = $this->_arrayMergeRecursiveDistinct($project[$this->common_project_name], $project_settings);
                        } else {
                            // set project configuration
                            $projects[$project_name] = $project_settings;
                        }

                        // set command configuration, if available
                        // if plugin name is used as array key, this entries are shift to $this->project root level!
                        if (isset($projects[$project_name][$this->plugin_name])) {
                            $projects[$project_name] = array_merge($projects[$project_name], $projects[$project_name][$this->plugin_name]);
                            unset($projects[$project_name][$this->plugin_name]);
                        }

                        if (!isset($projects[$project_name]['remote_tmp_dir'])) {
                            $projects[$project_name]['remote_tmp_dir'] = $this->conf['tmp_dir'];
                        }

                        // Project path is always required for a valid projec
                        if (!isset($projects[$project_name]['path'])) {
                            throw new Exception('Missing project path for project "' . $project_name . '".');
                        }
                    }
                }
            }

            return $projects;
        }
    }

    /**
     * Set active projects to $this->projects
     *
     * @return void
     */
    public function set_projects()
    {
        $this->projects = $this->get_projects();
    }

    /**
     * Get hostname
     *
     * @return  string
     */
    protected function get_hostname()
    {
        $hi = $this->system('hostname');
        return $hi['output'][0];
    }

    /**
     * Set nice level
     *
     * @param  string $level high or low are allowed values
     *
     * @return void
     */
    public function set_nice($level)
    {
        $known_levels = array('high', 'low');
        if (in_array($level, $known_levels)) {
            $this->nice = $this->conf['nice_' . $level];

            // negative nice level is only available for root
            if (($this->nice < 0) && ($this->current_user)) {
                $this->nice = 0;
            }

        } else {
            $this->msg('Unknow nice level (rc=' . $level . ')');
        }
    }

    /**
     * Execute system call
     *
     * @param   string $command system command to execute
     * @param   bool $passthru
     *
     * @return  string system command output
     */
    public function system($command, $passthru = false)
    {
        if ($this->debug) {
            $this->msg('system: ' . $command);
        }

        // include nice, if not 0
        if ($this->nice != 0) {
            $command = $this->conf['nice_bin'] . ' -n ' . $this->conf['nice_low'] . ' ' . $command;
        }

        $rc = 0;

        if ($passthru) {
            passthru($command, $rc);
            $output = '';
        } else {
            exec($command, $output, $rc);
        }

        // always reset nice after execution
        $this->nice = 0;

        return array('output' => $output, 'rc' => $rc);
    }

    /**
     * Print message to console
     *
     * @param string $msg message to print
     * @param int $error_code if greater 0, script exit with $error_code as return value
     *
     * @return int
     */
    public function msg($msg, $error_code = 0)
    {
        if (is_array($msg)) {
            $msg = print_r($msg, true);
        }
        echo $msg . "\n";
        if ($this->conf['write_to_log']) {
            $this->logger->log($msg, PEAR_LOG_INFO);
        }
        if ($error_code !== 0) {
            exit($error_code);
        }
    }

    /**
     * Run post commands
     *
     * @param array $commands install calls
     * @param bool $try if true, this is a test run without system calls
     * @throws Exception
     * @return int amount of system commands
     */
    public function installCommands($commands, $try = false)
    {
        return $this->_hook_commands($commands, 'install', $try);
    }

    /**
     * Run Hooks
     *
     * @param string $name pre, post or install hook
     * @param bool $try if true, this is a test run without system calls
     * @return int amount of system commands
     * @throws Exception
     */
    public function runHooks($name, $try = false)
    {
        if (!in_array($name, array('pre', 'post'))) {
            throw new Exception('Unsupported hook ' . $name . ' type for ' . $this->plugin_name);
        }

        $rc = 0;
        $command_name = $name . '_commands';

        if (!isset($this->paras->command->options['without_commands']) || !$this->paras->command->options['without_commands']) {

            $commands = array();
            if ($this->plugin_name == 'rollout-system') {
                if (isset($this->conf[$command_name])) {
                    $commands = $this->conf[$command_name];
                }
            } else {
                $key_name = str_replace('-', '_', $this->plugin_name);
                if ($key_name == $this->plugin_name) {
                    if (isset($this->project[$command_name])) {
                        $commands = $this->project[$command_name];
                    }
                } else if (isset($this->project[$key_name][$command_name])) {
                    $commands = $this->project[$key_name][$command_name];
                }
            }

            if (count($commands)) {
                $rc = $this->_hook_commands($commands, $name, $try);
            }
        }

        return $rc;
    }

    /**
     * Run this pre or post commands after plugin action (if supported by plugin)
     *
     * @param array $commands commands for system calls
     * @param string $msg
     * @param bool $try do not run commands (just count)
     *
     * @return int
     *    amount of commands
     * @throws Exception
     */
    private function _hook_commands($commands, $msg, $try = false)
    {
        $numCommands = 0;

        if (is_array($commands)) {

            $numCommands = count($commands);

            if (!$try) {
                foreach ($commands AS $command_info) {

                    if (is_array($command_info)) {
                        if (!isset($command_info['command'])) {
                            throw new Exception($msg . ' command error: command key not specified');
                        }
                    } else { // no array is used
                        $command_info = array('command' => $command_info);
                    }

                    if (isset($this->project['drush'])) {
                        $command_info['command'] = str_replace('[drush]', $this->project['drush'], $command_info['command']);
                    }

                    // switch to project path
                    if (isset($command_info['path'])) {
                        // only change directoy, if path is not empty. Use this, if you don't want to change directory
                        if (!empty($command_info['path'])) {
                            if (!chdir($command_info['path'])) {
                                throw new Exception($msg . ' command error: command path for ' . $command_info['command'] . ' does not exist (' . $command_info['path'] . ')');
                            }
                        }
                    } elseif (isset($this->project['path'])) { // Switch to project path by default
                        if (!chdir($this->project['path'])) {
                            throw new Exception($msg . ' command error: ' . $command_info['command'] . ' (changing to project path not possible)');
                        }
                    }

                    // show verbose message
                    if ((isset($this->paras->options['verbose']) && $this->paras->options['verbose'])) {
                        $this->msg('Running ' . $msg . ' command: ' . $command_info['command']);
                        $rc = $this->system($command_info['command'], true);
                    } else {
                        $rc = $this->system($command_info['command']);
                    }

                    if ($rc['rc'] != 0) {
                        throw new Exception($msg . ' command error: ' . $command_info['command'] . ' (rc=' . $rc['rc'] . ')');
                    }
                }
            }
        }

        return $numCommands;
    }

    /**
     * Check ssh configuration
     *
     * @return void
     * @throws Exception
     */
    public function ssh_check()
    {
        if (!is_array($this->ssh)) {
            throw new Exception('ssh_system error: ssh configuration is missing');
        } elseif (empty($this->ssh['host'])) {
            throw new Exception('ssh_system error: host is missing');
        }
    }

    /**
     * Get SSH hostname with optional user name
     *
     * @return string
     */
    private function _getSshHost()
    {
        if (!empty($this->ssh['user'])) {
            return $this->ssh['user'] . '@' . $this->ssh['host'];
        } else {
            return $this->ssh['host'];
        }
    }


    /**
     * Get SCP (Secure Copy) with optional parameters
     * (port, identity_file, verbose)
     *
     * @return string
     */
    private function _getScpBin()
    {
        $command = $this->conf['scp_bin'];
        if (!empty($this->ssh['port'])) {
            $command .= ' -P ' . $this->ssh['port'];
        }
        if (!empty($this->ssh['identity_file'])) {
            $command .= ' -i ' . $this->ssh['identity_file'];
        }
        if (!empty($this->ssh['verbose'])) {
            $command .= ' -v';
        }

        return $command;
    }

    /**
     * Get SSH with optional parameters (port, identity_file, verbose)
     *
     * @return string
     */
    private function _getSshBin()
    {
        $command = $this->conf['ssh_bin'];
        if (!empty($this->ssh['port'])) {
            $command .= ' -p ' . $this->ssh['port'];
        }
        if (!empty($this->ssh['identity_file'])) {
            $command .= ' -i ' . $this->ssh['identity_file'];
        }
        if (!empty($this->ssh['verbose'])) {
            $command .= ' -v';
        }

        return $command;
    }

    /**
     * Get remote file with ssh
     *
     * @param string $remote_file
     * @param string $local_file
     *
     * @return void
     * @throws Exception
     */
    public function ssh_get_file($remote_file, $local_file)
    {
        $this->ssh_check();
        $remote_file = $this->_getSshHost() . ':' . $remote_file;

        $this->msg('Transfer file from ' . $this->_getSshHost() . '...');
        $rc = $this->system($this->_getScpBin() . ' ' . $remote_file . ' ' . $local_file, true);
        if ($rc['rc']) {
            throw new Exception('File could not be transfered. (' . $remote_file . ')');
        }
    }

    /**
     * Tranfer file to remote server with ssh
     *
     * @param string $local_file
     * @param string $remote_file
     *
     * @return void
     * @throws Exception
     */
    public function ssh_put_file($local_file, $remote_file)
    {
        $this->ssh_check();
        $remote_file = $this->_getSshHost() . ':' . $remote_file;

        $this->msg('Transfer file to ' . $this->_getSshHost() . '...');
        $rc = $this->system($this->_getScpBin() . ' ' . $local_file . ' ' . $remote_file, true);
        if ($rc['rc']) {
            throw new Exception('File could not be transfered. (' . $local_file . ')');
        }
    }

    /**
     * Execute system call over SSH
     *
     * @require $this->ssh_user
     * @require $this->ssh_server
     *
     * @param   string $command system command to execute over ssh
     * @param   bool $passthru
     * @return  string command output
     */
    public function ssh_system($command, $passthru = false)
    {
        $this->ssh_check();

        $ssh_command = $this->_getSshBin() . ' ' . $this->_getSshHost();

        return $this->system($ssh_command . ' "' . $command . '"', $passthru);
    }

    /**
     * Compress file
     *
     * @param string $filename
     * @param bool $only_command
     *
     * @return string files list of files (compressed file and hash file)
     * @throws Exception
     */
    public function gzip_file($filename, $only_command = false)
    {
        $command = $this->conf['gzip_bin'] . ' -f ' . $filename;

        if (!$only_command) {
            $gz_filename = $filename . '.gz';
            $files = array($gz_filename);

            $this->set_nice('low');
            $this->show_progress('compressing file: ' . $filename);
            $rc = $this->system($command, true);
            if ($rc['rc']) {
                throw new Exception('Error while compress file ' . $filename);
            } elseif ($this->conf['create_hashfiles']) {
                $files[] = $this->md5_file($gz_filename);
            }
            return $files;
        } else {
            return $command;
        }
    }

    /**
     * Create md5 hash and create hash file
     *
     * @param string $filename name of file
     *
     * @return string filename of hash file
     * @throws Exception
     */
    public function md5_file($filename)
    {
        $md5_filename = $filename . '.md5';
        $md5 = md5_file($filename);

        try {
            file_put_contents($md5_filename, $md5 . '  ' . basename($filename));
        } catch (Exception $e) {
            $this->msg('Could not create hash file \'' . $md5_filename . '\'', 1);
        }

        return $md5_filename;
    }

    /**
     * Check if directory is /
     *
     * @param string $dir
     * @return bool
     */
    public function is_root_dir($dir)
    {
        if (!empty($dir)) {
            // check for one or more /
            if (preg_match('/^[\/]+$/', $dir)) {
                return true;
            }
            if ($dir == '/') {
                return true;
            }
        }
        return false;
    }

    /**
     * Remove directory (with content and subdirectories)
     *
     * @param string $dir
     *
     * @return void
     * @throws Exception
     */
    public function remove_directory($dir)
    {
        if (file_exists($dir)) {
            // remove existing target directory
            if (!$this->is_root_dir($dir)) {
                // set permission, to force delete command for all files
                $this->system('chmod -R 700 ' . $dir);
                // remove directories and files
                $rc = $this->system('rm -r ' . $dir);
                if ($rc['rc']) {
                    throw new Exception('Error with removing directory \'' . $dir . '\'');
                }
            } else {
                throw new Exception('Never ever use / as target directory!');
            }
        } else {
            $this->msg('Directory ' . $dir . ' does not exists, removing is not required.');
        }
    }

    /**
     * Merges any number of arrays / parameters recursively, replacing
     * entries with string keys with values from latter arrays.
     * If the entry or the next value to be assigned is an array, then it
     * automagically treats both arguments as an array.
     * Numeric entries are appended, not replaced, but only if they are
     * unique
     *
     * calling: result = _arrayMergeRecursiveDistinct(a1, a2, ... aN)
     *
     * @return array
     */
    private function _arrayMergeRecursiveDistinct()
    {
        $arrays = func_get_args();
        $base = array_shift($arrays);
        if (!is_array($base)) {
            $base = empty($base) ? array() : array($base);
        }
        foreach ($arrays as $append) {
            if (!is_array($append)) {
                $append = array($append);
            }
            foreach ($append as $key => $value) {
                if (!array_key_exists($key, $base) and !is_numeric($key)) {
                    $base[$key] = $append[$key];
                    continue;
                }
                if (is_array($value) or is_array($base[$key])) {
                    $base[$key] = $this->_arrayMergeRecursiveDistinct($base[$key], $append[$key]);
                } elseif (is_numeric($key)) {
                    if (!in_array($value, $base)) {
                        $base[] = $value;
                    }
                } else {
                    $base[$key] = $value;
                }
            }
        }
        return $base;
    }

    /**
     * Validate, if any project is configurated
     *
     * @throws Exception
     */
    public function validate_projects()
    {
        if (!count($this->projects)) {
            throw new Exception('No project configuration found');
        }
    }

    /**
     * Create backup of data directories
     *
     * @return void
     * @throws Exception
     */
    public function create_project_data_backup()
    {
        if (!is_array($this->project)) {
            throw new Exception('set_scm error: scm mode project requires $this->project');
        }
        foreach ($this->project['data_dir'] AS $name => $dir) {

            $target_file = $this->conf['backup_dir']
                . '/' . $this->project_name
                . $name . '-' . $this->date_stamp . '.tar';
            $this->create_data_dump($dir, $target_file);
        }
    }

    /**
     * Get source database name from database identifier
     *
     * @param string $identifier
     *
     * @return string
     */
    public function get_source_db($identifier)
    {
        // first look for source database
        if (isset($this->project['source_db'][$identifier]) && $this->project['source_db'][$identifier]) {
            $source_db = $this->project['source_db'][$identifier];
        } else {
            // if no source database has been specified,
            // same name as local will be used
            $source_db = $this->project['db'][$identifier];
        }

        return $source_db;
    }

    /**
     * Get source data name from data identifier
     *
     * @param string $identifier
     *
     * @return string
     * @throws Exception
     */
    public function get_source_data($identifier)
    {
        if (isset($this->project['source_data_dir'][$identifier]) && $this->project['source_data_dir'][$identifier]) {
            $source_dir = $this->project['source_data_dir'][$identifier];
        } else {
            throw new Exception('No source data found with identifier \'' . $identifier . '\'');
        }

        return $source_dir;
    }

    /**
     * Create a tar.gz file of a directory
     *
     * @param string $source_dir
     * @param string $target_file
     * @param array $excludes
     *
     * @return array created files (dump and hash file)
     * @throws Exception
     */
    public function create_data_dump($source_dir, $target_file, $excludes = null)
    {
        $this->show_progress('Creating tar file of directory ' . $source_dir . '...');

        // change to parent directory
        $current_dir = getcwd();
        $parent_dir = dirname($source_dir);

        $command = $this->conf['tar_bin'] . ' cplf ' . $target_file;
        if (isset($excludes) && is_array($excludes)) {
            $command .= ' --exclude="' . implode('" --exclude="', $excludes) . '"';
        }
        $command .= ' ' . basename($source_dir);

        $this->set_nice('low');

        try {
            chdir($parent_dir);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        $rc = $this->system($command);
        chdir($current_dir);

        if (!$rc['rc']) {
            return $this->gzip_file($target_file);
        } else {
            throw new Exception('Error creating dump of directory "' . $source_dir . '"');
        }
    }

    /**
     * Create database dump
     *
     * If the target file already exists, it will be overwritten
     *
     * @param string $db_name
     * @param string $target_file
     *
     * @return array created files (dump and hash file)
     * @throws Exception
     */
    public function create_db_dump($db_name, $target_file = null)
    {
        if (!isset($target_file)) {
            $target_file = $this->conf['backup_dir'] . '/db-' . $db_name . '-' . $this->date_stamp . '.sql';
        }

        $this->show_progress('Creating database dump of ' . $db_name . '...');

        $this->set_nice('high');
        $rc = $this->system($this->db->get_dump($db_name, $target_file));

        // if no error, compress sql dump
        if (!$rc['rc']) {
            return $this->gzip_file($target_file);
        } else {
            throw new Exception('Error creating database dump of "' . $db_name . '"');
        }
    }

    /**
     * Get base filename for database dump
     *
     * @param string $name
     *
     * @return string
     * @see get_sql_file
     */
    private function _getRemoteBasename($name)
    {
        $prefix = '';
        if (!empty($this->ssh['user'])) {
            $prefix .= $this->ssh['user'] . '_';
        }
        return $prefix . $this->project_name . '_' . $name;
    }

    /**
     * Get SQL file
     *
     * Dump a sql file on a remote server with ssl connection
     * and transfer file to build server
     *
     * @param string $identifier database identifier
     * @param string $db name of the database
     *
     * @return string  absolute filename to the local transfered sql file
     * @throws Exception
     */
    public function get_remote_db_file($identifier, $db)
    {
        if (!isset($this->project['source_type'])) {
            throw new Exception('Project source_type not specified.');
        }

        switch ($this->project['source_type']) {

            case 'local':
                // create local snapshot
                $target_file = $this->conf['tmp_dir'] . '/' . uniqid(rand(), true) . '.sql';
                $this->create_db_dump($db, $target_file);
                $sql_file = $target_file . '.gz';
                break;

            case 'backup':
                // use local backup file
                // TODO: support for different local database or dump file
                $sql_file = $this->project['sql_backup'] . '.gz';
                break;

            case 'remote':

                $remote_file = $this->project['remote_tmp_dir'] . '/' . $this->_getRemoteBasename('db_' . $identifier) . '.sql';

                $this->msg('Create Dump on remote server...(' . $this->ssh['host'] . ')');
                $rc = $this->ssh_system($this->db->get_dump($db, $remote_file), true);
                if ($rc['rc']) {
                    throw new Exception('Error creating remote dump.');
                }

                $this->msg('Compress remote file...');
                $rc = $this->ssh_system($this->gzip_file($remote_file, true), true);
                if ($rc['rc']) {
                    throw new Exception('Error compress remote file.');
                }

                $sql_file = $this->conf['tmp_dir'] . '/' . $this->_getRemoteBasename('db_' . $identifier) . '.sql.gz';

                $this->ssh_get_file($remote_file . '.gz', $sql_file);
                break;

            default:
                $sql_file = '';
        }

        return $sql_file;
    }

    /**
     * Get tar file
     *
     * @param string $identifier
     * @param string $source_dir
     *
     * @return string
     * @throws Exception
     */
    public function get_source_data_file($identifier, $source_dir)
    {
        if (!isset($this->project['source_type'])) {
            throw new Exception('Project source_type not specified.');
        }

        switch ($this->project['source_type']) {

            case 'local':
                // create local snapshot
                $target_file = $this->conf['tmp_dir'] . '/' . uniqid(rand(), true) . '.tar';
                $this->create_data_dump($source_dir, $target_file);
                $tar_file = $target_file . '.gz';
                break;

            case 'backup':
                // TODO: dir is a directory, this will not work!
                $tar_file = $source_dir . '.gz';
                break;

            case 'remote':

                $remote_file = $this->project['remote_tmp_dir'] . '/' . $this->_getRemoteBasename('data_' . $identifier) . '.tar.gz';

                $this->msg('Create TAR file on remote server (' . $this->ssh['host'] . ')...');

                $dir = basename($source_dir);

                $rc = $this->ssh_system('cd ' . dirname($source_dir) . ' && ' . $this->conf['tar_bin'] . ' cfz ' . $remote_file . ' ' . $dir);
                if ($rc['rc']) {
                    throw new Exception('Error creating tar file.');
                }

                $tar_file = $this->conf['tmp_dir'] . '/' . $this->_getRemoteBasename('data_' . $identifier) . '.tar.gz';

                $this->ssh_get_file($this->project['remote_tmp_dir'] . '/' . $this->_getRemoteBasename('data_' . $identifier) . '.tar.gz', $tar_file);
                break;

            default:
                $tar_file = '';
        }

        return $tar_file;
    }

    /**
     * Sanitize database
     *
     * Use by $this->sanitize_database() and reset_db
     *
     * @param string $db_name
     *
     * @return void
     */
    public function sanitize_database_sanitize($db_name)
    {
        // 1. truncates
        if (isset($this->project['sanitize']['truncates'])) {
            $tables = explode(' ', $this->project['sanitize']['truncates']);
            foreach ($tables AS $table) {
                if (!empty($table)) {
                    $this->msg('Truncate table ' . $table);
                    $this->system($this->db->get_table_truncate($db_name, $table), true);
                }
            }
        }

        // 2. drop
        if (isset($this->project['sanitize']['drop'])) {
            $tables = explode(' ', $this->project['sanitize']['drop']);
            foreach ($tables AS $table) {
                if (!empty($table)) {
                    $this->msg('Drop table ' . $table);
                    $this->system($this->db->get_table_drop($db_name, $table), true);
                }
            }
        }

        // 3. user defined sql queries
        if (isset($this->project['sanitize']['sql']) && is_array($this->project['sanitize']['sql'])) {
            foreach ($this->project['sanitize']['sql'] AS $sql) {
                if (!empty($sql)) {
                    $this->msg('Run sanitize SQL query...');
                    $this->system($this->db->get_query($db_name, $sql), true);
                }
            }
        }
    }

    /**
     * Sanitize database
     *
     * @param string $sql_file
     * @param string $source_db if specified, this database is used as source,
     *                          otherwise sql_file is also used as target
     *                          and source
     * @param bool $sanitize
     *
     * @return void
     */
    public function sanitize_database($sql_file, $source_db = null, $sanitize = true)
    {
        // 1. clear database
        $this->msg('Clear temporary database ' . $this->conf['tmp_db'] . '...');
        $this->system($this->db->get_db_drop($this->conf['tmp_db']), true);
        $this->system($this->db->get_db_create($this->conf['tmp_db']), true);

        // 2. clone database to tmp
        if (isset($source_db)) {
            $this->msg('Create SQL file ' . $source_db . '...');
            $this->system($this->db->get_dump($source_db, $sql_file), true);
        }
        $this->msg('Import SQL file...');
        $this->system($this->db->get_restore($this->conf['tmp_db'], $sql_file), true);

        // 3. Sanitize tmp database
        if ($sanitize) {
            $this->sanitize_database_sanitize($this->conf['tmp_db']);
        }

        // 4. Create dump
        $this->msg('Creating scm dump for ' . $this->project_name . '...');
        $this->system($this->db->get_dump($this->conf['tmp_db'], $sql_file), true);
    }

    /**
     * Check backup directory
     * - if directory can be created, it will be created
     * - if directory cannot be created, throw exception
     *
     * @return void
     * @throws Exception
     */
    public function prepare_backup_dir()
    {
        if (empty($this->conf['backup_dir'])) {
            throw new Exception('Backup directory not specified.');
        } elseif (file_exists($this->conf['backup_dir'])) {
            if (!is_writable($this->conf['backup_dir'])) {
                throw new Exception('Backup directory \'' . $this->conf['backup_dir'] . '\' is not writable.');
            }
        } else {

            try {
                mkdir($this->conf['backup_dir'], 0700, true);
            } catch (Exception $e) {
                $this->msg($e->message(), 1);
            }

            // TODO: check exception handler to die with warnings
            if (!file_exists($this->conf['backup_dir'])) {
                throw new Exception('Backup directory \'' . $this->conf['backup_dir'] . '\' does not exist and couldn\'t created automatically.');
            }
        }
    }

    /**
     * Check if database exists
     *
     * @param string $db_name
     * @return int
     */
    public function db_exists($db_name)
    {
        $rc = $this->system($this->db->get_db_exists($db_name));
        return count($rc['output']);
    }

    /**
     * Drop database
     *
     * @param string $db_name
     * @throws Exception
     */
    public function db_drop($db_name)
    {
        $rc = $this->system($this->db->get_db_drop($db_name));
        if ($rc['rc']) {
            throw new Exception('Error dropping database \'' . $db_name . '\'!');
        }
    }

    /**
     * Create database
     *
     * @param string $db_name
     * @throws Exception
     */
    public function db_create($db_name)
    {
        $rc = $this->system($this->db->get_db_create($db_name));
        if ($rc['rc']) {
            throw new Exception('Error creating database \'' . $db_name . '\'!');
        }
    }

    /**
     * Recreate database
     *
     * 1. drop existing database
     * 2. create database
     *
     * @param  string $db
     * @throws Exception
     */
    public function db_recreate($db)
    {
        // 1. drop database
        $this->db_drop($db);

        $this->msg('Recreating database ' . $db . '...');
        sleep(2);

        // 2. create database
        $this->db_create($db);
    }

    /**
     * Get number of project permission loops
     *
     * @param array $project
     * @return int
     */
    public function count_project_permissions($project)
    {
        $count = 0;
        if (isset($project['permissions']) && is_array($project['permissions'])) {

            foreach ($project['permissions'] AS $permission) {

                if (!is_array($permission['name'])) {
                    $permission['name'] = array($permission['name']);
                }

                // Run system call for each name entry
                foreach ($permission['name'] AS $name) {
                    if (isset($permission['mod']) && !empty($permission['mod'])) {
                        $count++;
                    }
                    if (isset($permission['own']) && !empty($permission['own'])) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Set permissions
     *
     * @param string $mode
     * @param array $permission
     *                   name = directory (required)
     *                   mod = permission value
     *                   own = owner
     *                   rec = recursive (default no)
     *                   filter = name filter
     * @param bool $root_dir
     *
     * @throws Exception
     * @return void
     */
    public function set_permissions($mode, $permission, $root_dir = false)
    {
        if (!isset($permission['name']) || empty($permission['name'])) {
            throw new Exception('name value (directory) is required for permissions.');
        } elseif (!isset($permission['rec'])) {
            $permission['rec'] = 'no';
        }

        if (isset($permission['filter'])) {
            $name_filter = ' -name "' . $permission['filter'] . '"';
        } else {
            $name_filter = '';
        }

        $type_filter = '';
        $maxdepth = '';
        switch ($permission['rec']) {

            case 'files':
                $type_filter = ' -type f';
                break;

            case 'dirs':
                $type_filter = ' -type d';
                break;

            case 'yes':
                break;

            default: // not recursive
                $maxdepth = ' -maxdepth 1';
        }

        if ($mode == 'own') {
            if (!isset($permission['own']) || empty($permission['own'])) {
                throw new Exception('own value is required for permissions (mode=own).');
            }
            $command = 'chown';
            $new_value = $permission['own'];
        } else {
            if (!isset($permission['mod']) || empty($permission['mod'])) {
                throw new Exception('mod value is required for permissions (mode=mod).');
            }
            $command = 'chmod';
            $new_value = $permission['mod'];
        }

        if (!is_array($permission['name'])) {
            $permission['name'] = array($permission['name']);
        }

        // Run system call for each name entry
        foreach ($permission['name'] AS $name) {
            // use root directory as prefix to name
            if (isset($root_dir)) {
                $name = $root_dir . $name;
            }

            if ($this->is_root_dir($name)) {
                throw new Exception('Permission should never ever set tor / (recursive)!');
            }

            $this->show_progress('Set permissions (' . $new_value . ') to ' . $name . '...');
            $rc = $this->system('find ' . $name . $type_filter . $name_filter . $maxdepth . ' -exec ' . $command . ' ' . $new_value . ' {} \;');
            if ($rc['rc']) {
                throw new Exception("Couldn't set permissions for $name.");
            }
        }
    }

    /**
     * Check, if backup is required to create
     *
     * @var bool command line option 'with_backup'
     * @var bool command line option 'without_backup'
     * @var bool $this ->conf['without_backup']
     * @return bool
     */
    public function is_backup_required()
    {
        if ((isset($this->paras->command->options['with_backup']) && ($this->paras->command->options['with_backup']))
            || (!isset($this->conf['without_backup']) || !$this->conf['without_backup'])
        ) {
            if (!isset($this->paras->command->options['without_backup']) || !$this->paras->command->options['without_backup']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check, if permission run is required
     *
     * @var bool command line option 'without_permission'
     * @return bool
     */
    public function is_permission_required()
    {
        if (!isset($this->paras->command->options['without_permission']) || !$this->paras->command->options['without_permission']) {
            return true;
        }

        return false;
    }
}
