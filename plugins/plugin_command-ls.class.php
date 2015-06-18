<?php
/**
 * List all available commands
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

$plugin['info'] = 'List all available commands';
$plugin['root_only'] = false;

/**
 * Class VcdeployPluginCommandLs
 */
class VcdeployPluginCommandLs extends Vcdeploy implements IVcdeployPlugin
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
        $commands = $this->get_available_commands();

        if (count($commands)) {
            // show list
            $this->msg('Available commands:');
            foreach ($this->get_available_commands() AS $command) {
                $this->msg('* ' . $command);
            }
        } else {
            $this->msg('No commands defined in vcdeploy configuration file.');
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
        return ++$init;
    }

    /**
     * Get all available commands
     *
     * @return array
     */
    public function get_available_commands()
    {
        $commands = array();
        foreach ($this->conf['commands'] AS $command_name => $command) {
            $commands[] = $command_name;
        }
        return $commands;
    }
}
