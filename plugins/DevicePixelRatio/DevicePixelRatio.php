<?php 
/**
 * Plugin Name: Device Pixel Ratio (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/DevicePixelRatio
 * Description: Collects statistics on the device pixel ratio of the visitor's devices.  Useful to analyze how many visitors have Retina or other high DPI displays.
 * Author: Johannes Singler
 * Author URI: https://github.com/johsin18/DevicePixelRatioMatomoPlugin
 * Version: 1.0.2
 */
?><?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DevicePixelRatio;

 
if (defined( 'ABSPATH')
&& function_exists('add_action')) {
    $path = '/matomo/app/core/Plugin.php';
    if (defined('WP_PLUGIN_DIR') && WP_PLUGIN_DIR && file_exists(WP_PLUGIN_DIR . $path)) {
        require_once WP_PLUGIN_DIR . $path;
    } elseif (defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR && file_exists(WPMU_PLUGIN_DIR . $path)) {
        require_once WPMU_PLUGIN_DIR . $path;
    } else {
        return;
    }
    add_action('plugins_loaded', function () {
        if (function_exists('matomo_add_plugin')) {
            matomo_add_plugin(__DIR__, __FILE__, true);
        }
    });
}

class DevicePixelRatio extends \Piwik\Plugin
{
    public function isTrackerPlugin()
    {
        // declare that this plugin's tracker(.min).js should be included in the JavaScript tracker code
        return true;
    }
}
