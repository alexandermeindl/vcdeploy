<?php
/**
 * Update system files and configuration of system services
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

$plugin['info'] = 'rollout system files/configuration';
$plugin['root_only'] = true;

$plugin['options']['without_packages'] = array(
    'short_name' => '-W',
    'long_name' => '--without_packages',
    'action' => 'StoreTrue',
    'description' => 'Don\'t run package commands: depends and conflicts',
);

$plugin['options']['without_commands'] = array(
    'short_name' => '-C',
    'long_name' => '--without_commands',
    'action' => 'StoreTrue',
    'description' => 'Don\'t run pre_commands and post_commands',
);

$plugin['options']['without_permissions'] = array(
    'short_name' => '-P',
    'long_name' => '--without_permissions',
    'action' => 'StoreTrue',
    'description' => 'Don\'t set permissions',
);

$plugin['options']['force'] = array(
    'short_name' => '-f',
    'long_name' => '--force',
    'action' => 'StoreTrue',
    'description' => 'Overwrite files, even if existing files are newer than source files',
);

/**
 * Class VcdeployPluginRolloutSystem
 */
class VcdeployPluginRolloutSystem extends Vcdeploy implements IVcdeployPlugin
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
        if (empty($this->conf['system_source'])) {
            throw new Exception('system_source not specified.');
        } else {

            // convert strings to array
            if (!is_array($this->conf['system_source'])) {
                $this->conf['system_source'] = array('main' => $this->conf['system_source']);
            }

            foreach ($this->conf['system_source'] AS $system_name => $system_source) {

                if (!file_exists($system_source)) {
                    throw new Exception($system_source . ' does not exist');
                } elseif (!is_dir($system_source)) {
                    throw new Exception($system_source . ' is not a directory');
                }

                chdir($system_source);

                if ($this->conf['source_scm'] == 'static') {
                    // do nothing
                    $this->progressbar_init(0);
                } else {
                    // update source
                    $this->progressbar_init(1);

                    $this->show_progress('Get repository updates...');
                    // initialize scm
                    $this->set_scm('system');
                    $this->system($this->scm->update(), true);
                }

                // update system
                $this->show_progress('Update system files...');
                $this->_copyFiles($system_source);
            }
        }

        // System package support
        $this->_createDirectories();
        $this->_createSymlinks();

        if (!isset($this->paras->command->options['without_packages']) || !$this->paras->command->options['without_packages']) {
            $this->_packageDepends();
            $this->_packageConflicts();
            $this->_packageGemDepends();
        }
        $this->_modsConfig();
        $this->_vhostsConfig();
        $this->_nginxConfig();
        $this->_servicesConfig();

        if (!isset($this->paras->command->options['without_permissions']) || !$this->paras->command->options['without_permissions']) {
            $this->_setSystemPermissions();
        }

        $this->runHooks('pre');

        $this->_service('reload');
        $this->_service('restart');

        $this->runHooks('post');

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
        // 1.  system file update
        if (is_array($this->conf['system_source'])) {
            $init += count($this->conf['system_source']);
        } else {
            $init++;
        }

        $init += $this->_createDirectories(true);
        $init += $this->_createSymlinks(true);

        if (!isset($this->paras->command->options['without_packages']) || !$this->paras->command->options['without_packages']) {
            // 2. Update package sources and add required system packages
            $init += 2;

            // 3. Remove unwanted system packages
            $init += 1;

            // gem package
            $init += 1;
        }

        // 4. modsConfig
        $init += $this->_modsConfig(true);

        // 5. vhostsConfig
        $init += $this->_vhostsConfig(true);

        // 6. nginxConfig
        $init += $this->_nginxConfig(true);

        // 7. serviceConfig
        $init += $this->_servicesConfig(true);

        // 8. preCommands
        $init += $this->runHooks('pre', true);

        // 9. serviceReload
        $init += $this->_service('reload', true);

        // 10. serviceRestart
        $init += $this->_service('restart', true);

        if (!isset($this->paras->command->options['without_permissions']) || !$this->paras->command->options['without_permissions']) {
            foreach ($this->conf['permissions'] AS $permission) {
                if (isset($permission['mod']) && !empty($permission['mod'])) {
                    $init++;
                }
                if (isset($permission['own']) && !empty($permission['own'])) {
                    $init++;
                }
            }
        }

        // 11. postCommands
        $init += $this->runHooks('post', true);

        return $init;
    }

    /**
     * Copy system files
     *
     * @param string $system_source
     */
    private function _copyFiles($system_source)
    {
        $tmp_dir = '';

        if ($this->conf['source_scm'] == 'svn') {

            // temporary directory for SVN copy
            $tmp_dir = $this->conf['tmp_dir'] . '/' . uniqid('vcdeploy_svn_cp_');
            if ($tmp_dir != '/' && file_exists($tmp_dir)) {
                $this->system('rm -rf ' . $tmp_dir, true);
            }

            // Copy files to temporary location
            $this->system($this->conf['cp_bin'] . ' -r . ' . $tmp_dir, true);

            // Remove SVN directories
            $this->system('find "' . $tmp_dir . '/" -name ".svn" -type d -exec rm -rf {} 2>/dev/null \;', true);

            // Cleanup Mac OS X files
            $this->system('find "' . $tmp_dir . '/" -name ".DS_Store" -type f -exec rm -f {} \;', true);

            // Switch to temporary directory
            chdir($tmp_dir);
        } else if (getcwd() != $system_source) {
            // Cleanup Mac OS X files
            $this->system('find "' . $system_source . '/" -name ".DS_Store" -type f -exec rm -f {} \;', true);
            // Switch to source directory
            chdir($system_source);
        }

        // Copy files
        if (isset($this->paras->command->options['force']) && $this->paras->command->options['force']) {
            $this->system($this->conf['cp_bin'] . ' -r . /', true);
        } else {
            $this->system($this->conf['cp_bin'] . ' -ru . /', true);
        }

        // Clean up
        if (!empty($tmp_dir) && $tmp_dir != '/' && file_exists($tmp_dir)) {
            $this->system('rm -rf ' . $tmp_dir, true);
        }
        if (getcwd() != $system_source) {
            chdir($system_source);
        }
    }

    /**
     * Configure vhosts: enable or disable vhosts
     *
     * @param bool $try if true, this is a test run without system calls
     *
     * @return int amount of system commands
     */
    private function _vhostsConfig($try = false)
    {

        if (!isset($this->conf['apache_sites'])) {
            $this->conf['apache_sites'] = '';
        }
        if (!isset($this->conf['apache_sites_enable'])) {
            $this->conf['apache_sites_enable'] = '';
        }

        return $this->_activationRun(
            $this->conf['apache_sites'],
            $this->conf['apache_sites_enable'],
            'vhost_enable',
            'Enable vhost',
            'vhost_disable',
            'Disable vhost',
            $try
        );
    }

    /**
     * Configure nginx sites: enable or disable vhosts
     *
     * @param bool $try if true, this is a test run without system calls
     *
     * @return int amount of system commands
     */
    private function _nginxConfig($try = false)
    {

        if (!isset($this->conf['nginx_sites'])) {
            $this->conf['nginx_sites'] = '';
        }
        if (!isset($this->conf['nginx_sites_enable'])) {
            $this->conf['nginx_sites_enable'] = '';
        }

        return $this->_activationRun(
            $this->conf['nginx_sites'],
            $this->conf['nginx_sites_enable'],
            'nginx_enable',
            'Enable nginx site',
            'nginx_disable',
            'Disable nginx site',
            $try
        );
    }

    /**
     * Apache modules configuration: enable or disable a module
     *
     * @param bool $try if true, this is a test run without system calls
     *
     * @return int amount of system commands
     */
    private function _modsConfig($try = false)
    {
        if (!isset($this->conf['apache_mods'])) {
            $this->conf['apache_mods'] = '';
        }
        if (!isset($this->conf['apache_mods_enable'])) {
            $this->conf['apache_mods_enable'] = '';
        }

        return $this->_activationRun(
            $this->conf['apache_mods'],
            $this->conf['apache_mods_enable'],
            'mod_enable',
            'Enable apache module',
            'mod_disable',
            'Disable apache module',
            $try
        );
    }

    /**
     * Configure services: enable or disable services
     *
     * @param bool $try if true, this is test run without system calls
     *
     * @return int amount of system commands
     */
    private function _servicesConfig($try = false)
    {
        if (!isset($this->conf['services'])) {
            $this->conf['services'] = '';
        }
        if (!isset($this->conf['services_enable'])) {
            $this->conf['services_enable'] = '';
        }

        return $this->_activationRun(
            $this->conf['services'],
            $this->conf['services_enable'],
            'service_enable',
            'Enable service',
            'service_disable',
            'Disable service',
            $try
        );
    }


    /**
     * Configure internal subroutine
     *
     * @param string $all_string
     * @param string $active_string
     * @param string $enable_command
     * @param string $enable_message
     * @param string $disable_command
     * @param string $disable_message
     * @param bool $try if true, this is test run without system calls
     *
     * @return int amount of system commands
     */
    private function _activationRun($all_string, $active_string, $enable_command, $enable_message, $disable_command, $disable_message, $try = false)
    {
        $count = 0;

        // Enable and disable
        if (!empty($all_string)) {
            $all = explode(' ', $all_string);
            $enable = array();
            if (!empty($active_string)) {
                $enable = explode(' ', $active_string);
            }

            if (is_array($all)) {
                foreach ($all AS $entry) {
                    if (!empty($entry)) {
                        if (!$try) {
                            if (in_array($entry, $enable)) {
                                $this->show_progress($enable_message . ' ' . $entry . '...');
                                $this->_systemCommand($enable_command, $entry);
                            } else {
                                $this->show_progress($disable_message . ' ' . $entry . '...');
                                $this->_systemCommand($disable_command, $entry);
                            }
                        }

                        // Count runs
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Restart services
     *
     * @param string $command command for init script (e.g. reload or restart)
     * @param bool $try if true, this is test run without system calls
     *
     * @return int amount of system commands
     */
    private function _service($command, $try = false)
    {
        $count = 0;
        $config_key = 'services_' . $command;

        if (!empty($this->conf[$config_key])) {
            $services = explode(' ', $this->conf[$config_key]);
            if (is_array($services)) {
                foreach ($services AS $service) {
                    if (!$try) {
                        $this->show_progress(ucfirst($command) . ' service ' . $service . '...');
                        $this->_systemCommand($command, $service);
                    }
                    // Count runs
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Run defined command system independent
     *
     * @param string $command = restart, reload
     *                            service_enable, service_disable,
     *                            vhost_enable, vhost_disable,
     *                            mod_enable, mod_disable
     * @param string $para
     *
     * @return int
     */
    private function _systemCommand($command, $para = null)
    {
        $rc = 0;

        switch ($command) {

            case 'service_enable':
                if ($this->conf['system_os'] == 'suse') {
                    $rc = $this->system('chkconfig --add ' . $para);
                } elseif ($this->conf['system_os'] == 'centos') {
                    $rc = $this->system('chkconfig --add ' . $para);
                } else { // debian or ubuntu
                    $rc = $this->system('update-rc.d ' . $para . ' defaults');
                }
                // gentoo
                // rc-update add $para default
                break;

            case 'service_disable':
                if ($this->conf['system_os'] == 'suse') {
                    $rc = $this->system('chkconfig --del ' . $para);
                } else if ($this->conf['system_os'] == 'centos') {
                    $rc = $this->system('chkconfig --del ' . $para);
                } else { // debian or ubuntu
                    $rc = $this->system('update-rc.d -f  ' . $para . ' remove');
                }
                // gentoo
                // rc-update del $para
                break;

            case 'vhost_enable':
                if ($this->conf['system_os'] == 'debian' || $this->conf['system_os'] == 'ubuntu') {
                    $rc = $this->system('a2ensite -q  ' . $para);
                } else {
                    $this->msg('vhost configuration not supported with ' . $this->conf['system_os'] . '.', 0, 'warning');
                    $rc = 1;
                }
                break;

            case 'vhost_disable':
                if ($this->conf['system_os'] == 'debian' || $this->conf['system_os'] == 'ubuntu') {
                    $rc = $this->system('a2dissite -q  ' . $para);
                } else {
                    $this->msg('vhost configuration not supported with ' . $this->conf['system_os'] . '.', 0, 'warning');
                    $rc = 1;
                }
                break;

            case 'nginx_enable':
                // suported with nginx version 1.8.0 or higher from dotdeb
                $rc = $this->system('ngx-conf --enable ' . $para);
                break;

            case 'nginx_disable':
                // suported with nginx version 1.8.0 or higher from dotdeb
                $rc = $this->system('ngx-conf --disable ' . $para);
                break;

            case 'mod_enable':
                if ($this->conf['system_os'] == 'debian' || $this->conf['system_os'] == 'ubuntu') {
                    $rc = $this->system('a2enmod -q  ' . $para);
                } else {
                    $this->msg('apache modules configuration not supported with ' . $this->conf['system_os'] . '.', 0, 'warning');
                    $rc = 1;
                }
                break;

            case 'mod_disable':
                if ($this->conf['system_os'] == 'debian' || $this->conf['system_os'] == 'ubuntu') {
                    $rc = $this->system('a2dismod -q  ' . $para);
                } else {
                    $this->msg('apache modules configuration not supported with ' . $this->conf['system_os'] . '.', 0, 'warning');
                    $rc = 1;
                }
                break;

            case 'restart':
                if ($this->conf['system_os'] == 'suse') {
                    $rc = $this->system('rc' . $para . ' restart');
                } elseif ($this->conf['system_os'] == 'centos') {
                    $rc = $this->system('service ' . $para . ' restart');
                } elseif ($this->conf['system_os'] == 'ubuntu') {
                    $rc = $this->system('service ' . $para . ' restart');
                } else { // debian
                    $rc = $this->system('invoke-rc.d ' . $para . ' restart');
                }
                break;

            case 'reload':
                if ($this->conf['system_os'] == 'suse') {
                    $rc = $this->system('rc' . $para . ' reload');
                } elseif ($this->conf['system_os'] == 'centos') {
                    $rc = $this->system('service ' . $para . ' reload');
                } elseif ($this->conf['system_os'] == 'ubuntu') {
                    $rc = $this->system('service ' . $para . ' reload');
                } else { // debian
                    $rc = $this->system('invoke-rc.d ' . $para . ' reload');
                }
                break;

            default:
                $this->msg('Unknown systemCommand ' . $command . ' used!', 0, 'warning');
                break;
        }

        return $rc;
    }

    /**
     * Update package sources and install system packages
     *
     * @return int
     * @throws Exception
     */
    private function _packageDepends()
    {
        if (!empty($this->conf['packages_depends'])) {
            $this->show_progress('First update to latest packages...');
            switch ($this->conf['system_os']) {
                case 'suse':
                    $this->show_progress('Check for SuSE packages to install...');
                    $rc = $this->system('zypper --non-interactive install ' . $this->conf['packages_depends'], true);
                    break;
                case 'centos':
                    $this->show_progress('Check for Redhat packages to install...');
                    $rc = $this->system('yum install -y ' . $this->conf['packages_depends'], true);
                    break;
                case 'ubuntu':
                    $rc = $this->system('aptitude update', true);
                    if (!$rc['rc']) {
                        $this->show_progress('Check for Ubuntu packages to install...');
                        $rc = $this->system('aptitude install -y ' . $this->conf['packages_depends'], true);
                    }
                    break;
                case 'debian':
                    $rc = $this->system('apt-get -qq update', true);
                    if (!$rc['rc']) {
                        $this->show_progress('Check for Debian packages to install...');
                        $rc = $this->system('apt-get install -yqq ' . $this->conf['packages_depends'], true);
                    }
                    break;
                default:
                    throw new Exception('Package depends system not support on this platform');
            }

            if ($rc['rc']) {
                if (!empty($rc['output'])) {
                    $this->msg('An error occured while installing packages:', 0, 'warning');
                    foreach ($rc['output'] AS $line) {
                        $this->msg($line);
                    }
                } else {
                    $this->msg('An error occured while installing packages (rc=' . $rc['rc'] . ')', 0, 'warning');
                }
                return $rc['rc'];
            }
        }

        return 0;
    }

    /**
     * Update package sources and install system packages
     *
     * @return int
     * @throws Exception
     */
    private function _packageConflicts()
    {
        if (!empty($this->conf['packages_conflicts'])) {
            $this->show_progress('Check for linux packages to remove...');

            switch ($this->conf['system_os']) {
                case 'suse':
                    $rc = $this->system('zypper --non-interactive remove ' . $this->conf['packages_conflicts'], true);
                    break;
                case 'centos':
                    $rc = $this->system('yum uninstall -y ' . $this->conf['packages_conflicts'], true);
                    break;
                case 'ubuntu':
                    $rc = $this->system('aptitude remove -y ' . $this->conf['packages_conflicts'], true);
                    break;
                case 'debian':
                    $rc = $this->system('apt-get --purge remove -yqq ' . $this->conf['packages_conflicts'], true);
                    break;
                default:
                    throw new Exception('Package depends system not support on this platform');
                    break;
            }

            if ($rc['rc']) {
                if (!empty($rc['output'])) {
                    $this->msg('An error occured while installing packages:', 0, 'warning');
                    foreach ($rc['output'] AS $line) {
                        $this->msg($line);
                    }
                } else {
                    $this->msg('An error occured while installing packages (rc=' . $rc['rc'] . ')', 0, 'warning');
                }
                return $rc['rc'];
            }
        }

        return 0;
    }

    /**
     * Install gem packages
     *
     * @throws Exception
     * @return int amount of system commands
     */
    private function _packageGemDepends()
    {
        if (!empty($this->conf['gem_packages_depends'])) {

            $this->show_progress('Install ruby gem packages...');
            $rc = $this->system('gem install ' . $this->conf['gem_options'] . ' ' . $this->conf['gem_packages_depends'], true);

            if ($rc['rc']) {
                if (!empty($rc['output'])) {
                    $this->msg('An error occured while installing gem packages:', 0, 'warning');
                    foreach ($rc['output'] AS $line) {
                        $this->msg($line, 0, 'warning');
                    }
                } else {
                    $this->msg('An error occured while installing gem packages (rc=' . $rc['rc'] . ')', 0, 'warning');
                }
                return $rc['rc'];
            }
        }

        return 0;
    }

    /**
     * Create directories
     *
     * @param bool $try if true, this is a test run without system calls
     * @throws Exception
     * @return int amount of system commands
     */
    private function _createDirectories($try = false)
    {
        $rc = 0;

        if (isset($this->conf['dirs']) &&
            is_array($this->conf['dirs']) &&
            count($this->conf['dirs'])
        ) {

            foreach ($this->conf['dirs'] AS $dir) {
                $rc++;
                if (!$try) {
                    if (file_exists($dir)) {
                        $this->show_progress('Directory ' . $dir . ' already exists.');
                    } else {
                        $this->show_progress('Creating directory ' . $dir);
                        mkdir($dir, 0775, true);
                    }
                }
            }
        }

        return $rc;
    }

    /**
     * Create symbolic links
     *
     * @param bool $try if true, this is a test run without system calls
     * @throws Exception
     * @return int amount of system commands
     */
    private function _createSymlinks($try = false)
    {
        $rc = 0;

        if (isset($this->conf['symlinks']) &&
            is_array($this->conf['symlinks']) &&
            count($this->conf['symlinks'])
        ) {

            foreach ($this->conf['symlinks'] AS $target => $source) {
                $rc++;
                if (!$try) {
                    if (file_exists($target)) {
                        $this->show_progress('Symlink target ' . $target . ' already exists.');
                    } else {
                        $parent_dir = dirname($target);
                        $basename = basename($target);
                        if (file_exists($parent_dir)) {
                            // change to parent directory
                            $current_dir = getcwd();

                            try {
                                chdir($parent_dir);
                            } catch (Exception $e) {
                                echo $e->getMessage();
                            }

                            $this->show_progress('Creating symlink ' . $source . ' => ' . $target);
                            $rc = $this->system('ln -s ' . $source . ' ' . $basename);
                            chdir($current_dir);
                            if ($rc['rc']) {
                                throw new Exception('Error creating symlink "' . $target . '"');
                            }
                        } else {
                            $this->msg('Cannot create symlink ' . $target . ', because parent directory does not exist.', 0, 'warning');
                        }
                    }
                }
            }
        }
        return $rc;
    }

    /**
     * Set system permissions for files and directories
     *
     * @throws Exception
     */
    private function _setSystemPermissions()
    {
        if (is_array($this->conf['permissions']) && count($this->conf['permissions'])) {
            foreach ($this->conf['permissions'] AS $permission) {
                if (isset($permission['mod']) && !empty($permission['mod'])) {
                    $this->set_permissions('mod', $permission);
                }
                if (isset($permission['own']) && !empty($permission['own'])) {
                    $this->set_permissions('own', $permission);
                }
            }
        }
    }
}
