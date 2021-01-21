<?php
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

namespace Piwik\Plugins\WhiteLabel;

use Piwik\Common;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Mail\EmailStyles;
use Piwik\Piwik;
use Piwik\Plugin\Manager;
use Piwik\Plugin\ThemeStyles;
use Piwik\Widget\WidgetsList;

class WhiteLabel extends \Piwik\Plugin
{
    public function registerEvents()
    {
        return array(
            'Widget.filterWidgets' => 'removeWidgets',
            'Platform.initialized' => 'unloadPlugins',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Controller.CoreAdminHome.home.end' => 'removePremiumFeatures',
            'Controller.Marketplace.overview' => 'checkMarketplacePermissions',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Request.dispatch.end' => 'onRequestEndReplacePiwik',
            'API.SitesManager.getImageTrackingCode.end' => 'updateTrackingEndpoint',
            'API.SitesManager.getJavascriptTag.end' => 'updateTrackingEndpoint',
            'API.TagManager.getAvailableVariableTypesInContext.end' => 'updateAvailableVariableTypesInContext',
            'FrontController.modifyErrorPage' => 'onModifyErrorPage',
            'Template.jsGlobalVariables' => 'addJsGlobalVariables',
            'SitesManager.showMatomoLinksInTrackingCodeEmail' => 'showMatomoLinksInTrackingCodeEmail',
            'AssetManager.addStylesheets' => [
                'function' => 'addStylesheets',
                'after' => true,
            ],
            'Email.configureEmailStyle' => [
                'function' => 'setEmailThemeVariables',
                'after' => true,
            ],
            'Theme.configureThemeVariables' => [
                'function' => 'configureThemeVariables',
                'after' => true,
            ],
            'SitesManager.siteWithoutData.customizeImporterMessage' => 'customizeImporterMessage'
        );
    }

    public function customizeImporterMessage(&$googleAnalyticsImporterMessage)
    {
        $settings = $this->getSystemSettings();
        if ($settings->removeLinksToMatomo->getValue() || $settings->brandName->getValue()) {
            $googleAnalyticsImporterMessage = '';
        }
    }

    public function showMatomoLinksInTrackingCodeEmail(&$showMatomoLinks)
    {
        $settings = $this->getSystemSettings();
        if ($settings->removeLinksToMatomo->getValue() || $settings->brandName->getValue()) {
            $showMatomoLinks = false;
        }
    }

    public function addStylesheets(&$mergedContent)
    {
        $settings = $this->getSystemSettings();

        $variables = array(
            '@theme-color-header-background' => $settings->headerBackgroundColor->getValue(),
            '@theme-color-header-text' => $settings->headerFontColor->getValue(),
        );

        foreach ($variables as $var => $color) {
            if (!empty($color)) {
                $color = '#' . ltrim($color, '#');
                $mergedContent .= "
        $var: $color;";
            }
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/WhiteLabel/stylesheets/whitelabel.less";
    }

    public function checkMarketplacePermissions()
    {
        if ($this->getSystemSettings()->marketplaceOnlySuperUser->getValue()) {
            Piwik::checkUserHasSuperUserAccess();
        }
    }

    public function removeWidgets(WidgetsList $list)
    {
        $list->remove('About Piwik');
        $list->remove('About Matomo');
        $list->remove('Marketplace_Marketplace');
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/WhiteLabel/javascripts/ui.js";
    }

    public function removePremiumFeatures(&$output)
    {
        // TODO: Can be removed from Piwik 3.0.2 see https://github.com/piwik/piwik/pull/11293
        $output = str_replace('<div piwik-widget-loader=\'{"module":"Marketplace","action":"getPremiumFeatures"}\'></div>', '', $output);
        $output = str_replace('<div piwik-widget-loader=\'{"module":"Marketplace","action":"getNewPlugins", "isAdminPage": "1"}\'></div>', '', $output);
    }

    public function onModifyErrorPage(&$output)
    {
        if (!Piwik::hasUserSuperUserAccess()) {
            $output = preg_replace("/<li><a.*?<\/a><\/li>/", '', $output);
        }

        return $this->onRequestEndReplacePiwik($output, 'FrontController', 'error');
    }

    private function getSystemSettings()
    {
        return StaticContainer::get('Piwik\Plugins\WhiteLabel\SystemSettings');
    }

    public function onRequestEndReplacePiwik(&$output, $module, $action)
    {
        $format = Common::getRequestVar('format', false, 'string');
        if ($module == 'API'
            && ($action == '' || $action == 'index')
            && ($format == 'original' || $format == 'php')
        ) {
            return;
        }

        $settings = $this->getSystemSettings();
        $brandName = $settings->brandName->getValue();

        $brand = new Brand($brandName);
        $output = $brand->removeMobileAppAd($output);

        if ($brand->shouldReplaceBrand($module, $action)) {
            $output = $brand->replacePiwikWithBrand($output);
        }

        if (!Piwik::hasUserSuperUserAccess() && $settings->removeLinksToMatomo->getValue()) {
            $output = $brand->removeLinksToMatomo($output);
        }
    }

    public function addJsGlobalVariables(&$str)
    {
        $shouldRemove = 0;

        $settings = $this->getSystemSettings();
        if (!Piwik::hasUserSuperUserAccess() && $settings->removeLinksToMatomo->getValue()) {
            $shouldRemove = 1;
        }

        $str .= "piwik.whiteLabelRemoveLinks = " . $shouldRemove . ";";
    }

    public function updateAvailableVariableTypesInContext(&$response)
    {
        $settings = $this->getSystemSettings();
        if ($settings->whitelabelTrackingEndpoint->getValue()) {
            foreach ($response as &$row) {
                if (empty($row['types'])) {
                    continue;
                }
                foreach ($row['types'] as &$type) {
                    if (empty($type['parameters'])) {
                        continue;
                    }
                    if ($type['id'] === 'MatomoConfiguration') {
                        foreach ($type['parameters'] as &$parameter){
                            if ($parameter['name'] === 'jsEndpoint') {
                                $parameter['availableValues'] = array('js/tracker.php');
                                $parameter['defaultValue'] = 'js/tracker.php';
                            }
                        }
                    }
                }
            }
        }
    }

    public function updateTrackingEndpoint(&$response)
    {
        $settings = $this->getSystemSettings();

        if ($settings->whitelabelTrackingEndpoint->getValue()) {
            $response = str_replace(array('piwik.js','piwik.php', 'matomo.js', 'matomo.php'), 'js/tracker.php', $response);
        }
    }

    public function unloadPlugins()
    {
        // config file etc might not be writable or not be changed on all servers so we also do disable the plugin
        // dynamically
        $this->unloadPlugin('ProfessionalServices');

        $module = Common::getRequestVar('module', '', 'string');
        $action = Common::getRequestVar('action', '', 'string');

        $customEmail = Config::getInstance()->General['feedback_email_address'];

        $isCustomizedFeedbackEmail = false;
        if (!empty($customEmail) && strpos($customEmail, 'piwik.org') !== false && strpos($customEmail, 'matomo.org') !== false) {
            $isCustomizedFeedbackEmail = true;
        }

        $isJsProxy = $module === 'Proxy' && in_array($action, array('getCoreJs', 'getNonCoreJs'), true);
        $isCssProxy = $module === 'Proxy' && in_array($action, array('getCss'), true);

        // see #43
        if ($isCssProxy) {
            // we make sure to not unload the plugin so we can be sure all CSS files of feedback are included and eg
            // the help page is styled for a super user
        } elseif ($isJsProxy && $isCustomizedFeedbackEmail) {
            // we want to make sure the feedback js is included for the thumbs up / down rating feature
            // therefore we make sure to not unload feedback plugin when generating JavaScript
        } elseif ($isJsProxy && !$isCustomizedFeedbackEmail) {
            // we always unload the feedback plugin when generating JavaScript so rating won't appear as it would go
            // to Piwik emails etc
            $this->unloadPlugin('Feedback');
        } elseif (!Piwik::hasUserSuperUserAccess()) {
            // we make sure to unload the plugin so the help page won't appear etc
            $this->unloadPlugin('Feedback');
        }
    }

    public function configureThemeVariables(ThemeStyles $vars)
    {
        $settings = $this->getSystemSettings();

        $headerBackgroundColor = $settings->headerBackgroundColor->getValue();
        if ($headerBackgroundColor) {
            $vars->colorHeaderBackground = '#' . $headerBackgroundColor;
        }

        $headerFontColor = $settings->headerFontColor->getValue();
        if ($headerFontColor) {
            $vars->colorHeaderText = '#' . $headerFontColor;
        }
    }

    public function setEmailThemeVariables(EmailStyles $vars)
    {
        $settings = $this->getSystemSettings();
        $vars->brandNameLong = $settings->brandName->getValue();
    }

    private function unloadPlugin($pluginName)
    {
        $manager = Manager::getInstance();

        if ($manager->isPluginActivated($pluginName) == true) {
            $manager->unloadPlugin($pluginName);

            // we need to make sure to "silently" deactive the plugin, otherwise it will be loaded later again or
            // it may show a warning in admin saying "missing plugin WhiteLabel" when dev mode is disabled
            $settingsProvider = StaticContainer::get('Piwik\Application\Kernel\GlobalSettingsProvider');
            $plugins = $settingsProvider->getSection('Plugins');

            if (!empty($plugins)) {
                $activatedPlugins = $plugins['Plugins'];
                if (!empty($activatedPlugins)) {
                    $key = array_search($pluginName, $activatedPlugins);
                    if ($key !== false) {
                        array_splice($activatedPlugins, $key, 1);
                        $plugins['Plugins'] = $activatedPlugins;
                        $settingsProvider->setSection('Plugins', $plugins);
                    }
                }
            }

        }
    }
}
