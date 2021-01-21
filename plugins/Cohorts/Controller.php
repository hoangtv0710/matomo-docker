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

namespace Piwik\Plugins\Cohorts;

use Piwik\Common;
use Piwik\Context;
use Piwik\DataTable;
use Piwik\DataTable\Filter\ColumnCallbackReplace;
use Piwik\Date;
use Piwik\Metrics;
use Piwik\Period;
use Piwik\Piwik;
use Piwik\Plugins\Cohorts\Reports\GetCohorts;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\ViewDataTable\Factory;
use Piwik\View;

class Controller extends \Piwik\Plugin\Controller
{
    public function getEvolutionGraph()
    {
        $this->checkSitePermission();

        $cohortDates = $this->getCohortsEvolutionPeriods();
        $displayDateRange = $this->getDateRangeToDisplay($cohortDates);

        $period = Common::getRequestVar('period');

        /** @var Evolution $view */
        $view = Factory::build(Evolution::ID, 'Cohorts.getCohortsOverTime', 'Cohorts.getEvolutionGraph');
        $view->config->show_periods = false;
        $view->config->title = Piwik::translate('Cohorts_EvolutionGraph');
        $view->config->show_limit_control = false;

        if (property_exists($view->config, 'disable_comparison')) {
            $view->config->disable_comparison = true;
        }

        $metrics = GetCohorts::getAvailableCohortsMetrics($includeTemporary = false, $includeProcessed = true);
        $view->config->selectable_columns = $metrics;

        $view->requestConfig->request_parameters_to_modify['cohorts'] = $cohortDates;
        $view->requestConfig->request_parameters_to_modify['displayDateRange'] = $displayDateRange;

        $view->config->filters[] = [function (DataTable $table) use ($period) {
            GetCohorts::prettifyCohortsLabelsInTable($table, $period);
        }];

        // configure displayed columns
        $columns = Common::getRequestVar('columns', false);
        if (false !== $columns) {
            $columns = Piwik::getArrayFromApiParameter($columns);
        }
        if (false !== $columns) {
            $columns = !is_array($columns) ? array($columns) : $columns;
        }

        if (!empty($columns)) {
            $view->config->columns_to_display = $columns;
        } elseif (empty($view->config->columns_to_display)) {
            $view->config->columns_to_display = [GetCohorts::DEFAULT_METRIC];
        }

        // configure displayed rows
        $visibleRows = Common::getRequestVar('rows', false);
        if ($visibleRows !== false) {
            // this happens when the row picker has been used
            $visibleRows = Piwik::getArrayFromApiParameter($visibleRows);
            $visibleRows = array_map('urldecode', $visibleRows);
        } else {
            $firstRow = GetCohorts::prettifyCohortsLabel(explode(',', $cohortDates)[0], $period);
            $label = Common::getRequestVar('label', $firstRow);

            if (!empty($view->config->rows_to_display)) {
                $visibleRows = $view->config->rows_to_display;
            } else {
                $visibleRows = [$label];
            }

            $view->requestConfig->request_parameters_to_modify['rows'] = $label;
        }
        $view->config->row_picker_match_rows_by = 'label';
        $view->config->rows_to_display = $visibleRows;

        // translations
        $translations = GetCohorts::getAvailableCohortsMetricsTranslations();
        foreach ($translations as $metric => $translation) {
            $view->config->addTranslation($metric, $translation);
        }

        return $this->renderView($view);
    }

    private function getCohortsEvolutionPeriods()
    {
        $date = Common::getRequestVar('date');
        $period = Common::getRequestVar('period');
        $filterLimit = Common::getRequestVar('filter_limit', $default = -1, $type = 'int');

        $cohortRanges = new CohortRanges();
        return $cohortRanges->getMultipleDateForCohortLength($date, $period, $filterLimit);
    }

    private function getDateRangeToDisplay($cohortDates)
    {
        $period = Common::getRequestVar('period');

        $cohortPeriodRange = Period\Factory::build($period, $cohortDates);
        if ($period == 'range') {
            return $cohortPeriodRange->getRangeString();
        }

        $cohortPeriods = $cohortPeriodRange->getSubperiods();

        $configuration = new Configuration();
        $periodsFromStart = $configuration->getPeriodsFromStartToShow();

        $today = Date::today();

        $evolutionPeriodStart = reset($cohortPeriods)->getDateStart();
        $evolutionPeriodEnd = end($cohortPeriods)->getDateEnd()->addPeriod($periodsFromStart, $period);
        if ($evolutionPeriodEnd->isLater($today)) {
            $evolutionPeriodEnd = $today;
        }

        $evolutionPeriodDateStr = $evolutionPeriodStart->toString() . ',' . $evolutionPeriodEnd->toString();

        return $evolutionPeriodDateStr;
    }
}
