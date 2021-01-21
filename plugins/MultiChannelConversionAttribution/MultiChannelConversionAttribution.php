<?php 
/**
 * Plugin Name: Multi Channel Conversion Attribution (Matomo Plugin)
 * Plugin URI: https://plugins.matomo.org/MultiChannelConversionAttribution
 * Description: Get a clear understanding of how much credit each of your marketing channel is actually responsible for to shift your marketing efforts wisely.
 * Author: InnoCraft
 * Author URI: https://www.innocraft.com
 * Version: 3.0.7
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

namespace Piwik\Plugins\MultiChannelConversionAttribution;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\MultiChannelConversionAttribution\Dao\GoalAttributionDao;

 
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

class MultiChannelConversionAttribution extends \Piwik\Plugin
{
    /**
     * @see \Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        $hooks = array(
            'Template.beforeGoalListActionsHead' => 'printGoalListHead',
            'Template.beforeGoalListActionsBody' => 'printGoalListBody',
            'Template.endGoalEditTable' => 'printGoalEdit',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'API.Goals.addGoal.end' => 'setAttributionFromAddGoal',
            'API.Goals.updateGoal.end' => 'setAttributionFromGoalUpdate',
            'API.Goals.deleteGoal.end' => 'onDeleteGoal',
            'SitesManager.deleteSite.end' => 'onDeleteSite',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Metrics.getDefaultMetricTranslations' => 'getDefaultMetricTranslations',
            'Metrics.getDefaultMetricDocumentationTranslations' => 'getDefaultMetricDocumentationTranslations'
        );
        return $hooks;
    }

    public function install()
    {
        $configuration = new Configuration();
        $configuration->install();
        
        $attributionDao = new GoalAttributionDao();
        $attributionDao->install();
    }

    public function activate()
    {
        if (Plugin\Manager::getInstance()->isPluginActivated('Goals') && Piwik::hasUserSuperUserAccess()) {
            try {
                $goals = Request::processRequest('Goals.getGoals', array('idSite' => 'all', 'filter_limit' => -1), $default = []);
                if (count($goals) <= 50) {
                    $attributionDao = new GoalAttributionDao();

                    foreach ($goals as $goal) {
                        $attributionDao->addGoalAttribution($goal['idsite'], $goal['idgoal']);
                    }
                }
            } catch (\Exception $e) {

            }
        }
    }

    public function uninstall()
    {
        $configuration = new Configuration();
        $configuration->uninstall();

        $attributionDao = new GoalAttributionDao();
        $attributionDao->uninstall();
    }

    private function getDao()
    {
        return StaticContainer::get('Piwik\Plugins\MultiChannelConversionAttribution\Dao\GoalAttributionDao');
    }

    private function getValidator()
    {
        return StaticContainer::get('Piwik\Plugins\MultiChannelConversionAttribution\Input\Validator');
    }

    public function printGoalListHead(&$out)
    {
        $out .= '<th>' . Piwik::translate('MultiChannelConversionAttribution_Attribution') . '</th>';
    }

    public function setAttributionFromAddGoal($returnedValue, $info)
    {
        if ($returnedValue) {
            $idGoal = $returnedValue;
            $finalParameters = $info['parameters'];
            $idSite = $finalParameters['idSite'];

            $this->setAttribution($idSite, $idGoal);
        }
    }

    public function setAttributionFromGoalUpdate($value, $info)
    {
        if (empty($info['parameters'])) {
            return;
        }

        $finalParameters = $info['parameters'];
        $idSite = $finalParameters['idSite'];
        $idGoal = $finalParameters['idGoal'];

        $this->setAttribution($idSite, $idGoal);
    }

    private function setAttribution($idSite, $idGoal)
    {
        if (!isset($_POST['multiAttributionEnabled'])) {
            // no value was set, we should not change anything
            return;
        }

        $isEnabled = Common::getRequestVar('multiAttributionEnabled', 0, 'int');

        Request::processRequest('MultiChannelConversionAttribution.setGoalAttribution', array(
            'idSite' => $idSite,
            'idGoal' => $idGoal,
            'isEnabled' => $isEnabled
        ), $default = []);
    }

    public function printGoalListBody(&$out, $goal)
    {
        $attribution = Request::processRequest('MultiChannelConversionAttribution.getGoalAttribution', [
            'idSite' => $goal['idsite'],
            'idGoal' => $goal['idgoal'],
        ], $default = []);

        $isEnabled = (bool)$attribution['isEnabled'];

        $out .= '<td>';

        if (!empty($isEnabled)) {
            $message = Piwik::translate('MultiChannelConversionAttribution_MultiAttributionEnabledForGoal');
            $message = htmlentities($message);
            $out .= '<span title="' . $message . '" class="icon-ok multiAttributionActivated"></span>';
        } else {
            $out .= '-';
        }

        $out .= '</td>';
    }

    public function getDefaultMetricTranslations(&$translations)
    {
        $translations = array_merge($translations, Metrics::getMetricsTranslations());
    }

    public function getDefaultMetricDocumentationTranslations(&$translations)
    {
        $translations = array_merge($translations, Metrics::getMetricsDocumentationTranslations());
    }

    public function printGoalEdit(&$out)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!$this->getValidator()->canWrite($idSite)) {
            return;
        }

        $out .= '<div piwik-manage-multiattribution></div>';
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/MultiChannelConversionAttribution/angularjs/manage-attribution/manage-attribution.directive.js";
        $jsFiles[] = "plugins/MultiChannelConversionAttribution/angularjs/report-attribution/manage-attribution.directive.js";
        $jsFiles[] = "plugins/MultiChannelConversionAttribution/javascripts/attributionDataTable.js";
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/MultiChannelConversionAttribution/angularjs/manage-attribution/manage-attribution.directive.less";
        $stylesheets[] = "plugins/MultiChannelConversionAttribution/angularjs/report-attribution/report-attribution.directive.less";
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'MultiChannelConversionAttribution_Introduction';
        $translationKeys[] = 'MultiChannelConversionAttribution_Enabled';
        $translationKeys[] = 'MultiChannelConversionAttribution_MultiChannelConversionAttribution';
    }

    public function onDeleteSite($idSite)
    {
        $dao = $this->getDao();
        $dao->removeSiteAttributions($idSite);
    }

    public function onDeleteGoal($value, $info)
    {
        if (empty($info['parameters'])) {
            return;
        }

        $finalParameters = $info['parameters'];

        $idSite = $finalParameters['idSite'];
        $idGoal = $finalParameters['idGoal'];

        $goal = Request::processRequest('Goals.getGoal', array('idSite' => $idSite, 'idGoal' => $idGoal), $default = []);

        if (empty($goal['idgoal'])) {
            // we only delete attribution if that goal was actually deleted
            // we check for idgoal because API might return true even though goal does not exist
            $dao = $this->getDao();
            $dao->removeGoalAttribution($idSite, $idGoal);
        }
    }

}
