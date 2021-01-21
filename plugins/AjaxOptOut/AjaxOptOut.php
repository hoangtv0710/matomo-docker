<?php 
/**
 * Plugin Name: Ajax Opt Out (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/AjaxOptOut
 * Description: Supports Opt Out from tracking through Ajax-Request. So you can implement a custom HTML, CSS and JS to realize custom-style opt out.
 * Author: Lipperts WEB
 * Author URI: https://lw-scm.de/lipperts-web/piwik-ajax-opt-out
 * Version: 1.2.1
 */
?><?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AjaxOptOut;

 
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

class AjaxOptOut extends \Piwik\Plugin {

}
