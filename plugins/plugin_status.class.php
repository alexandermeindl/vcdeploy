<?php
/**
 * configuration information of vcdeploy
 *
 * PHP version 5.3
 *
 * @category  Console
 * @package   Vcdeploy
 * @author    Alexander Meindl <a.meindl@alphanodes.com>
 * @copyright 2015 Alexander Meindl
 * @license   http://www.mozilla.org/MPL Mozilla Public License Version 1.1
 * @link      https://github.com/alexandermeindl/vcdeploy
 */

$plugin['info'] = 'status messages and information of vcdeploy environment';
$plugin['root_only'] = false;

/**
 * Class VcdeployPluginStatus
 */
class VcdeployPluginStatus extends Vcdeploy implements IVcdeployPlugin
{
    /**
     * This function is run with the command
     *
     * @return int
     * @see vcdeploy#run()
     */
    public function run()
    {
        $this->msg("Version:\t\t" . $this->version);
        $this->msg("SCM:\t\t\t" . $this->conf['source_scm']);
        $this->msg("System OS:\t\t" . $this->conf['system_os']);
        $this->msg("Config file:\t\t" . $this->conf['config_file']);
        $this->msg("System source:\t\t" . $this->_getSystemSourcesList());
        $this->msg("Backup dir:\t\t" . $this->conf['backup_dir'] . $this->_checkDir($this->conf['backup_dir']));
        $this->msg("Project (active):\t" . count($this->get_projects(true)));
        $this->msg("Project (all):\t\t" . count($this->get_all_projects()));

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
     * Get system_source list string
     *
     * @return string
     */
    private function _getSystemSourcesList()
    {
        if (is_array($this->conf['system_source'])) {
            return implode(', ', $this->conf['system_source']);
        } else {
            return $this->conf['system_source'];
        }
    }

    /**
     * Check directory
     * - if it exist
     * - if it is a directory
     * - if vcdeloy has write permission
     *
     * @param string $dir
     * @return string if a problem exist, message is returned
     */
    private function _checkDir($dir)
    {
        $msg = '';

        if (!file_exists($dir)) {
            $msg = 'does not exist';
        } elseif (!is_dir($dir)) {
            $msg = 'is not a directory';
        } elseif (!is_writable($dir)) {
            $msg = 'is not writable';
        }

        if (empty($msg)) {
            return $msg;
        } else {
            return ' (' . $msg . ')';
        }
    }
}
