<?php
/**
 *   Plugin for log system changes
 *
 *   - log drupal module changes
 *   - log system package changes
 *   - log etc changes
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

$plugin['info'] = 'log system changes';
$plugin['root_only'] = false;

/**
 * Class VcdeployPluginSystemLog
 */
class VcdeployPluginSystemLog extends Vcdeploy implements IVcdeployPlugin
{
    /**
     * File name for package list
     *
     * @var string
     */
    private $package_file_name = 'packages.txt';

    /**
     * Directory to use for log files
     *
     * @var string
     */
    private $log_dir;

    /**
     * This function is run with the command
     *
     * @return int
     * @throws Exception
     * @see vcdeploy#run()
     */
    public function run()
    {

        if (empty($this->conf['package_manager'])) {
            throw new Exception('No package manager defined');
        } elseif (empty($this->conf['log_source'])) {
            throw new Exception('No log_source directory defined');
        } elseif (!file_exists($this->conf['log_source'])) {
            throw new Exception('log_source does not exist');
        }

        // set log_dir
        $this->log_dir = $this->conf['log_source'] . '/' . $this->conf['log_host'];

        // is not working without changing directory with php script :(
        chdir($this->conf['log_source']);
        // make sure we have the latest content,
        // which is required to avoid conflicts
        $this->system($this->conf['git_bin'] . ' --work-tree ' . $this->conf['log_source'] . ' pull', true);

        if ($this->conf['log_etc_dir']) {
            if ($this->current_user == 'root') {
                $this->_etcLog();
            } else {
                $this->msg('system etc and packages log is only tracked for root');
            }
        }

        $this->_packageList();
        $this->_projectTasks();
        $this->_commitToVcm();

        return 0;
    }

    /**
     * Get max steps of this plugin for progress view
     *
     * @param int $init initial value of counter
     *
     * @return int amount of working steps of this plugin
     * @see Vcdeploy#progressbar_init()
     */
    public function get_steps($init = 0)
    {
        return ++$init;
    }

    /**
     * Exclude paremeters for rsync
     *
     */
    private function _getExcludeParameters()
    {
        $paras = array();

        $excludes = explode(' ', $this->conf['log_excludes']);
        foreach ($excludes AS $exclude) {
            $paras[] = '--exclude=' . $exclude;
        }

        return implode(' ', $paras);
    }

    /**
     * Is the server mac os x or linux
     *
     * @return bool
     */
    private function _detectDarwin()
    {
        ob_start();
        phpinfo(1);
        $pinfo = ob_get_contents();
        ob_end_clean();

        if (substr_count(strtolower($pinfo), 'darwin kernel version') > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Log changes in etc directory
     *
     */
    private function _etcLog()
    {
        // run rsync
        $this->msg('rsync etc directory for tracking...');
        // source directory with added slash (required on mac os x)
        $cmd = $this->conf['rsync_bin'] . ' -aH ' . $this->_getExcludeParameters() . ' ' . $this->conf['etc_dir'] . '/ ' . $this->log_dir . '/etc';
        $this->system($cmd);

        // track file metafiles
        if ($this->_detectDarwin()) {
            $ls_para = '-RlT';
        } else {
            $ls_para = '-Rl --full-time';
        }

        $rc = $this->system('ls ' . $ls_para . ' ' . $this->log_dir . '/etc');
        if (!$rc['rc']) {
            if (is_array($rc['output'])) {
                $info = '';
                foreach ($rc['output'] AS $line) {
                    $info .= $line . "\n";
                }
                $filename = $this->log_dir . '/etc_metadata.txt';
                file_put_contents($filename, $info);
            }
        } else {
            throw new Exception('Cannot calculate etc metadata information');
        }
    }

    /**
     * Do project tasks
     *
     */
    private function _projectTasks()
    {
        if (count($this->projects)) {
            foreach ($this->projects AS $project_name => $project) {
                $this->set_project($project_name, $project);

                if (isset($this->project['drush'])) {
                    $this->msg('Drupal module log on ' . $this->project_name);
                    $this->_drushModules($this->project['drush']);
                }

                if (isset($this->project['sql_to_scm']) && $this->project['sql_to_scm']) {
                    $this->msg('Dump SQL to SCM on ' . $this->project_name);

                    if (isset($this->project['sql_to_scm_with_db_sanitize']) && $this->project['sql_to_scm_with_db_sanitize']) {
                        $with_sanitize = true;
                    } else {
                        $with_sanitize = false;
                    }

                    foreach ($this->project['db'] AS $db) {
                        $this->sanitize_database($this->log_dir . '/dump_' . $this->project_name . '.sql', $db, $with_sanitize);
                    }
                }
            }
        }
    }

    /**
     * Get information about drupal modules
     *
     * @param string
     *
     * @return void
     */
    private function _drushModules($script)
    {
        $rc = $this->system($script . ' pm-list');
        if (!$rc['rc']) {
            if (is_array($rc['output'])) {
                $info = '';
                foreach ($rc['output'] AS $line) {
                    $info .= $line . "\n";
                }
                $filename = $this->log_dir . '/drupal_modules_' . $this->project_name . '.txt';
                file_put_contents($filename, $info);
            }
        }
    }

    /**
     * Create a list with all installed software packages
     * on the system and commit this list to git
     *
     */
    private function _packageList()
    {
        $this->msg('Create system package list...');

        switch ($this->conf['package_manager']) {
            case 'apt':
                $cmd_create = "dpkg-query -W -f '\${Status}\t\${Package} \${Version}\n' | egrep '(ok installed|ok config-files)' | cut -f2,3";
                // $cmd_create = 'dpkg --get-selections "*"';
                break;
            case 'rpm':
                $cmd_create = 'rpm -qa --queryformat "%{name} %{version} %{arch}\n" | sort';
                break;
            case 'port':
                $cmd_create = 'port installed';
                break;
            default:
                throw new Exception('Package manager ' . $this->conf['package_manager'] . ' is not supported');
        }

        // absolute path of package log file
        $package_log_file = $this->log_dir . '/' . $this->package_file_name;

        $this->system($cmd_create . ' > ' . $package_log_file, true);
    }

    /**
     * Commit changes to vcm
     *
     * @return void
     */
    private function _commitToVcm()
    {
        $this->system($this->conf['git_bin'] . ' --work-tree ' . $this->conf['log_source'] . ' add -A ' . $this->log_dir, true);
        $this->system($this->conf['git_bin'] . ' --work-tree ' . $this->conf['log_source'] . ' commit -m "Changes found on ' . $this->hostname . '"', true);
        $this->system($this->conf['git_bin'] . ' --work-tree ' . $this->conf['log_source'] . ' push', true);
    }
}
