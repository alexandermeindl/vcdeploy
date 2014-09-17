<?php
/**
 * SCM Mercurial implementation
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

require_once 'Scm_base.inc.php';

/**
 * Class VcdeployScmHg
 */
class VcdeployScmHg extends VcdeployScm
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
     *
     * @return string
     */
    public function get_name()
    {
        return 'Mercurial';
    }

    /**
     * Get checkout command
     *
     * @return string
     */
    public function update()
    {
        if (!isset($this->project['scm']['url'])) {
            throw new Exception($this->get_name() . ' url not defined (scm->url)');
        }

        return $this->conf['hg_bin'] . ' pull ' . $this->project['scm']['url'] . ' && ' . $this->conf['hg_bin'] . ' update';
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

        return $this->conf['hg_bin'] . ' commit -m "' . $message . '" ' . implode(' ', $files);
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

        $command = $this->conf['hg_bin'] . ' clone ' . $this->project['scm']['url'];
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
        return 'default';
    }

    /**
     * Get 'activate tag' command
     *
     * @param string $tag
     *
     * @return string
     */
    public function activate_tag($tag)
    {
        return $this->conf['hg_bin'] . ' checkout ' . $tag;
    }

    /**
     * Get 'set tag' command
     *
     * @param string $tag
     *
     * @return string
     */
    public function set_tag($tag)
    {
        return $this->conf['hg_bin'] . ' tag ' . $tag;
    }

    /**
     * Get 'remove tag' command
     *
     * @param string $tag
     *
     * @return string
     */
    public function remove_tag($tag)
    {
        return $this->conf['hg_bin'] . ' tag --remove ' . $tag;
    }

    /**
     * Get list of tags command
     *
     * @return string
     */
    public function get_tags()
    {
        return $this->conf['hg_bin'] . ' tags';
    }

    /**
     * Get push command
     *
     * @param bool $with_tags
     *
     * @return string
     */
    public function push($with_tags = FALSE)
    {
        $command = $this->conf['hg_bin'] . ' push';

        if ($with_tags) {
            $command .= ' --tags';
        }

        return $command;
    }
}
