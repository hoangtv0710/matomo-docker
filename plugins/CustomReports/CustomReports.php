<?php 
/**
 * Plugin Name: Custom Reports (Matomo Plugin)
 * Plugin URI: https://plugins.matomo.org/CustomReports
 * Description: Pull out the information you need in order to be successful. Develop your custom strategy to meet your individualized goals while saving money & time.
 * Author: InnoCraft
 * Author URI: https://www.innocraft.com
 * Version: 3.1.27
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

namespace Piwik\Plugins\CustomReports;

use Piwik\API\Request;
use Piwik\Category\Subcategory;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugins\CustomReports\Dao\CustomReportsDao;
use Piwik\Plugins\CoreHome\SystemSummary;
use Piwik\Plugins\CustomReports\Input\Dimensions;
use Piwik\Plugins\CustomReports\Input\Metrics;
use Piwik\Plugins\CustomReports\Input\ReportType;

 
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

class CustomReports extends \Piwik\Plugin
{
    const MENU_ICON = 'icon-business';

    public function registerEvents()
    {
        return array(
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Report.addReports' => 'addCustomReports',
            'SitesManager.deleteSite.end' => 'onDeleteSite',
            'System.addSystemSummaryItems' => 'addSystemSummaryItems',
            'Category.addSubcategories' => 'addSubcategories',
            'CustomReports.buildPreviewReport' => 'buildPreviewReport',
            'API.addGlossaryItems' => 'addGlossaryItems',
            'API.getPagesComparisonsDisabledFor' => 'getPagesComparisonsDisabledFor',
        );
    }

    public function getPagesComparisonsDisabledFor(&$pages)
    {
        $pages[] = 'CustomReports_CustomReports.CustomReports_ManageReports';
    }

    public function buildPreviewReport(&$report)
    {
        $dimensions = Common::getRequestVar('dimensions', '', 'string');
        $metrics = Common::getRequestVar('metrics', null, 'string');
        $idSite = Common::getRequestVar('idSite', 0, 'string');

        if (!empty($dimensions)) {
            $dimensions = array_unique(explode(',' , $dimensions));
            $dimensionsCheck = new Dimensions($dimensions, $idSite);
            $dimensionsCheck->check();
        } else {
            $dimensions = array();
        }

        $metrics = array_unique(explode(',' , $metrics));
        $metricsCheck = new Metrics($metrics, $idSite);
        $metricsCheck->check();

        $reportType = Common::getRequestVar('report_type', null, 'string');
        $segment = Request::getRawSegmentFromRequest();

        $type = new ReportType($reportType);
        $type->check();

        $thirdDimensionTruncated = false;
        if (count($dimensions) > 2) {
            $thirdDimensionTruncated = true;
            $dimensions = array_slice($dimensions, 0, 2);
        }

        $report = array(
            'idcustomreport' => 0,
            'report_type' => $reportType,
            'dimensions' => $dimensions,
            'metrics' => $metrics,
            'segment_filter' => $segment,
            'category' => array('id' => CustomReportsDao::DEFAULT_CATEGORY,
                                'name' => Piwik::translate(CustomReportsDao::DEFAULT_CATEGORY),
                                'order' => 999,
                                'icon' => ''),
            'subcategory' => null,
            'name' => Piwik::translate('CustomReports_Preview'),
            'description' => null,
            'dimensionsTruncated' => $thirdDimensionTruncated,
            'created_date' => Date::now()->getDatetime(),
            'updated_date' => Date::now()->getDatetime(),
        );
    }

    public function addSystemSummaryItems(&$systemSummary)
    {
        $dao = $this->getDao();
        $numForms = $dao->getNumReportsTotal();

        $systemSummary[] = new SystemSummary\Item($key = 'customreports', Piwik::translate('CustomReports_NCustomReports', $numForms), $value = null, array('module' => 'CustomReports', 'action' => 'manage'), self::MENU_ICON, $order = 8);
    }

    public function install()
    {
        $dao = new CustomReportsDao();
        $dao->install();

        $config = new Configuration();
        $config->install();
    }

    public function uninstall()
    {
        $dao = new CustomReportsDao();
        $dao->uninstall();

        $config = new Configuration();
        $config->uninstall();
    }

    private function getModel()
    {
        return StaticContainer::get('Piwik\Plugins\CustomReports\Model\CustomReportsModel');
    }

    private function getDao()
    {
        return StaticContainer::get('Piwik\Plugins\CustomReports\Dao\CustomReportsDao');
    }

    public function onDeleteSite($idSite)
    {
        $model = $this->getModel();
        $model->deactivateReportsForSite($idSite);
    }

    public function addCustomReports(&$instances)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (empty($idSite) || $idSite < 1) {
            // fallback for eg API.getReportMetadata which uses idSites
            $idSite = Common::getRequestVar('idSites', 0, 'int');

            if (empty($idSite) || $idSite < 1) {
                return;
            }
        }

        $reports = $this->getConfiguredReports($idSite);

        foreach ($reports as $report) {
            $instance = new GetCustomReport();
            $instance->initCustomReport($report);
            $instances[] = $instance;
        }
    }

    public function addSubcategories(&$subcategories)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (empty($idSite)) {
            // fallback for eg API.getReportMetadata which uses idSites
            $idSite = Common::getRequestVar('idSites', 0, 'int');

            if (empty($idSite)) {
                return;
            }
        }

        $reports = $this->getConfiguredReports($idSite, $skipCategoryMetadata = true); // skipping metadata since it will lead to infinite recursion
        usort($reports, function ($a, $b) {
           return strcasecmp($a['name'], $b['name']);
        });

        $addedNames = array();
        $addedCategories = array();

        $order = 100;

        foreach ($reports as $report) {
            if (!empty($report['category']) && $report['category'] === CustomReportsDao::DEFAULT_CATEGORY) {
                // we presume this subcategory is added by different plugin.
                if (!empty($report['subcategory']) && $report['idcustomreport'] != $report['subcategory']) {
                    // will be added with another custom report entry. Happens when assigning a custom report to another custom report page
                    continue;
                }

                $subcategoryName = $report['name'];
                $subcategoryId = $report['idcustomreport'];
                $lowerName = strtolower($subcategoryName);

                if (in_array($lowerName, $addedNames)) {
                    continue; // this may happen when two custom reports exist where one has eg name "My report" and the other
                    // custom report chooses the same subcategory "My report"
                }

                if (in_array($subcategoryId, $addedCategories)) {
                    continue; // this may happen when two custom reports exist where one has eg name "My report" and the other
                    // custom report chooses the same subcategory "My report"
                }

                $addedNames[] = $lowerName;
                $addedCategories[] = $subcategoryId;

                $subcategory = new Subcategory();
                $subcategory->setName($subcategoryName);
                $subcategory->setCategoryId($report['category']);
                $subcategory->setId($subcategoryId);
                $subcategory->setOrder($order++);
                $subcategories[] = $subcategory;
            }
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/CustomReports/angularjs/manage/edit.directive.less";
        $stylesheets[] = "plugins/CustomReports/angularjs/manage/list.directive.less";
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/CustomReports/angularjs/common/filters/truncateText2.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/edit.controller.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/edit.directive.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/list.controller.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/list.directive.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/manage.controller.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/manage.directive.js";
        $jsFiles[] = "plugins/CustomReports/angularjs/manage/model.js";
    }

    public function getClientSideTranslationKeys(&$result)
    {
        $result[] = 'General_Actions';
        $result[] = 'General_Name';
        $result[] = 'General_Id';
        $result[] = 'General_Yes';
        $result[] = 'General_No';
        $result[] = 'General_LoadingData';
        $result[] = 'General_Description';
        $result[] = 'General_Cancel';
        $result[] = 'General_Website';
        $result[] = 'General_Metrics';
        $result[] = 'General_Search';
        $result[] = 'CoreUpdater_UpdateTitle';
        $result[] = 'CustomReports_AddMetric';
        $result[] = 'CustomReports_Type';
        $result[] = 'CustomReports_Category';
        $result[] = 'CustomReports_AddDimension';
        $result[] = 'CustomReports_ReportPage';
        $result[] = 'CustomReports_ReportCategory';
        $result[] = 'CustomReports_ReportCategoryHelp';
        $result[] = 'CustomReports_ReportSubcategory';
        $result[] = 'CustomReports_ReportSubcategoryHelp';
        $result[] = 'CustomReports_ReportType';
        $result[] = 'CustomReports_Dimensions';
        $result[] = 'CustomReports_PreviewReport';
        $result[] = 'CustomReports_Preview';
        $result[] = 'CustomReports_Filter';
        $result[] = 'CustomReports_WarningRequiresUnlock';
        $result[] = 'CustomReports_Unlock';
        $result[] = 'CustomReports_ConfirmUnlockReport';
        $result[] = 'CustomReports_WarningOnUpdateReportMightGetLost';
        $result[] = 'CustomReports_InfoReportIsLocked';
        $result[] = 'CustomReports_ReportContent';
        $result[] = 'CustomReports_AvailableAllWebsites';
        $result[] = 'CustomReports_ErrorMissingMetric';
        $result[] = 'CustomReports_ErrorMissingDimension';
        $result[] = 'CustomReports_ReportEditNotAllowedAllWebsites';
        $result[] = 'CustomReports_RemoveMetric';
        $result[] = 'CustomReports_RemoveDimension';
        $result[] = 'CustomReports_ReportAvailableToAllWebsites';
        $result[] = 'CustomReports_ApplyTo';
        $result[] = 'CustomReports_ViewReportInfo';
        $result[] = 'CustomReports_CustomReportIntroduction';
        $result[] = 'CustomReports_NoCustomReportsFound';
        $result[] = 'CustomReports_ManageReports';
        $result[] = 'CustomReports_EditReport';
        $result[] = 'CustomReports_DeleteReportConfirm';
        $result[] = 'CustomReports_DeleteReportInfo';
        $result[] = 'CustomReports_CreateNewReport';
        $result[] = 'CustomReports_ErrorXNotProvided';
        $result[] = 'CustomReports_ReportCreated';
        $result[] = 'CustomReports_ReportUpdated';
        $result[] = 'CustomReports_UpdatingData';
        $result[] = 'CustomReports_FieldNamePlaceholder';
        $result[] = 'CustomReports_FieldDescriptionPlaceholder';
        $result[] = 'CustomReports_ReportNameHelp';
        $result[] = 'CustomReports_ReportDescriptionHelp';
        $result[] = 'CustomReports_ReportAllWebsitesHelp';
        $result[] = 'CustomReports_ReportDimensionsHelp';
        $result[] = 'CustomReports_ReportMetricsHelp';
        $result[] = 'CustomReports_ReportSegmentHelp';
    }

    public function addGlossaryItems(&$glossaryItems)
    {
        /** @var Glossary $glossary */
        $glossary = StaticContainer::get(Glossary::class);
        $glossaryItems['CustomReports'] = [
            'title' => Piwik::translate('CustomReports_CustomReports'),
            'entries' => $glossary->getMetricsAndDimensions(),
        ];
    }

    private function getConfiguredReports($idSite, $skipCategoryMetadata = false)
    {
        return Request::processRequest('CustomReports.getConfiguredReports', [
            'idSite' => $idSite,
            'skipCategoryMetadata' => $skipCategoryMetadata ? '1' : '0',
            'filter_limit' => '-1',
        ], $default = []);
    }
}
