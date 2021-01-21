<?php 
/**
 * Plugin Name: Log Viewer (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/LogViewer
 * Description: View log messages logged by Matomo
 * Author: Matomo
 * Author URI: https://matomo.org
 * Version: 3.0.4
 */
?><?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\LogViewer;

 
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

class LogViewer extends \Piwik\Plugin
{
    /**
     * @see Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        return array(
            'AssetManager.getStylesheetFiles'        => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles'        => 'getJsFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
        );
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/LogViewer/angularjs/log-viewer/log-viewer.directive.less";
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/LogViewer/libs/phpjs/preg_quote.js";
        $jsFiles[] = "plugins/LogViewer/angularjs/log-viewer/log-viewer.controller.js";
        $jsFiles[] = "plugins/LogViewer/angularjs/log-viewer/log-viewer.directive.js";
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'General_Search';
        $translationKeys[] = 'LogViewer_LogViewer';
        $translationKeys[] = 'LogViewer_SearchPattern';
        $translationKeys[] = 'LogViewer_LogViewer';
        $translationKeys[] = 'General_Plugin';
        $translationKeys[] = 'General_Date';
        $translationKeys[] = 'LogViewer_RequestId';
        $translationKeys[] = 'LogViewer_Severity';
        $translationKeys[] = 'LogViewer_AnySeverity';
        $translationKeys[] = 'LogViewer_Tag';
        $translationKeys[] = 'LogViewer_Message';
        $translationKeys[] = 'General_Previous';
        $translationKeys[] = 'General_Next';
        $translationKeys[] = 'General_SearchNoResults';
        $translationKeys[] = 'General_ClickToSearch';
        $translationKeys[] = 'LogViewer_UseRegExp';
        $translationKeys[] = 'LogViewer_SearchHelp';
        $translationKeys[] = 'LogViewer_NoSupportedLogWriterConfiguredTitle';
        $translationKeys[] = 'LogViewer_NoSupportedLogWriterConfiguredMessage';
        $translationKeys[] = 'LogViewer_ClickToSearchForThis';
        $translationKeys[] = 'LogViewer_CurrentLogConfigIs';
        $translationKeys[] = 'LogViewer_ExportThisSearch';
    }
}
