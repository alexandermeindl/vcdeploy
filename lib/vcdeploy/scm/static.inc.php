<?php
/**
 * SCM static implementation
 *
 * This class is used as dummy SCM
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
 * Class VcdeployScmStatic
 */
class VcdeployScmStatic extends VcdeployScm
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
        return 'Static';
    }

    /**
     * Get update command
     *
     */
    public function update()
    {
        return FALSE;
    }

    /**
     * Get commit command
     *
     * @param string $message
     * @param array $files
     *
     * @return string
     */
    public function commit($message, $files)
    {
        return FALSE;
    }

    /**
     * Get checkout command
     *
     * @param string $directory
     *
     * @return string
     */
    public function checkout($directory = NULL)
    {
        return FALSE;
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

    /**
     * Get 'activate tag' command
     *
     * @param string $tag
     *
     * @return string
     */
    public function activate_tag($tag)
    {
        return FALSE;
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
        return FALSE;
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
        return FALSE;
    }

    /**
     * Get list of tags command
     *
     * @return string
     */
    public function get_tags()
    {
        return FALSE;
    }
}
