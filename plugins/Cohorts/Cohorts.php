<?php 
/**
 * Plugin Name: Cohorts (Matomo Plugin)
 * Plugin URI: https://plugins.matomo.org/Cohorts
 * Description: Track your retention efforts over time and keep your visitors engaged and coming back for more.
 * Author: InnoCraft
 * Author URI: https://plugins.matomo.org/Cohorts
 * Version: 3.0.11
 */
?><?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

namespace Piwik\Plugins\Cohorts;

use Piwik\Piwik;
use Piwik\Plugins\Cohorts\Columns\Metrics\VisitorRetentionPercent;

 
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

class Cohorts extends \Piwik\Plugin
{
    public function registerEvents()
    {
        return [
            'AssetManager.getStylesheetFiles' => 'getStylesheets',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Metrics.getDefaultMetricTranslations' => 'getDefaultMetricTranslations',
            'API.getPagesComparisonsDisabledFor'     => 'getPagesComparisonsDisabledFor',
        ];
    }

    public function getPagesComparisonsDisabledFor(&$pages)
    {
        $pages[] = 'General_Visitors.Cohorts_Cohorts';
    }

    public function getDefaultMetricTranslations(&$translations)
    {
        $translations[VisitorRetentionPercent::NAME] = Piwik::translate('Cohorts_ReturningVisitorsPercent');
    }

    public function getStylesheets(&$stylesheets)
    {
        $stylesheets[] =  'plugins/Cohorts/stylesheets/dataTableVizCohorts.less';
    }

    public function getJsFiles(&$javascripts)
    {
        $javascripts[] = 'plugins/Cohorts/javascripts/cohortsDataTable.js';
    }
}
