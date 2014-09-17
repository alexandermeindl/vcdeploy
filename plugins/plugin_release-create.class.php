<?php
/**
 *   Plugin to create a project release
 *
 * Workflow:
 *
 *  - set a TAG
 *  (database)
 *  - create a database dump of specified database
 *  - sanitize database dump
 *  - create archive file of sql dump with version information
 *  - create md5 hash file to archive file
 *  (data)
 *  - create a archive file of one or more directory with version information
 *  - create md5 hash file to archive file
 *  (project code)
 *  - create a archive file of project code with version information
 *  - create md5 hash file to archive file
 *  - remove local TAG, if build failt
 *
 * TODO: - sanitize database dump
 *       - create database and files dump of remove system
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

$plugin['info'] = 'Create a new release. If no project is specified, for all projects a new release will be created';
$plugin['root_only'] = FALSE;

$plugin['args']['tag'] = 'Tag of the release (required)';

$plugin['options']['project'] = array(
    'short_name' => '-p',
    'long_name' => '--project',
    'action' => 'StoreString',
    'description' => 'Only create release for this project',
);

$plugin['options']['with_db'] = array(
    'short_name' => '-d',
    'long_name' => '--with_db',
    'action' => 'StoreTrue',
    'description' => 'Create database dump file with this release (overwrites [release][with_db]',
);

$plugin['options']['without_db'] = array(
    'short_name' => '-D',
    'long_name' => '--without_db',
    'action' => 'StoreTrue',
    'description' => 'Do not create database dump file with this release (overwrites [release][with_db]',
);

$plugin['options']['with_data'] = array(
    'short_name' => '-f',
    'long_name' => '--with_data',
    'action' => 'StoreTrue',
    'description' => 'Create data dump with this release (overwrites [release][with_data]',
);

$plugin['options']['without_data'] = array(
    'short_name' => '-F',
    'long_name' => '--without_data',
    'action' => 'StoreTrue',
    'description' => 'Do not create data dump with this release (overwrites [release][with_data]',
);

$plugin['options']['create_tag'] = array(
    'short_name' => '-t',
    'long_name' => '--create_tag',
    'action' => 'StoreTrue',
    'description' => 'Create a tag for this release (overwrites [release][create_tag]',
);

$plugin['options']['dontcreate_tag'] = array(
    'short_name' => '-T',
    'long_name' => '--dont_create_tag',
    'action' => 'StoreTrue',
    'description' => 'Do not create tag for this release (overwrites [release][create_tag]',
);

/**
 * Class VcdeployPluginReleaseCreate
 */
class VcdeployPluginReleaseCreate extends Vcdeploy implements IVcdeployPlugin
{
    /**
     * Push files to SCM server
     *
     * @var bool
     */
    private $with_push = TRUE;

    /**
     * files to commit
     *
     * @var array
     */
    private $commit_files = array();

    /**
     * Release TAG
     *
     * @var string
     */
    private $tag;

    /**
     * This function is run with the command
     *
     * @return int
     * @throws Exception
     * @see vcdeploy#run()
     */
    public function run()
    {
        if (isset($this->paras->command->args['tag']) && !empty($this->paras->command->args['tag'])) {
            $this->tag = $this->paras->command->args['tag'];
        } else {
            throw new Exception('No release TAG specified.');
        }

        if (isset($this->paras->command->options['project']) && !empty($this->paras->command->options['project'])) {
            $rc = $this->_projectRelease($this->paras->command->options['project']);
        } else {
            $rc = $this->_projectReleases();
        }

        return $rc;
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
     * Create a release for all active projects
     *
     * @return int
     */
    private function _projectReleases()
    {
        // check for existing projects
        $this->validate_projects();

        $rc = 0;

        foreach ($this->projects AS $project_name => $project) {
            $this->set_project($project_name, $project);
            $this->msg('Project: ' . $this->project_name);

            $r = $this->_projectRelease($this->project_name);
            if ($r) {
                $rc += $r;
            }
        }

        return $rc;
    }

    /**
     * Create a release for a project
     *
     * @param string $project_name
     *
     * @return int amount of errors
     * @throws Exception
     */
    private function _projectRelease($project_name)
    {
        $rc = 0;

        // initialize db
        $this->set_db();

        if (!is_array($this->project)) {

            // check for existing projects
            $this->validate_projects();

            $this->set_project($project_name, $this->get_project($project_name));
        }

        // initialize scm
        $this->set_scm('project');

        $this->_prepareEnvironment();

        $with_commit = FALSE;

        // set tag
        if ((isset($this->paras->command->options['create_tag']) && ($this->paras->command->options['create_tag']))
            || (isset($this->project['release']['create_tag']) && $this->project['release']['create_tag'])
        ) {
            if (!isset($this->paras->command->options['dontcreate_tag']) || (!$this->paras->command->options['dontcreate_tag'])) {
                $this->_setTag();
                $with_commit = TRUE;
            }
        }

        try {

            if ((isset($this->paras->command->options['with_db']) && ($this->paras->command->options['with_db']))
                || (isset($this->project['release']['with_db']) && $this->project['release']['with_db'])
            ) {
                if (!isset($this->paras->command->options['without_db']) || (!$this->paras->command->options['without_db'])) {
                    $this->_createDbDump();
                }
            }

            if ((isset($this->paras->command->options['with_data']) && ($this->paras->command->options['with_data']))
                || (isset($this->project['release']['with_data']) && $this->project['release']['with_data'])
            ) {
                if (!isset($this->paras->command->options['without_data']) || (!$this->paras->command->options['without_data'])) {
                    $this->_createDataArchive();
                }
            }

            if (isset($this->project['release']['with_project_archive']) && $this->project['release']['with_project_archive']) {
                $this->_createProjectArchive();
            }

        } catch (Exception $e) {

            // remove local tag
            if ($with_commit) {
                $this->_removeTag();
            }

            print 'Message: ' . $e->getMessage();
        }

        if ($with_commit) {
            // change to project path
            chdir($this->project['path']);
            if (isset($this->project['release']['with_commit']) && $this->project['release']['with_commit'] && count($this->commit_files)) {
                $this->system($this->scm->commit('Files for release ' . $this->tag . ' have been added.', $this->commit_files));
            }
            if ($this->with_push) {
                $this->system($this->scm->push(TRUE));
            }
        }

        return $rc;
    }

    /**
     * Prepare build environment
     *
     * @return void
     * @throws Exception
     */
    private function _prepareEnvironment()
    {
        if (!isset($this->project['release']['release_dir'])) {
            throw new Exception('\'release_dir\' is not specified.');
        }

        if (!file_exists($this->project['release']['release_dir'])) {
            mkdir($this->project['release']['release_dir'], 0775, TRUE);
        }
    }

    /**
     * Set new tag
     *
     * @return void
     * @throws Exception
     */
    private function _setTag()
    {
        // change to project path
        chdir($this->project['path']);

        $this->msg('Set tag "' . $this->tag . '"...');
        $rc = $this->system($this->scm->set_tag($this->tag));

        if ($rc['rc']) {
            throw new Exception('Error: Could not create tag ' . $this->tag . '. (rc=' . $rc['rc'] . ')');
        }
    }

    /**
     * Remove a local tag
     *
     * @return void
     * @throws Exception
     */
    private function _removeTag()
    {
        $this->msg('Remove local tag "' . $this->tag . '"...');

        // change to project path
        chdir($this->project['path']);
        $rc = $this->system($this->scm->remove_tag($this->tag));

        if ($rc['rc']) {
            throw new Exception('Error: Could not remove tag ' . $this->tag . '. (rc=' . $rc['rc'] . ')');
        }
    }

    /**
     * Add files to $this->commit_files
     *
     * @param array $files
     *
     * @return void
     */
    private function _addCommitFiles($files)
    {
        if (is_array($files)) {
            $this->commit_files = array_merge($this->commit_files, $files);
        }
    }

    /**
     * Create database dump
     *
     * @return void
     */
    private function _createDbDump()
    {
        foreach ($this->project['db'] AS $identifier => $db) {

            $target_file = $this->project['release']['release_dir']
                . '/' . $this->project['release']['prefix'] . $identifier . '-'
                . $this->tag . '.sql';

            if (isset($this->project['release']['remote_db']) && $this->project['release']['remote_db']) {

                $sql_file = $this->get_remote_db_file($identifier, $this->get_source_db($identifier));

                if (!empty($sql_file)) {

                    if (isset($this->project['release']['with_db_sanitize']) && $this->project['release']['with_db_sanitize']) {
                        $this->system($this->conf['gunzip_bin'] . ' -f ' . $sql_file, TRUE);
                        $sql_file_uncompressed = substr($sql_file, 0, -3);
                        $this->sanitize_database($sql_file_uncompressed);
                        $this->system($this->conf['gzip_bin'] . ' -f ' . $sql_file_uncompressed, TRUE);
                    }

                    $this->system('mv ' . $sql_file . ' ' . $target_file . '.gz');
                    $this->_addCommitFiles(array(
                        $target_file . '.gz',
                        $this->md5_file($target_file . '.gz'),
                    ));
                } else {
                    $this->msg('SQL file for import could not be identify');
                }
            } else { // local
                $this->_addCommitFiles($this->create_db_dump($db, $target_file));
            }
        }
    }

    /**
     * create archive of data directories
     *
     * @return void
     */
    private function _createDataArchive()
    {
        foreach ($this->project['data_dir'] AS $identifier => $dir) {

            $target_file = $this->project['release']['release_dir']
                . '/' . $this->project['release']['prefix'] . $identifier . '-'
                . $this->tag . '.tar';

            if (isset($this->project['release']['remote_data']) && $this->project['release']['remote_data']) {

                $tar_file = $this->get_source_data_file($identifier, $this->get_source_data($identifier));

                if (!empty($tar_file)) {
                    $this->system('mv ' . $tar_file . ' ' . $target_file . '.gz');
                    $this->_addCommitFiles(array(
                        $target_file . '.gz',
                        $this->md5_file($target_file . '.gz'),
                    ));
                } else {
                    $this->msg('Tar file for import could not be identify');
                }
            } else {
                $this->_addCommitFiles($this->create_data_dump($dir, $target_file));
            }
        }
    }

    /**
     * Create archive file of project code
     *
     * Workflow:
     * - checkout to temporary directory
     * - remove unwanted files
     * - create archive file of directory or subdirectory
     * - remove temporary directory
     *
     * @return void
     * @throws Exception
     */
    private function _createProjectArchive()
    {
        if ($this->project['scm']['type'] == 'static') {
            throw new Exception('You cannot use [scm][type]=static with setting [release][with_project_archive]=TRUE');
        }

        // 0. prepare environment
        $release_name = $this->project['release']['prefix'] . $this->tag;
        $tmp_dir = $this->conf['tmp_dir'] . '/release-create-' . $release_name . date('c');
        $release_dir = $tmp_dir . '/' . $release_name;

        mkdir($tmp_dir);

        // 1. checkout project code
        chdir($tmp_dir);
        $rc = $this->system($this->scm->checkout($release_name));
        if ($rc['rc']) {
            throw new Exception('Error with SCM checkout.');
        }

        // 2. remove unwanted files
        if (is_array($this->project['release']['remove_files'])) {
            foreach ($this->project['release']['remove_files'] AS $dir) {
                $this->msg('Remove file/directory \'' . $dir . '\'...');
                $this->remove_directory($release_dir . '/' . $dir);
            }
        } else {
            $this->msg('No files/directories are specified for removing.');
        }

        // 4. create archive file
        if (isset($this->project['release']['project_archive_dir'])) {
            $source_dir = $release_dir . '/' . $this->project['release']['project_archive_dir'];
        } else {
            $source_dir = $release_dir;
        }

        $target_file = $this->project['release']['release_dir']
            . '/' . $release_name . '.tar';

        $this->_addCommitFiles($this->create_data_dump($source_dir, $target_file));

        // 5. cleanup build environment
        $this->msg('Cleanup build environment...');
        $this->remove_directory($tmp_dir);
    }
}
