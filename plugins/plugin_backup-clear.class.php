<?php
/**
 * Clear old backup sets
 *
 * PHP version 5.3
 *
 * @category  Plugins
 * @package   Vcdeploy
 * @author    Alexander Meindl <a.meindl@alphanodes.com>
 * @copyright 2015 Alexander Meindl
 * @license   http://www.mozilla.org/MPL Mozilla Public License Version 1.1
 * @link      https://github.com/alexandermeindl/vcdeploy
 */

$plugin['info'] = 'remove old backups';
$plugin['root_only'] = FALSE;

/**
 * Class VcdeployPluginBackupClear
 *
 */
class VcdeployPluginBackupClear extends Vcdeploy implements IVcdeployPlugin
{
    /**
     * This function is run with the command
     *
     * @return int
     * @throws Exception
     * @see vcdeploy#run()
     */
    public function run()
    {
        if (empty($this->conf['backup_dir'])) {
            throw new Exception('Backup directory not specified.');
        } elseif (!file_exists($this->conf['backup_dir'])) {
            throw new Exception('Backup directory does not exist.');
        }

        $this->progressbar_init();
        return $this->_clearBackups();
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
        return $init + 3;
    }

    /**
     * Clear existing backups
     *
     * @return int
     */
    private function _clearBackups()
    {
        $this->msg('Remove old backups on ' . $this->hostname . ':');

        $max_minutes = $this->conf['backup_max_days'] * 24 * 60;

        $this->show_progress('Removing old tar.gz files...');
        $this->system('find "' . $this->conf['backup_dir'] . '/" ! -mmin -' . $max_minutes . ' -name "*.tar.gz*" -type f -exec rm {} \;', true);
        $this->show_progress('Removing old sql.gz files...');
        $this->system('find "' . $this->conf['backup_dir'] . '/" ! -mmin -' . $max_minutes . ' -name "*sql.gz*" -type f -exec rm {} \;', true);

        // delete files, which no longer in use
        $this->show_progress('Removing tar files...');
        $rc = $this->system('find "' . $this->conf['backup_dir'] . '/" -name "*.tar" -type f -exec rm {} \;', true);

        return $rc['rc'];
    }
}
