<?php
/**
 * SCM base class
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

/**
 * Class VcdeployScm
 */
class VcdeployScm
{
    /**
     * Configuration
     *
     * @var array
     */
    protected $conf;

    /**
     * Current project settings
     *
     * @var array
     */
    protected $project;

    /**
     * Constructor
     *
     * @param array $conf
     * @param array $project
     *
     * @throws Exception
     */
    public function __construct($conf, $project = NULL)
    {
        $this->conf = $conf;
        if (isset($project)) {
            $this->project = $project;
        }
    }

    /**
     * Get userfriendly name of SCM
     *
     * @throws Exception
     */
    public function get_name()
    {
        throw new Exception('get_name is not implement of the current SCM');
    }

    /**
     * Get update command
     *
     * @throws Exception
     */
    public function update()
    {
        throw new Exception('\'update\' is not implement of the current SCM');
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
        throw new Exception('\'commit\' is not implement of the current SCM');
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
        throw new Exception('\'checkout\' is not implement of the current SCM');
    }

    /**
     * Get default branch
     *
     * @return string
     * @throws Exception
     */
    public function get_default_branch()
    {
        throw new Exception('\'get_default_branch\' is not implement of the current SCM');
    }

    /**
     * Get 'activate tag' command
     *
     * @param string $tag
     *
     * @return string
     * @throws Exception
     */
    public function activate_tag($tag)
    {
        throw new Exception('\'activate tag\' is not implement of the current SCM');
    }

    /**
     * Get 'set tag' command
     *
     * @param string $tag
     *
     * @return string
     * @throws Exception
     */
    public function set_tag($tag)
    {
        throw new Exception('\'set tag\' is not implement of the current SCM');
    }

    /**
     * Get 'remove tag' command
     *
     * @param string $tag
     *
     * @return string
     * @throws Exception
     */
    public function remove_tag($tag)
    {
        throw new Exception('\'remove tag\' is not implement of the current SCM');
    }

    /**
     * Get list of tags command
     *
     * @return string
     * @throws Exception
     */
    public function get_tags()
    {
        throw new Exception('\'get tags\' is not implement of the current SCM');
    }

    /**
     * Get push command
     *
     * @return string
     * @throws Exception
     */
    public function push($with_tags = FALSE)
    {
        throw new Exception('\'push\' is not implement of the current SCM');
    }
}
