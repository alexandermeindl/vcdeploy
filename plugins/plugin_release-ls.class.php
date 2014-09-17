<?php
/**
 * List release files
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

$plugin['info'] = 'List all available releases';
$plugin['root_only'] = false;

$plugin['args']['project'] = 'Project to list releases';

/**
 * Class VcdeployPluginReleaseLs
 */
class VcdeployPluginReleaseLs extends Vcdeploy implements IVcdeployPlugin
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

        $project_name = $this->paras->command->args['project'];
        $this->set_project($project_name, $this->get_project($project_name));

        return $this->_listReleases();
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
     * List all existing Releases to a project
     *
     * @return int
     * @throws Exception
     */
    private function _listReleases()
    {
        if (!isset($this->project['rollout']['with_project_scm']) || $this->project['rollout']['with_project_scm']) {

            // initialize scm
            $this->set_scm('project');
            if ($this->project['scm']['type'] != 'static') {
                chdir($this->project['path']);
                $rc = $this->system($this->scm->get_tags());
                foreach ($rc['output'] AS $tag) {
                    $this->msg('- ' . $tag);
                }
            } else {
                throw new Exception('SCM type static is not supported with release-ls');
            }
        } else {

            if (!isset($this->project['release']['release_dir'])) {
                throw new Exception('\'release_dir\' is not specified.');
            }

            $files = $this->_findReleases();

            rsort($files);
            foreach ($files AS $line) {
                $this->msg('- ' . $line);
            }
        }

        return 0;
    }

    /**
     * Get existing tags of project releases
     *
     * @array tags
     */
    private function _findReleases()
    {
        $lines = array();

        $d = dir($this->project['release']['release_dir']);
        $prefix_length = strlen($this->project['release']['prefix']);

        while (false !== ($entry = $d->read())) {
            if ($entry != '.' && $entry != '..') {

                if ((substr($entry, 0, $prefix_length) == $this->project['release']['prefix'])
                    && (substr($entry, -4) != '.md5')
                ) {
                    $lines[] = $entry;
                }
            }
        }
        $d->close();

        return $lines;
    }
}
