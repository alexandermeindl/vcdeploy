<?php
/**
 * Plugin to setup drupal installation
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

$plugin['info'] = 'Setup a drupal installation';
$plugin['root_only'] = true;

$plugin['args']['project'] = 'Name of project to setup';
$plugin['args']['command'] = 'Name of subcommand
- reset-files: clear private and public download directories
- reset-settings: copy drupal settings.php to target directory
- reset-db: drop and create database to cleanup
- install: install drupal
- reset: run \'reset-settings\', \'reset-files\' and \'reset-db\'
- reinstall: run \'reset-settings\', \'reset-files\' and \'install\'';

$plugin['options']['without_permission'] = array(
    'short_name' => '-P',
    'long_name' => '--without_permission',
    'action' => 'StoreTrue',
    'description' => 'Do not apply specified permissions',
);

$plugin['options']['without_commands'] = array(
    'short_name' => '-C',
    'long_name' => '--without_commands',
    'action' => 'StoreTrue',
    'description' => 'Don\'t run pre_commands and post_commands',
);

/**
 * Class VcdeployPluginSetupDrupal
 */
class VcdeployPluginSetupDrupal extends Vcdeploy implements IVcdeployPlugin
{
    /**
     * Drupal base directory
     *
     * This is relative to project path
     *
     * @var string
     */
    private $drupal_base_dir = 'htdocs';

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

        $project_name = $this->paras->command->args['project'];
        $command = $this->paras->command->args['command'];
        $this->set_project($project_name, $this->get_project($project_name));

        if (!isset($this->project['setup_drupal']['install_profile'])) {
            throw new Exception('install_profile is not configured');
        }

        if (isset($this->project['setup_drupal']['base_dir'])) {
            $this->drupal_base_dir = $this->project['setup_drupal']['base_dir'];
        }

        $commands = array('reset-files', 'reset-settings', 'reset-db', 'install', 'reinstall', 'reset');

        if (in_array($command, $commands)) {
            $method = 'run' . str_replace(' ', '', ucwords(str_replace('-', ' ', $command)));
            $this->$method();
        } else {
            throw new Exception('Subcommand \'' . $command . '\' unknown.');
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

        // pre commands
        $init += $this->runHooks('pre', true);

        // post commands
        $init += $this->runHooks('post', true);

        return ++$init;
    }

    /**
     * Reset drupal files directories
     *
     * Reset all data directories of a drupal projects (private and public download directories)
     *
     * @throws Exception
     */
    private function runResetFiles()
    {
        $this->msg('Run reset files directories...');

        foreach ($this->project['data_dir'] AS $identifier => $dir) {

            $this->msg('Deleting all files in ' . $dir . '...');

            // 1. Remove directory
            if (file_exists($dir)) {
                $this->remove_directory($dir);
            }

            // 2. Recreate directory
            if (!mkdir($dir, 0755)) {
                throw new Exception('Failed to create data directory: ' . $dir);
            }

            // 3. detect which .htaccess content has to be written
            // If data directory is within drupal base directory, private access is set - otherwise public access
            $base_dir = $this->project['path'] . '/' . $this->drupal_base_dir;
            if (substr_count($dir, $base_dir) && substr($identifier, 0, 7) != 'private') {
                // within drupal document root - > public directory
                $htaccess_content = "SetHandler Drupal_Security_Do_Not_Remove_See_SA_2006_006\nOptions None\nOptions +FollowSymLinks\n";
            } else {
                // outsite drupal document root - > private directory
                $htaccess_content = "SetHandler Drupal_Security_Do_Not_Remove_See_SA_2006_006\nDeny from all\nOptions None\nOptions +FollowSymLinks\n";
            }
            // 4. Add .htaccess file
            if (!file_put_contents($dir . '/.htaccess', $htaccess_content)) {
                throw new Exception('Failed to write .htaccess file to ' . $dir . '/.htaccess');
            }
        }
    }

    /**
     * Reset drupal settings.php
     *
     * Copy settings.php of a source directory to the sites directory
     * of drupal installation
     *
     * @throws Exception
     */
    private function runResetSettings()
    {
        $this->msg('Run reset settings...');

        $site_dir = $this->project['path'] . '/' . $this->drupal_base_dir . '/sites/' . $this->getSitesSubdir();
        $settings_php = $site_dir . '/settings.php';

        // 1. Create site directory, if directory does not exist

        if (!file_exists($site_dir)) {
            $this->msg('Creating site directory: ' . $site_dir);
            if (!mkdir($site_dir, 0755)) {
                throw new Exception('Failed to create site directory: ' . $site_dir);
            }
        }

        // only run the following steps, if settings.php source is configured
        if (isset($this->project['setup_drupal']['settings_source'])) {

            // 2. check if source file is provided
            if (!file_exists($this->project['setup_drupal']['settings_source'])) {
                throw new Exception('settings.php source file does not exists: ' . $this->project['setup_drupal']['settings_source']);
            }

            // 3. make sure write permissions on target directory/file
            if (!chmod($site_dir, 0775)) {
                throw new Exception('chmod failed to drupal site directory: ' . $site_dir);
            }
            if (file_exists($settings_php) && !chmod($settings_php, 0664)) {
                throw new Exception('chmod failed to drupal settings.php: ' . $settings_php);
            }

            // 4. copy file
            if (!copy($this->project['setup_drupal']['settings_source'], $settings_php)) {
                throw new Exception('Failed to copy settings_source to drupal site directory!');
            }
        }
    }

    /**
     * Reset drupal database
     *
     * @throws Exception
     */
    private function runResetDb()
    {
        $this->msg('Run reset db...');

        foreach ($this->project['db'] AS $identifier => $db) {
            $this->db_recreate($db);
        }
    }

    /**
     * Run drupal installation with drush
     *
     */
    private function runInstall()
    {
        $this->msg('Run drupal installation of ' . $this->project_name . '...');

        // 1. Run pre commands
        $this->runHooks('pre');

        // 2. Run install
        $command = '[drush] --yes si ' . $this->project['setup_drupal']['install_profile'] . ' ' . $this->getDrushParas();
        $this->installCommands(array($command));

        // 3. Run post commands
        $this->runHooks('post');

        // 4. Set file permissions (has to be after post commands to make sure all created files are affected)
        if ($this->is_permission_required()) {
            if (isset($this->project['permissions']) && is_array($this->project['permissions'])) {
                foreach ($this->project['permissions'] AS $permission) {
                    if (isset($permission['mod']) && !empty($permission['mod'])) {
                        $this->set_permissions('mod', $permission, $this->project['path']);
                    }
                    if (isset($permission['own']) && !empty($permission['own'])) {
                        $this->set_permissions('own', $permission, $this->project['path']);
                    }
                }
            }
        }
    }

    /**
     * Get drush parameters of definied settings
     */
    private function getDrushParas()
    {
        $drush_para = '';
        $paras = array('account-name', 'account-pass', 'account-mail', 'sites-subdir', 'site-name', 'site-mail', 'clean-url');

        foreach ($paras AS $key) {
            $vc_key = str_replace('-', '_', $key);
            if (isset($this->project['setup_drupal'][$vc_key])) {
                if ($key == 'sites-subdir') {
                    $drush_para .= ' --' . $key . '=' . $this->getSitesSubdir();
                } else {
                    $drush_para .= ' --' . $key . '="' . $this->project['setup_drupal'][$vc_key] . '"';
                }
            }
        }

        return $drush_para;
    }

    /**
     * Run reinstall of drupal
     *
     * 1. $this->runResetSettings()
     * 2. $this->runResetFiles()
     * 3. $this->runInstall()
     *
     * @see $this->runResetSettings, $this->runResetFiles, $this->runInstall
     */
    public function runReinstall()
    {
        $this->runResetSettings();
        $this->runResetFiles();
        // $this->runResetDb(); // not required, because database dropped with drush
        $this->runInstall();
    }

    /**
     * Execute all reset commands
     *
     * 1. $this->runResetSettings()
     * 2. $this->runResetFiles()
     * 3. $this->runResetDb()
     *
     * @see $this->runResetSettings, $this->runResetFiles, $this->runResetDb
     */
    public function runReset()
    {
        $this->runResetSettings();
        $this->runResetFiles();
        $this->runResetDb();
    }

    private function getSitesSubdir()
    {
        if (isset($this->project['setup_drupal']['sites_subdir'])) {
            return $this->project['setup_drupal']['sites_subdir'];
        } else {
            return 'default';
        }
    }
}
