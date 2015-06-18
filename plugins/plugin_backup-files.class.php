<?php
/**
 * Backup for projects and independent files/directory
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

$plugin['info'] = 'Create backup of files';
$plugin['root_only'] = false;

class VcdeployPluginBackupFiles extends Vcdeploy implements IVcdeployPlugin
{
    /**
     * This function is run with the command
     *
     * @return int
     * @see vcdeploy#run()
     */
    public function run()
    {
        $this->msg('Run backups...');

        // check backup directory if exists and is writable
        $this->prepare_backup_dir();

        return $this->_backupFiles();
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
        $backups = count($this->conf['backup_daily']);

        // backups x 2 because of gzip compression
        return $init + $backups * 2;
    }

    /**
     * Create backup of specified directories
     *
     * @return int amount of errors
     */
    private function _backupFiles()
    {
        $rc = 0;

        if (is_array($this->conf['backup_daily'])) {

            $this->progressbar_init();

            foreach ($this->conf['backup_daily'] AS $name => $values) {

                if (!isset($values['dir'])) {
                    $this->msg('Missing dir for ' . $name . ' backup set!');
                    $rc++;
                } elseif (!file_exists($values['dir'])) {
                    $this->msg('Backup target directory does not exist: ' . $values['dir']);
                    $rc++;
                } else {

                    if (!isset($values['excludes'])) {
                        $values['excludes'] = array();
                    }

                    if (isset($values['multi']) && ($values['multi'])) { // MULTI

                        if (!isset($values['multi_excludes'])) {
                            $values['multi_excludes'] = array();
                        }

                        $source_dirs = $this->_getMultiSourceDirs($values['dir'], $values['multi_excludes']);
                        foreach ($source_dirs AS $source_name => $source_dir) {
                            $backup_name = $name . '_' . $source_name;
                            $this->create_data_dump($source_dir, $this->_getTargetBackupFilename($backup_name), $values['excludes']);
                        }
                    } else {                                               // SINGLE
                        $this->create_data_dump($values['dir'], $this->_getTargetBackupFilename($name), $values['excludes']);
                    }
                }
            }
        }

        return $rc;
    }

    /**
     * Get filename for backup
     *
     * @param string $name
     *
     * @return string
     */
    private function _getTargetBackupFilename($name)
    {
        return $this->conf['backup_dir'] . '/' . $name . '-' . $this->date_stamp . '.tar';
    }

    /**
     * Get directories for multi mode
     *
     * @param string $source_dir
     * @param array $excludes
     *
     * @return array
     */
    private function _getMultiSourceDirs($source_dir, $excludes)
    {
        $dirs = array();

        $d = dir($source_dir);
        while (false !== ($entry = $d->read())) {
            if ($entry != '.' && $entry != '..' && is_dir($source_dir . '/' . $entry)) {
                $ok = true;
                reset($excludes);
                foreach ($excludes AS $exclude) {
                    if ($entry == $exclude) {
                        $ok = false;
                        break;
                    }
                }

                if ($ok) {
                    $dirs[$entry] = $source_dir . '/' . $entry;
                }
            }
        }
        $d->close();

        return $dirs;
    }
}
