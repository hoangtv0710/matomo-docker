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

namespace Piwik\Plugins\Cohorts\Reports;

use Piwik\API\Request;
use Piwik\Cache;
use Piwik\Cache\Transient;
use Piwik\CacheId;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Metrics;
use Piwik\Period;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\Cohorts\CohortRanges;
use Piwik\Plugins\Cohorts\Columns\Metrics\CohortTableColumn;
use Piwik\Plugins\Cohorts\Columns\Metrics\VisitorRetentionPercent;
use Piwik\Plugins\Cohorts\Configuration;
use Piwik\Plugins\Cohorts\Visualizations\Cohorts;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\Goals;
use Piwik\Report\ReportWidgetFactory;
use Piwik\SettingsPiwik;
use Piwik\Site;
use Piwik\Widget\WidgetsList;

class GetCohorts extends Report
{
    const DEFAULT_METRIC = VisitorRetentionPercent::NAME;

    protected function init()
    {
        parent::init();

        $this->categoryId = 'General_Visitors';
        $this->subcategoryId = 'Cohorts_Cohorts';
        $this->name = Piwik::translate('Cohorts_Cohorts');
        $this->documentation = Piwik::translate('Cohorts_GetCohortsDocumentation');
        $this->order = 10;
        $this->processedMetrics = [];
        $this->metrics = [];

        $period = Common::getRequestVar('period', false);
        $date = Common::getRequestVar('date', false);
        $filter_limit = Common::getRequestVar('filter_limit', 0, 'int');
        if (!empty($period)
            && !empty($date)
            && !empty(Piwik::$idPeriods[$period])
        ) {
            $configuration = new Configuration();
            $periodsFromStart = $filter_limit > 0 ? $filter_limit : $configuration->getPeriodsFromStartToShow();

            if ($period == 'range') {
                $period = 'day';
            }

            for ($i = 0; $i <= $periodsFromStart; ++$i) {
                $this->metrics[] = new CohortTableColumn($period, $i);
            }
        }
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig($factory->createWidget()->setIsWide());
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);

        $view->config->enable_sort = false;

        $view->requestConfig->filter_sort_column = 'label';
        $view->requestConfig->filter_sort_order = 'asc';
        if ($view->requestConfig->filter_limit <= 0) {
            $view->requestConfig->filter_limit = 10;
        }

        $period = Common::getRequestVar('period');
        $view->config->filters[] = [function (DataTable $table) use ($period) {
            self::prettifyCohortsLabelsInTable($table, $period);
        }];

        $translations = GetCohorts::getAvailableCohortsMetricsTranslations();
        foreach ($translations as $metric => $translation) {
            $view->config->addTranslation($metric, $translation);
        }
    }

    public function getDefaultTypeViewDataTable()
    {
        return Cohorts::ID;
    }

    public function alwaysUseDefaultViewDataTable()
    {
        return true;
    }

    public static function prettifyCohortsLabelsInTable(DataTable $table, $period)
    {
        foreach ($table->getRows() as $row) {
            $date = $row->getMetadata('date');
            $label = self::prettifyCohortsLabel($date, $period);
            $row->setColumn('label', $label);
        }
    }

    public static function prettifyCohortsLabel($date, $period)
    {
        if ($period == 'range') {
            $period = 'day';
        }

        return Period\Factory::build($period, $date)->getLocalizedShortString();
    }

    public static function getAvailableCohortsMetrics($includeTemporary = true, $includeProcessed = false)
    {
        $result = self::getNonGoalAvailableMetrics($includeTemporary, $includeProcessed);

        // add goal specific metrics
        $goalSpecificMetrics = self::getGoalSpecificMetricTranslations();
        $result = array_merge($result, array_keys($goalSpecificMetrics));

        return $result;
    }

    private static function getNonGoalAvailableMetrics($includeTemporary = true, $includeProcessed = false)
    {
        $result = [
            'nb_visits',
            'nb_actions',
            'max_actions',
            'nb_conversions',
            'revenue',
        ];

        $period = Common::getRequestVar('period', false);
        $isUniqueVisitorsEnabled = SettingsPiwik::isUniqueVisitorsEnabled($period);

        if ($isUniqueVisitorsEnabled) {
            array_unshift($result, 'nb_uniq_visitors', 'nb_users');
        }

        if ($includeTemporary) {
            $result[] = 'sum_visit_length';
            $result[] = 'bounce_count';
        }

        if ($includeProcessed) {
            $result[] = 'bounce_rate';
            $result[] = 'nb_actions_per_visit';
            $result[] = 'avg_time_on_site';
            if ($isUniqueVisitorsEnabled) {
                array_unshift($result, VisitorRetentionPercent::NAME);
            }
        }

        return $result;
    }

    private static function getGoalSpecificMetricTranslations()
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        if (empty($idSite)) {
            return [];
        }

        $cache = Cache::getTransientCache();
        $cacheKey = CacheId::siteAware('Cohorts.getGoalSpecificMetricTranslations', [$idSite]);

        $result = $cache->fetch($cacheKey);
        if ($result !== false) {
            return $result;
        }

        // TOOD: don't add revenue if goal doesn't have revenue defined?
        $translations = Metrics::getDefaultMetricTranslations();

        $result = [];

        $goals = Request::processRequest('Goals.getGoals', ['idSite' => $idSite], []);

        $idGoals = array_keys($goals);
        if (Site::isEcommerceEnabledFor($idSite)) {
            $idGoals[] = Piwik::LABEL_ID_GOAL_IS_ECOMMERCE_ORDER;
            $idGoals[] = Piwik::LABEL_ID_GOAL_IS_ECOMMERCE_CART;
        }

        $goalColumns = ['nb_conversions', 'nb_visits_converted', 'revenue'];
        foreach ($idGoals as $idGoal) {
            foreach ($goalColumns as $column) {
                $goalColumnName = Goals\Goals::makeGoalColumn($idGoal, $column, $forceInt = false);
                $columnTranslation = $translations[$column];

                if ($idGoal == Piwik::LABEL_ID_GOAL_IS_ECOMMERCE_CART) {
                    $goalName = Piwik::translate('Goals_AbandonedCart');
                } else if ($idGoal == Piwik::LABEL_ID_GOAL_IS_ECOMMERCE_ORDER) {
                    $goalName = Piwik::translate('Goals_EcommerceOrder');
                } else {
                    $goalName = Piwik::translate('Goals_GoalX', $goals[$idGoal]['name']);
                }

                $result[$goalColumnName] = $columnTranslation . ' (' . $goalName . ')';
            }
        }

        $cache->save($cacheKey, $result);

        return $result;
    }

    public static function getAvailableCohortsMetricsTranslations()
    {
        $allTranslations = Metrics::getDefaultMetricTranslations();

        $translations = [];
        foreach (self::getNonGoalAvailableMetrics($includeTemporary = false, $includeProcessed = true) as $metric) {
            $translations[$metric] = $allTranslations[$metric];
        }

        $goalSpecificTranslations = self::getGoalSpecificMetricTranslations();
        return array_merge($translations, $goalSpecificTranslations);
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        // empty
    }
}
