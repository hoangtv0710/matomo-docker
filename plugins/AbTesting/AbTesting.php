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

namespace Piwik\Plugins\AbTesting;

use Piwik\Access;
use Piwik\API\Request;
use Piwik\Category\Subcategory;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\AbTesting\Dao\Experiment;
use Piwik\Plugins\AbTesting\Dao\LogTable;
use Piwik\Plugins\AbTesting\Dao\Strategy;
use Piwik\Plugin;
use Exception;
use Piwik\Widget\WidgetConfig;
use Piwik\Plugins\CoreHome\SystemSummary;

class AbTesting extends Plugin
{
    public function registerEvents()
    {
        return array(
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Tracker.Cache.getSiteAttributes'  => 'addSiteExperiments',
            'Segment.addSegments' => 'addSegments',
            'SitesManager.deleteSite.end' => 'onDeleteSite',
            'Template.jsGlobalVariables' => 'addJsGlobalVariables',
            'Piwik.getJavascriptCode' => 'makePiwikJsLoadSync',
            'Category.addSubcategories' => 'addSubcategories',
            'Widget.addWidgetConfigs' => 'addWidgetConfigs',
            'System.addSystemSummaryItems' => 'addSystemSummaryItems',
            'Tracker.PageUrl.getQueryParametersToExclude' => 'getQueryParametersToExclude',
            'API.getPagesComparisonsDisabledFor'     => 'getPagesComparisonsDisabledFor',
        );
    }

    public function getPagesComparisonsDisabledFor(&$pages)
    {
        $pages[] = 'AbTesting_Experiments.General_Overview';
        $pages[] = 'AbTesting_Experiments.AbTesting_ManageExperiments';
    }

    public function getQueryParametersToExclude(&$parametersToExclude)
    {
        $parametersToExclude[] = 'pk_abe';
        $parametersToExclude[] = 'pk_abv';
    }

    public function addSystemSummaryItems(&$systemSummary)
    {
        $dao = $this->getExperimentsDao();
        $numExperiments = $dao->getNumExperimentsTotal();

        $systemSummary[] = new SystemSummary\Item($key = 'experiments', Piwik::translate('AbTesting_NExperiments', $numExperiments), $value = null, array('module' => 'AbTesting', 'action' => 'manage'), $icon = 'abtestingicon-lab', $order = 9);
    }

    public function addSubcategories(&$subcategories)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!$idSite) {
            // fallback for eg API.getReportMetadata which uses idSites
            $idSite = Common::getRequestVar('idSites', 0, 'int');

            if (!$idSite) {
                return;
            }
        }

        if (!Piwik::isUserHasViewAccess($idSite)) {
            return;
        }

        $experiments = $this->getExperimentsWithReports($idSite);

        $order = 20;
        foreach ($experiments as $experiment) {
            $category = new Subcategory();
            $category->setName($experiment['name']);
            $category->setCategoryId('AbTesting_Experiments');
            $category->setId($experiment['idexperiment']);
            $category->setOrder($order++);
            $subcategories[] = $category;
        }
    }

    public function addWidgetConfigs(&$subcategories)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!$idSite) {
            // fallback for eg API.getReportMetadata which uses idSites
            $idSite = Common::getRequestVar('idSites', 0, 'int');

            if (!$idSite) {
                return;
            }
        }

        if (!Piwik::isUserHasViewAccess($idSite)) {
            return;
        }

        $experiments = $this->getExperimentsWithReports($idSite);

        foreach ($experiments as $experiment) {
            $title = Piwik::translate('AbTesting_MenuTitleExperiment', $experiment['name']);
            $config = new WidgetConfig();
            $config->setName($title);
            $config->setModule('AbTesting');
            $config->setAction('summary');
            $config->setCategoryId('AbTesting_Experiments');
            $config->setSubcategoryId($experiment['idexperiment']);
            $config->setParameters(array('idExperiment' => $experiment['idexperiment']));
            $config->setIsNotWidgetizable();
            $config->setOrder(1);
            $subcategories[] = $config;
        }
    }

    public function makePiwikJsLoadSync(&$codeImpl, $parameters)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int', $codeImpl);

        if (!empty($idSite)) {
            $experimentsModel = $this->getExperimentsModel();
            if ($experimentsModel->hasSiteExperiments($idSite)) {
                // it is needed to load the piwik js tracker synchronous when using a/b tests
                $codeImpl['loadAsync'] = false;
            }
        }
    }

    public function addJsGlobalVariables()
    {
        echo 'var piwikExposeAbTestingTarget = true;';
    }

    public function install()
    {
        $dao = new Experiment();
        $dao->install();

        $dao = new Strategy();
        $dao->install();

        $dao = new LogTable();
        $dao->install();
    }

    public function uninstall()
    {
        $dao = new Experiment();
        $dao->uninstall();

        $dao = new Strategy();
        $dao->uninstall();

        $dao = new LogTable();
        $dao->uninstall();
    }

    public function isTrackerPlugin()
    {
        return true;
    }

    public function onDeleteSite($idSite)
    {
        $experimentsModel = $this->getExperimentsModel();
        $experimentsModel->deleteExperimentsForSite($idSite);
    }

    public function getClientSideTranslationKeys(&$result)
    {
        $result[] = 'General_Save';
        $result[] = 'General_Done';
        $result[] = 'General_Actions';
        $result[] = 'General_Yes';
        $result[] = 'General_No';
        $result[] = 'General_Add';
        $result[] = 'General_Remove';
        $result[] = 'General_Search';
        $result[] = 'CoreUpdater_UpdateTitle';
        $result[] = 'AbTesting_Rule';
        $result[] = 'AbTesting_Filter';
        $result[] = 'AbTesting_EditExperiment';
        $result[] = 'AbTesting_NameOriginalVariation';
        $result[] = 'AbTesting_CreateNewExperimentNow';
        $result[] = 'AbTesting_Status';
        $result[] = 'AbTesting_StartDate';
        $result[] = 'AbTesting_FinishDate';
        $result[] = 'AbTesting_NoActiveExperimentConfigured';
        $result[] = 'AbTesting_CreateNewExperiment';
        $result[] = 'AbTesting_ManageExperiments';
        $result[] = 'AbTesting_ExperimentCreated';
        $result[] = 'AbTesting_ExperimentUpdated';
        $result[] = 'AbTesting_ExperimentStarted';
        $result[] = 'AbTesting_ExperimentFinished';
        $result[] = 'AbTesting_SuccessMetrics';
        $result[] = 'AbTesting_SuccessConditions';
        $result[] = 'AbTesting_NameAllowedCharacters';
        $result[] = 'AbTesting_ErrorXNotProvided';
        $result[] = 'AbTesting_ExperimentName';
        $result[] = 'AbTesting_Hypothesis';
        $result[] = 'AbTesting_Variation';
        $result[] = 'AbTesting_Variations';
        $result[] = 'AbTesting_FilesystemDirectory';
        $result[] = 'AbTesting_FieldSuccessMetricsLabel';
        $result[] = 'AbTesting_StatusActive';
        $result[] = 'AbTesting_ExperimentIsFinishedPleaseRemoveCode';
        $result[] = 'AbTesting_FieldSuccessMetricsHelp1';
        $result[] = 'AbTesting_FieldSuccessMetricsHelp2';
        $result[] = 'AbTesting_FieldSuccessMetricsHelp3';
        $result[] = 'AbTesting_FieldIncludedTargetsLabel';
        $result[] = 'AbTesting_FieldIncludedTargetsHelp2';
        $result[] = 'AbTesting_FieldExcludedTargetsLabel';
        $result[] = 'AbTesting_FieldExcludedTargetsHelp';
        $result[] = 'AbTesting_FieldRedirectHelp1';
        $result[] = 'AbTesting_FieldRedirectHelp2';
        $result[] = 'AbTesting_FieldRedirectHelp3';
        $result[] = 'AbTesting_ClickToCreateNewGoal';
        $result[] = 'AbTesting_TargetComparisons';
        $result[] = 'AbTesting_ErrorExperimentCannotBeUpdatedBecauseArchived';
        $result[] = 'AbTesting_FieldScheduleExperimentStartHelp';
        $result[] = 'AbTesting_FieldScheduleExperimentFinishHelp';
        $result[] = 'AbTesting_TargetComparisionsCaseInsensitive';
        $result[] = 'AbTesting_FormScheduleIntroduction';
        $result[] = 'AbTesting_FieldScheduleExperimentStartLabel';
        $result[] = 'AbTesting_FieldScheduleExperimentFinishLabel';
        $result[] = 'AbTesting_FieldPercentageParticipantsLabel';
        $result[] = 'AbTesting_FieldPercentageParticipantsHelp';
        $result[] = 'AbTesting_FieldPercentageVariationsLabel';
        $result[] = 'AbTesting_FieldPercentageVariationsHelp';
        $result[] = 'AbTesting_FieldVariationsHelp';
        $result[] = 'AbTesting_ErrorVariationAllocatedNot100Traffic';
        $result[] = 'AbTesting_ErrorVariationAllocatedNotEnoughOriginal';
        $result[] = 'AbTesting_EqualsDateInYourTimezone';
        $result[] = 'AbTesting_CurrentTimeInUTC';
        $result[] = 'AbTesting_NoExperimentsFound';
        $result[] = 'AbTesting_DeleteExperimentInfo';
        $result[] = 'AbTesting_ViewReportInfo';
        $result[] = 'AbTesting_ArchiveReportInfo';
        $result[] = 'AbTesting_ArchiveReportConfirm';
        $result[] = 'AbTesting_DeleteExperimentConfirm';
        $result[] = 'AbTesting_UrlParameterValueToMatchPlaceholder';
        $result[] = 'AbTesting_TargetPageTestTitle';
        $result[] = 'AbTesting_TargetPageTestLabel';
        $result[] = 'AbTesting_TargetPageTestErrorInvalidUrl';
        $result[] = 'AbTesting_TargetPageTestUrlMatches';
        $result[] = 'AbTesting_TargetPageTestUrlNotMatches';
        $result[] = 'AbTesting_ExperimentCreatedInfo1';
        $result[] = 'AbTesting_ExperimentCreatedInfo2';
        $result[] = 'AbTesting_ExperimentCreatedInfo3';
        $result[] = 'AbTesting_ExperimentRunningInfo1';
        $result[] = 'AbTesting_ExperimentRunningInfo2';
        $result[] = 'AbTesting_ExperimentRunningInfo3';
        $result[] = 'AbTesting_ManageExperimentsIntroduction';
        $result[] = 'AbTesting_ExperimentFinishedInfo1';
        $result[] = 'AbTesting_ExperimentFinishedInfo2';
        $result[] = 'AbTesting_RelatedActions';
        $result[] = 'AbTesting_ExperimentWillStartFromFirstTrackingRequest';
        $result[] = 'AbTesting_RunExperimentWithJsClient';
        $result[] = 'AbTesting_RunExperimentWithJsTracker';
        $result[] = 'AbTesting_RunExperimentWithOtherSDK';
        $result[] = 'AbTesting_RunExperimentWithEmailCampaign';
        $result[] = 'AbTesting_ConfidenceThreshold';
        $result[] = 'AbTesting_MinimumDetectableEffectMDE';
        $result[] = 'AbTesting_NeedHelp';
        $result[] = 'General_OrCancel';
        $result[] = 'AbTesting_TargetTypeEqualsSimple';
        $result[] = 'AbTesting_TargetTypeEqualsSimpleInfo';
        $result[] = 'AbTesting_TargetTypeEqualsExactly';
        $result[] = 'AbTesting_TargetTypeEqualsExactlyInfo';
        $result[] = 'AbTesting_TargetTypeRegExp';
        $result[] = 'AbTesting_TargetTypeRegExpInfo';
        $result[] = 'AbTesting_FieldExperimentNameHelp';
        $result[] = 'AbTesting_FieldHypothesisHelp';
        $result[] = 'AbTesting_FieldHypothesisPlaceholder';
        $result[] = 'AbTesting_FieldDescriptionHelp';
        $result[] = 'AbTesting_FieldDescriptionPlaceholder';
        $result[] = 'AbTesting_ActivateExperimentOnAllPages';
        $result[] = 'AbTesting_ActiveExperimentOnSomePages';
        $result[] = 'AbTesting_NavigationBack';
        $result[] = 'AbTesting_Schedule';
        $result[] = 'AbTesting_EmbedCode';
        $result[] = 'AbTesting_Definition';
        $result[] = 'AbTesting_UpdatingData';
        $result[] = 'AbTesting_FormCreateExperimentIntro';
        $result[] = 'AbTesting_FieldConfidenceThresholdHelp';
        $result[] = 'AbTesting_FieldMinimumDetectableEffectHelp1';
        $result[] = 'AbTesting_FieldMinimumDetectableEffectHelp2';
        $result[] = 'AbTesting_FieldSuccessConditionsHelp';
        $result[] = 'AbTesting_NewExperimentTargetPageHelp';
        $result[] = 'AbTesting_TargetPages';
        $result[] = 'AbTesting_TrafficAllocation';
        $result[] = 'AbTesting_ActionViewReport';
        $result[] = 'AbTesting_ActionFinishExperiment';
        $result[] = 'AbTesting_ActionEditExperimentAnyway';
        $result[] = 'AbTesting_ConfirmUpdateStartsExperiment';
        $result[] = 'AbTesting_ConfirmFinishExperiment';
        $result[] = 'AbTesting_ExperimentRequiresUpdateBeforeViewEmbedCode';
        $result[] = 'AbTesting_ActionArchiveExperimentSuccess';
        $result[] = 'AbTesting_ErrorCreateNoUrlDefined';
        $result[] = 'AbTesting_TargetTypeIsAny';
        $result[] = 'AbTesting_TargetTypeIsNot';
        $result[] = 'AbTesting_EditThisExperiment';
        $result[] = 'AbTesting_Redirects';
        $result[] = 'General_Description';
        $result[] = 'General_Ok';
        $result[] = 'Goals_GoalX';
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/AbTesting/libs/jquery-timepicker/jquery.timepicker.css";
        $stylesheets[] = "plugins/AbTesting/angularjs/targettest/targettest.directive.less";
        $stylesheets[] = "plugins/AbTesting/angularjs/urltarget/urltarget.directive.less";
        $stylesheets[] = "plugins/AbTesting/angularjs/manage/edit.directive.less";
        $stylesheets[] = "plugins/AbTesting/angularjs/manage/list.directive.less";
        $stylesheets[] = "plugins/AbTesting/libs/abtestingicons/style.css";
        $stylesheets[] = "plugins/AbTesting/stylesheets/report.less";
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/AbTesting/libs/jquery-timepicker/jquery.timepicker.min.js";
        $jsFiles[] = "plugins/AbTesting/tracker.min.js";

        $jsFiles[] = "plugins/AbTesting/javascripts/abtestDataTable.js";
        $jsFiles[] = "plugins/AbTesting/javascripts/topControls.js";

        $jsFiles[] = "plugins/AbTesting/angularjs/common/directives/archiveExperiment.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/common/directives/finishExperiment.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/common/directives/bindHtmlCompile.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/common/directives/checkForActiveExperiments.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/common/directives/experiment-page-link.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/common/filters/removezerotime.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/common/filters/toLocalTime.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/common/filters/readableStatus.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/common/filters/truncateString.js";

        $jsFiles[] = "plugins/AbTesting/angularjs/targettest/targettest.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/urltarget/urltarget.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit/basic.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit/traffic.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit/conditions.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit/targets.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit/schedule.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit/metrics.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit/redirects.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit/embed.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit/variations.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/model.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/list.controller.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/list.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit.controller.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/edit.directive.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/manage.controller.js";
        $jsFiles[] = "plugins/AbTesting/angularjs/manage/manage.directive.js";
    }

    public function addSiteExperiments(&$content, $idSite)
    {
        // we cache running and created experiments as a created one can become running while being cached
        $experimentsModel = $this->getExperimentsModel();
        $content['experiments'] = $experimentsModel->getActiveExperiments($idSite);
    }

    public function addSegments(&$segments)
    {
        $segment = new Segment();
        $segment->setSegment(Segment::NAME_EXPERIMENT_SEGMENT);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName('AbTesting_Experiment');
        $segment->setSqlSegment('log_abtesting.idexperiment');
        $segment->setAcceptedValues('Accepts any experiment name of a currently running or finished experiment.');
        $segment->setSqlFilter('\\Piwik\\Plugins\\AbTesting\\Segment::getIdByName');
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $experiments = $this->getExperimentsWithReports($idSite);
            $names = array();

            foreach ($experiments as $experiment) {
                $names[] = $experiment['name'];
            }

            return array_slice($names, 0, $maxValuesToReturn);
        });

        $segments[] = $segment;

        $segment = new Segment();
        $segment->setSegment(Segment::NAME_VARIATION_SEGMENT);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName('AbTesting_Variation');
        $segment->setSqlSegment('log_abtesting.idvariation');
        $segment->setAcceptedValues('Accepts any variation name of a currently running or finished experiment.');
        $segment->setSqlFilter('\\Piwik\\Plugins\\AbTesting\\Segment::getIdByName');
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            $experiments = $this->getExperimentsWithReports($idSite);
            $names = array();

            foreach ($experiments as $experiment) {
                foreach ($experiment['variations'] as $variation) {
                    $names[] = $variation['name'];
                }
            }

            return array_slice($names, 0, $maxValuesToReturn);
        });

        $segments[] = $segment;

        $segment = new Segment();
        $segment->setSegment(Segment::NAME_ENTERED_SEGMENT);
        $segment->setType(Segment::TYPE_DIMENSION);
        $segment->setName('AbTesting_VisitEnteredExperiment');
        $segment->setSqlSegment('log_abtesting.entered');
        $segment->setAcceptedValues('Eg "1", "0", "true", "false"');
        $segment->setSqlFilterValue(function ($entered) {
            if (in_array($entered, array(0,1))) {
                return (int) $entered;
            }

            if (strtolower($entered) === 'true') {
                return 1;
            }

            if (strtolower($entered) === 'false') {
                return 0;
            }

            $message = Piwik::translate('AbTesting_ErrorXNotWhitelisted', array('abtesting_entered', '1, 0, true, false'));

            throw new Exception($message);

        });
        $segment->setSuggestedValuesCallback(function ($idSite, $maxValuesToReturn) {
            return array('true', 'false', '1', '0');
        });

        $segments[] = $segment;
    }
    
    private function getExperimentsModel()
    {
        return StaticContainer::get('Piwik\Plugins\AbTesting\Model\Experiments');
    }

    private function getExperimentsDao()
    {
        return StaticContainer::get('Piwik\Plugins\AbTesting\Dao\Experiment');
    }

    private function getExperimentsWithReports($idSite)
    {
        return Request::processRequest('AbTesting.getExperimentsWithReports', ['idSite' => $idSite, 'filter_limit' => -1], $default = []);
    }

}
