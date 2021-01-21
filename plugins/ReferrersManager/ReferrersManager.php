<?php 
/**
 * Plugin Name: Referrers Manager (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/ReferrersManager
 * Description: Allows to view and manage the search engines and social networks that Matomo is able to detect.
 * Author: Stefan Giehl
 * Author URI: http://github.com/sgiehl/piwik-plugin-ReferrersManager
 * Version: 3.0.4
 */
?><?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\ReferrersManager;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;
use Piwik\Plugin;

/**
 *
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

class ReferrersManager extends Plugin
{
    /**
     * @see Piwik_Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        return array(
            'Referrer.addSearchEngineUrls'     => 'addSearchEngineUrls',
            'Referrer.addSocialUrls'           => 'addSocialUrls',
            'AssetManager.getJavaScriptFiles'  => 'getJsFiles',
            'AssetManager.getStylesheetFiles'  => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
        );
    }

    /**
     * Adds required JS files
     * @param $jsFiles
     */
    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/ReferrersManager/javascripts/checkreferrerurl.controller.js";
        $jsFiles[] = "plugins/ReferrersManager/javascripts/searchengines.controller.js";
        $jsFiles[] = "plugins/ReferrersManager/javascripts/socials.controller.js";
        $jsFiles[] = "plugins/ReferrersManager/javascripts/referrersmanager-tabs.directive.js";
    }

    /**
     * Adds required CSS files
     * @param $stylesheets
     */
    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/ReferrersManager/stylesheets/styles.less";
    }

    public function isTrackerPlugin()
    {
        return true;
    }

    /**
     * Adds the user defined search engines
     * @param $searchEngines
     */
    public function addSearchEngineUrls(&$searchEngines)
    {
        try {
            $userEngines = Model::getInstance()->getUserDefinedSearchEngines();
            $searchEngines = array_merge($searchEngines, $userEngines);
        } catch (\Exception $e) {}
    }

    /**
     * Adds the user defined social networks
     * @param $socials
     */
    public function addSocialUrls(&$socials)
    {
        try {
            if(Model::getInstance()->areDefaultSocialsDisabled()) {
                $socials = array();
            }

            $userSocials = Model::getInstance()->getUserDefinedSocials();
            $socials = array_merge($socials, $userSocials);
        } catch (\Exception $e) {}
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = "ReferrersManager_PluginDescription";
        $translationKeys[] = "ReferrersManager_SearchEnginesAndSocialNetworks";
        $translationKeys[] = "ReferrersManager_AddSocial";
        $translationKeys[] = "ReferrersManager_Hostname";
        $translationKeys[] = "ReferrersManager_SearchEnginesList";
        $translationKeys[] = "ReferrersManager_SocialsList";
        $translationKeys[] = "ReferrersManager_ManageSocialsAndEngines";
        $translationKeys[] = "ReferrersManager_ConfirmRemove";
        $translationKeys[] = "ReferrersManager_ManageSearchEngines";
        $translationKeys[] = "ReferrersManager_ManageSocials";
        $translationKeys[] = "ReferrersManager_DetectedEngine";
        $translationKeys[] = "ReferrersManager_DetectedKeywords";
        $translationKeys[] = "ReferrersManager_DetectedSocial";
        $translationKeys[] = "ReferrersManager_CheckUrl";
        $translationKeys[] = "ReferrersManager_CheckUrlDesc";
        $translationKeys[] = "ReferrersManager_CheckUrlSend";
        $translationKeys[] = "ReferrersManager_AddSearchEngine";
        $translationKeys[] = "ReferrersManager_Parameters";
        $translationKeys[] = "ReferrersManager_BacklinkPattern";
        $translationKeys[] = "ReferrersManager_Charset";
        $translationKeys[] = "ReferrersManager_AddEngineError";
        $translationKeys[] = "ReferrersManager_CommaSeparated";
        $translationKeys[] = "ReferrersManager_AddSocialError";
        $translationKeys[] = "ReferrersManager_SocialListDisabled";
        $translationKeys[] = "ReferrersManager_EnableSocialList";
        $translationKeys[] = "ReferrersManager_DisableSocialList";
        $translationKeys[] = "ReferrersManager_UsingDefaultSocials";
        $translationKeys[] = "ReferrersManager_ProvideSocialData";
        $translationKeys[] = "ReferrersManager_DuplicateHostnameInfo";
        $translationKeys[] = "ReferrersManager_ProvideEngineData";
        $translationKeys[] = "ReferrersManager_HostnameWildcardDesc";

        
    }

}
