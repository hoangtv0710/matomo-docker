<?php 
/**
 * Plugin Name: Treemap Visualization (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/TreemapVisualization
 * Description: Visualise any report in Matomo as a Treemap. Click on the Treemap icon in each report to load the visualisation. 
 * Author: Matomo
 * Author URI: https://matomo.org
 * Version: 3.1.2
 */
?><?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\TreemapVisualization;

use Piwik\Common;
use Piwik\Period;
use Piwik\Plugins\TreemapVisualization\Visualizations\Treemap;

/**
 * Plugin that contains the Treemap DataTable visualization.
 */
 
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

class TreemapVisualization extends \Piwik\Plugin
{
    public function registerEvents()
    {
        return array(
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'ViewDataTable.addViewDataTable'  => 'getAvailableVisualizations', // Piwik 2.X
            'ViewDataTable.filterViewDataTable'  => 'removeTreemapVisualizationIfFlattenIsUsed' // Piwik 3.X
        );
    }

    public function getAvailableVisualizations(&$visualizations)
    {
        // treemap doesn't work w/ flat=1
        if (Common::getRequestVar('flat', 0)) {
            $key = array_search('Piwik\\Plugins\\TreemapVisualization\\Visualizations\\Treemap', $visualizations);
            if ($key !== false) {
                unset($visualizations[$key]);
            }
        }
    }

    public function removeTreemapVisualizationIfFlattenIsUsed(&$visualizations)
    {
        // treemap doesn't work w/ flat=1
        if (Common::getRequestVar('flat', 0) && isset($visualizations[Treemap::ID])) {
            unset($visualizations[Treemap::ID]);
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/TreemapVisualization/stylesheets/treemap.less';
        $stylesheets[] = 'plugins/TreemapVisualization/stylesheets/treemapColors.less';
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/TreemapVisualization/libs/Jit/jit-2.0.1-yc.js';
        $jsFiles[] = 'plugins/TreemapVisualization/javascripts/treemapViz.js';
    }

}
