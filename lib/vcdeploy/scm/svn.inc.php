<?php
/**
 * SCM subversion implementation
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

require_once 'Scm_base.inc.php';

/**
 * Class VcdeployScmSvn
 */
class VcdeployScmSvn extends VcdeployScm
{
    /**
     * Constructor
     *
     * @param array $conf
     * @param array $project
     */
    public function __construct($conf, $project = NULL)
    {
        parent::__construct($conf, $project);
    }

    /**
     * Get userfriendly name of SCM
     */
    public function get_name()
    {
        return 'Subversion';
    }

    /**
     * Get update command
     *
     */
    public function update()
    {
        return $this->conf['svn_bin'] . ' update';
    }

    /**
     * Get commit command
     *
     * @param string $message
     * @param array $files
     *
     * @throws Exception
     * @return string
     */
    public function commit($message, $files)
    {
        if (!is_array($files)) {
            throw new Exception('commit error: files has to be an array');
        }

        return $this->conf['svn_bin'] . ' commit -m "' . $message . '" ' . implode(' ', $files);
    }

    /**
     * Get checkout command
     *
     * @param string $directory
     *
     * @throws Exception
     * @return string
     */
    public function checkout($directory = NULL)
    {
        if (!isset($this->project['scm']['url'])) {
            throw new Exception($this->get_name() . ' url not defined (scm->url)');
        }

        $command = $this->conf['svn_bin'] . ' checkout ' . $this->project['scm']['url'];
        if (isset($directory)) {
            $command .= ' ' . $directory;
        }

        return $command;
    }

    /**
     * Get default branch
     *
     * @return string
     */
    public function get_default_branch()
    {
        return '';
    }
}
