<?php
/**
 * Backup rsync
 *
 * PHP version 5.3
 *
 * @category  Plugins
 * @package   Vcdeploy
 * @author    Alexander Meindl <a.meindl@alphanodes.com>
 * @copyright 2014 Alexander Meindl
 * @license   http://www.mozilla.org/MPL Mozilla Public License Version 1.1
 * @link      https://github.com/alexandermeindl/vcdeploy
 */

$plugin['info'] = 'Rsync backup files with remote system';
$plugin['root_only'] = false;

class VcdeployPluginBackupRsync extends Vcdeploy implements IVcdeployPlugin
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
        if (!isset($this->conf['backup_remote_host']) || empty($this->conf['backup_remote_host'])) {
            throw new Exception('backup_remote_host is not specified!');
        } elseif (!isset($this->conf['backup_remote_dir']) || empty($this->conf['backup_remote_dir'])) {
            throw new Exception('backup_remote_dir is not specified!');
        }

        $this->msg('Sync data between ' . $this->hostname . ' and ' . $this->conf['backup_remote_host'] . '...');

        $remote_dir = $this->conf['backup_remote_host'] . ':' . $this->conf['backup_remote_dir'] . '/';
        $command = $this->conf['rsync_bin'] . ' -e ssh -avzp --exclude "*.journal" --exclude ".nfs*" --exclude "*.tar" --delete';

        $rc = $this->system($command . ' ' . $this->conf['backup_dir'] . '/ ' . $remote_dir);

        return $rc['rc'];
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
}
