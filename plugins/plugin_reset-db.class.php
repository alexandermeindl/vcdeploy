<?php
/**
 * Plugin to reset database
 *
 * This is useful, if you want to fetch a copy an extern
 * installation to your local developer environment.
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

$plugin['info'] = 'Reset database. If no project is specified, all active project databases will be reseted';
$plugin['root_only'] = false;

$plugin['options']['project'] = array(
    'short_name' => '-p',
    'long_name' => '--project',
    'action' => 'StoreString',
    'description' => 'Only reset database of this project',
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

/**
 * Class VcdeployPluginResetDb
 */
class VcdeployPluginResetDb extends Vcdeploy implements IVcdeployPlugin
{
    /**
     * This function is run with the command
     *
     * @return int
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
            $this->_resetDb();
        } else {
            foreach ($this->projects AS $project_name => $project) {
                $this->set_project($project_name, $project);

                $this->msg('Project: ' . $this->project_name);
                $this->_resetDb();
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
            $init = 1;
        } else {
            $init = count($this->projects);
        }

        // pre commands
        $init += $this->runHooks('pre', true);

        if (isset($this->project['db'])) {
            $init += count($this->project['db']);
        }

        // post commands
        $init += $this->runHooks('post', true);

        return $init;
    }

    /**
     * Reset database
     *
     * @return bool
     */
    private function _resetDb()
    {
        // initialize db
        $this->set_db();

        if (isset($this->project['db'])) {

            // set permissions (requires database admin permission)
            if (isset($this->project['reset_db']['permissions'])) {
                $perm = $this->project['reset_db']['permissions'];
                if (!is_array($perm)) {
                    $this->msg('Missing permissions array for databases.');
                }

                foreach ($this->project['db'] AS $identifier => $db) {
                    $this->system($this->db->get_user_drop($perm['host'], $perm['user']), true);
                    $this->system($this->db->get_user_create($perm['host'], $db, $perm['user'], $perm['password']), true);
                }
            }

            // run pre commands
            $this->runHooks('pre');

            foreach ($this->project['db'] AS $identifier => $db) {

                $sql_file = $this->get_remote_db_file($identifier, $this->get_source_db($identifier));

                if (!empty($sql_file)) {

                    // create backup of existing database
                    if ($this->is_backup_required()) {
                        if ($this->db_exists($db)) {
                            $this->create_db_dump($db);
                        } else {
                            $this->msg('Backup was not created, because database did not exist yet.');
                        }
                    } else {
                        $this->msg('Backup deactivated.');
                    }

                    // recreate database
                    $this->db_recreate($db);

                    $this->msg('Import data...');
                    $this->system($this->db->get_restore($db, $sql_file, true));

                    if (isset($this->project['reset_db']['with_db_sanitize']) && $this->project['reset_db']['with_db_sanitize']) {
                        $this->sanitize_database_sanitize($db);
                    }

                    $this->msg('Database ' . $db . ' has been successfully reseted.');

                    if ($this->project['source_type'] == 'local') {
                        // cleanup sql file
                        unlink($sql_file);
                    }
                } else {
                    $this->msg('SQL file for import could not be identify');
                }
            }

            // run post commands
            $this->runHooks('post');
        } else {
            $this->msg('Project ' . $this->project_name . ': no database has been specified.');
        }
    }
}
