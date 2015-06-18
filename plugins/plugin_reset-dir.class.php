<?php
/**
 * Plugin to reset a directory
 *
 * This is useful, if you want to fetch a copy an extern installatio
 * to your local developer environment.
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

$plugin['info'] = 'Reset directory. If no project is specified, all active projects files/directories will be reseted.';
$plugin['root_only'] = false;

$plugin['options']['project'] = array(
    'short_name' => '-p',
    'long_name' => '--project',
    'action' => 'StoreString',
    'description' => 'Only reset data of this project',
);

$plugin['options']['with_backup'] = array(
    'short_name' => '-b',
    'long_name' => '--with_backup',
    'action' => 'StoreTrue',
    'description' => 'Create a backup before the sync (default with setting without_backup=false)',
);

$plugin['options']['without_backup'] = array(
    'short_name' => '-B',
    'long_name' => '--without_backup',
    'action' => 'StoreTrue',
    'description' => 'Do not create a backup before the sync (default with setting without_backup=true)',
);

$plugin['options']['without_commands'] = array(
    'short_name' => '-C',
    'long_name' => '--without_commands',
    'action' => 'StoreTrue',
    'description' => 'Don\'t run pre_commands and post_commands',
);

$plugin['options']['without_permission'] = array(
    'short_name' => '-P',
    'long_name' => '--without_permission',
    'action' => 'StoreTrue',
    'description' => 'Do not apply specified permissions',
);

/**
 * Class VcdeployPluginResetDir
 */
class VcdeployPluginResetDir extends Vcdeploy implements IVcdeployPlugin
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
        // check for existing projects
        $this->validate_projects();

        // check backup directory if exists and is writable
        if ($this->is_backup_required()) {
            $this->prepare_backup_dir();
        }

        if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
            $project_name = $this->paras->command->options['project'];
            $this->set_project($project_name, $this->get_project($project_name));
            $this->msg('Project: ' . $this->project_name);
            $this->_resetDir();
        } else {
            foreach ($this->projects AS $project_name => $project) {
                $this->set_project($project_name, $project);

                $this->msg('Project: ' . $this->project_name);
                $this->_resetDir();
            }
        }

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
        if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
            $rc = 1;
        } else {
            $rc = count($this->projects);
        }

        // with backup
        if ($this->is_backup_required()) {
            $rc *= 2;
        }

        // pre commands
        $init += $this->runHooks('pre', true);

        // with permissions
        if ($this->is_permission_required()) {
            if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
                $project_name = $this->paras->command->options['project'];
                $rc += $this->count_project_permissions($this->projects[$project_name]);
            } else {
                foreach ($this->projects AS $project_name => $project) {
                    $rc += $this->count_project_permissions($project);
                }
            }
        }

        // post commands
        $init += $this->runHooks('post', true);

        return $init + $rc;
    }

    /**
     * Reset directory with content to specified source
     *
     * @return bool
     * @throws Exception
     */
    private function _resetDir()
    {
        if (isset($this->project['data_dir'])) {

            // Pre commands
            $this->runHooks('pre');

            foreach ($this->project['data_dir'] AS $identifier => $dir) {

                $tar_file = $this->get_source_data_file($identifier, $this->get_source_data($identifier));

                if (!empty($tar_file)) {

                    // 1. create backup of existing data
                    if ($this->is_backup_required()) {
                        $this->create_project_data_backup();
                    } else {
                        $this->msg('Backup deactivated.');
                    }

                    // 2. remove existing target directory
                    if (file_exists($dir)) {
                        $this->remove_directory($dir);
                    }

                    // 3. Restore Tar file
                    chdir(dirname($dir)); // go to parent directory
                    $this->system($this->conf['tar_bin'] . ' -xz --no-same-owner -f ' . $tar_file);

                    // 4. Set file permissions (has to be after post commands to make sure all created files are affected)
                    if ($this->is_permission_required()) {
                        if (isset($this->project['permissions']) && is_array($this->project['permissions'])) {
                            if ($this->current_user != 'root') {
                                throw new Exception('permission commands requires to run script with root privileges for project ' . $this->project_name);
                            }
                            foreach ($this->project['permissions'] AS $permission) {
                                if (isset($permission['mod']) && !empty($permission['mod'])) {
                                    $this->set_permissions('mod', $permission, $this->project['path']);
                                }
                                if (isset($permission['own']) && !empty($permission['own'])) {
                                    $this->set_permissions('own', $permission, $this->project['path']);
                                }
                            }
                        }
                    } else {
                        $this->msg('Directory ' . $identifier . ' has been successfully restored.');
                    }

                    $this->msg('Directory ' . $identifier . ' has been successfully restored.');
                } else {
                    $this->msg('TAR file for reset could not be identify', 0, 'warning');
                }

                // Post commands
                $this->runHooks('post');

                if ($this->project['source_type'] == 'local') {
                    // cleanup tar file
                    unlink($tar_file);
                }
            }
        } else {
            $this->msg('Project ' . $this->project_name . ': no data_dir has been specified.');
        }
    }
}
