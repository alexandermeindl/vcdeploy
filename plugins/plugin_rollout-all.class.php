<?php
/**
 * Run other plugins as a batch
 *
 * PHP version 5.3
 *
 * @category  Console
 * @package   Vcdeploy
 * @author    Alexander Meindl <a.meindl@alphanodes.com>
 * @copyright 2014 Alexander Meindl
 * @license   http://www.mozilla.org/MPL Mozilla Public License Version 1.1
 * @link      https://github.com/alexandermeindl/vcdeploy
 */

$plugin['info'] = 'Run rollout and rollout-system plugins';
$plugin['root_only'] = true;

$plugin['batch_before'] = array('rollout', 'rollout-system');
$plugin['batch_after'] = array();

/**
 * Class VcdeployPluginRolloutAll
 */
class VcdeployPluginRolloutAll extends Vcdeploy implements IVcdeployPlugin
{
    /**
     * This function is run with the command
     *
     * @return int
     * @see vcdeploy#run()
     */
    public function run()
    {
        // nothing todo in this plugin
        $this->msg('All updates completed.');

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
        return ++$init;
    }
}
