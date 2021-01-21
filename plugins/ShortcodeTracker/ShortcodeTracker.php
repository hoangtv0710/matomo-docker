<?php 
/**
 * Plugin Name: Shortcode Tracker (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/ShortcodeTracker
 * Description: Plugin allowing user to create shortcodes and track their usage within Piwik. Also integrates with UI to deliver user-friendly interaction.
 * Author: Michał Gaździk
 * Author URI: http://mgazdzik.pl/shortcodetracker
 * Version: 1.2.0
 */
?><?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ShortcodeTracker;

use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\ShortcodeTracker\Model\Model;
use Piwik\Plugins\ShortcodeTracker\Tracker\RedirectTracker;

/**
 * @codeCoverageIgnore
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

class ShortcodeTracker extends \Piwik\Plugin
{
    const DEFAULT_SHORTENER_URL = 'http://changeme.com/';
    const SHORTENER_URL_SETTING = 'shortener_url';
    const SHORTENER_EXTERNAL_SHORTCODES_IDSITE = 'shortener_external_shortcodes_idsite';
    const TRACK_REDIRECT_VISIT_EVENT = 'ShortcodeTracker.trackRedirectAction';
    const REDIRECT_EVENT_CATEGORY = 'shordcode';
    const REDIRECT_EVENT_NAME = 'redirect';

    public function install()
    {
        $model = new Model();
        $model->install();
    }

    /**
     * @return array
     */
    public function getListHooksRegistered()
    {
        return array(
            'API.DocumentationGenerator.@hideForAll' => 'hideForAll',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            self::TRACK_REDIRECT_VISIT_EVENT => 'trackRedirectAction',
            'ViewDataTable.configure' => 'dataTableConfigure',
        );
    }

    /**
     * @param bool $hide
     */
    public function hideForAll(&$hide)
    {
        $hide = true;
    }

    /**
     * @param array $jsFiles
     */
    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/ShortcodeTracker/javascripts/rowaction.js";
        $jsFiles[] = "plugins/ShortcodeTracker/javascripts/shortcodeGenerating.js";
    }

    /**
     * @param array $translationKeys
     */
    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'ShortcodeTracker_generated_shortcode_is';
        $translationKeys[] = 'ShortcodeTracker_rowaction_tooltip_title';
        $translationKeys[] = 'ShortcodeTracker_rowaction_tooltip';
    }

    /**
     * @param array $stylesheets
     */
    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/ShortcodeTracker/stylesheets/styles.less";
    }

    /**
     * @param array $shortcode
     */
    public function trackRedirectAction($shortcode)
    {
        $tracker = new RedirectTracker();
        $tracker->recordRedirectAction($shortcode);
    }

    public function dataTableConfigure(ViewDataTable $view)
    {
        if (
            ($view->requestConfig->apiMethodToRequestDataTable === 'ShortcodeTracker.getShortcodeUsageReport')
             || $view->requestConfig->apiMethodToRequestDataTable === 'ShortcodeTracker.getExternalShortcodeUsageReport'
             || $view->requestConfig->apiMethodToRequestDataTable === 'ShortcodeTracker.getShortenedExternalPagesReport'
             || $view->requestConfig->apiMethodToRequestDataTable === 'ShortcodeTracker.getShortenedPagesReport'

        ) {
            $view->config->show_insights = false;
            $view->config->disable_row_evolution = true;
            $view->config->show_all_views_icons = false;
        }
    }
}
