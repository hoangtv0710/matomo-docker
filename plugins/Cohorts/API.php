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

use Piwik\API\Request;
use Piwik\Archive;
use Piwik\DataTable;
use Piwik\DataTable\Filter\ColumnCallbackReplace;
use Piwik\Date;
use Piwik\Metrics;
use Piwik\NumberFormatter;
use Piwik\Period;
use Piwik\Period\Factory;
use Piwik\Period\Range;
use Piwik\Piwik;
use Piwik\SettingsPiwik;
use Piwik\Site;

/**
 * How Cohort Reports are archived/built:
 *
 * A cohort is a group of visitors whose first visit was within a specific period. Cohort reports
 * show metric values for each cohort for periods after the cohort's first visit.
 *
 * Archiving:
 *
 * We only archive one type of record: blobs that aggregate metrics by the day of first visit. This
 * involves a simple query on the log_visit and log_conversion tables.
 *
 * When aggregating for non-day periods, we can just aggregate the records for the individual days.
 * Ie, the visits for week X for cohort that started on day N, is the sum of visits for each day
 * in week X, for the same cohort.
 *
 * The records are transformed in the API methods.
 *
 * API:
 *
 * The records, which show metrics by first day of visit, are transformed into reports that show
 * metrics by the period of the first visit.
 *
 * We basically transform the label of the record (the date of first visit) to the requested period type,
 * and group rows w/ the same label together. We also remove any rows that are outside of the requested
 * periods.
 *
 * After this, we modify the report the required format. For the Cohorts table, we want a DataTable
 * with cohorts as rows and metric values as columns. For the evolution graph, we just want metric
 * values by cohort, so we don't need to do any extra transformation there.
 */
class API extends \Piwik\Plugin\API
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var CohortRanges
     */
    private $cohortRanges;

    /**
     * @var CohortRecordProcessor
     */
    private $cohortRecordProcessor;

    /**
     * @var NumberFormatter
     */
    private $numberFormatter;

    public function __construct(NumberFormatter $formatter)
    {
        $this->configuration = new Configuration();
        $this->cohortRanges = new CohortRanges();
        $this->cohortRecordProcessor = new CohortRecordProcessor();
        $this->numberFormatter = $formatter;
    }

    // NOTE: we can't pass 'date' in here because it seems we can't change the date through request_params_to_modify
    public function getCohortsOverTime($idSite, $period, $displayDateRange, $cohorts, $segment = false, $filter_limit = false)
    {
        $date = $displayDateRange;

        Piwik::checkUserHasViewAccess($idSite);
        $this->checkSiteIsSingleSite($idSite);
        $this->checkMultiplePeriod($period, $date);

        /** @var Range $multiplePeriod */
        $multiplePeriod = Factory::build($period, $date);

        if ($period == 'range') {
            $cohortPeriods = Factory::build('day', $date)->getSubperiods();
        } else {
            $cohortPeriods = $multiplePeriod->getSubperiods();
        }

        $individualPeriodType = $period == 'range' ? 'day' : $period;

        // go through API so metrics will be formatted
        /** @var DataTable\Map $columnsData */
        $columnsData = Request::processRequest('Cohorts.getByPeriodOfFirstVisit', [
            'idSite' => $idSite,
            'cohorts' => $cohorts,
            'period' => $individualPeriodType,
            'date' => $date,
            'segment' => $segment,
            'format_metrics' => 'all',
            'periodsFromStart' => $period == 'range' ? count($cohortPeriods) : $filter_limit,
        ], []);

        // remove $columnsData tables that are not in $timePeriods
        foreach ($columnsData->getDataTables() as $label => $table) {
            /** @var Period $period */
            $periodObj = $table->getMetadata('period');
            if ($periodObj->getDateStart()->isEarlier($multiplePeriod->getDateStart())
                || $periodObj->getDateEnd()->isLater($multiplePeriod->getDateEnd())
            ) {
                $columnsData->deleteRow($label);
            }
        }

        // add empty rows for cohort periods that have no data so they are still selectable in the row selector
        $today = Date::today();

        $cohortDates = explode(',', $cohorts);
        $cohortDates = array_map(function ($d) { return Date::factory($d); }, $cohortDates);
        $columnsData->filter(function (DataTable $table) use ($individualPeriodType, $cohortDates, $today) {
            foreach ($cohortDates as $cohortDate) {
                if ($cohortDate->isLater($today)) { // sanity check
                    continue;
                }

                $cohortLabel = $cohortDate->toString();

                $row = $table->getRowFromLabel($cohortLabel);
                if (empty($row)) {
                    $table->addRowFromSimpleArray(['label' => $cohortLabel]);
                }
            }
        });

        // add date as metadata
        $columnsData->queueFilter(DataTable\Filter\ColumnCallbackAddMetadata::class, ['label', 'date']);

        $this->prettifyCohortLabels($columnsData, $period);

        return $columnsData;
    }

    public function getCohorts($idSite, $period, $date, $metric = null, $segment = false, $filter_limit = false)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $this->checkSiteIsSingleSite($idSite);

        $date = $this->cohortRanges->getMultipleDateForCohortLength($date, $period, $filter_limit);

        /** @var Range $multiplePeriod */
        $multiplePeriod = Factory::build($period, $date);

        if ($period == 'range') {
            $cohortPeriods = Factory::build('day', $date)->getSubperiods();
            $filter_limit = count($cohortPeriods);
        } else {
            $cohortPeriods = $multiplePeriod->getSubperiods();
        }

        $cohorts = array_map(function (Period $period) {
            return $period->getDateStart()->toString();
        }, $cohortPeriods);
        $cohorts = implode(',', $cohorts);

        // go through API so metrics will be formatted
        /** @var DataTable\Map $columnsData */
        $columnsData = Request::processRequest('Cohorts.getByPeriodOfFirstVisit', [
            'idSite' => $idSite,
            'cohorts' => $cohorts,
            'period' => $period == 'range' ? 'day' : $period,
            'segment' => $segment,
            'format_metrics' => 'all',
            'periodsFromStart' => $filter_limit,
        ], []);

        if ($metric === null) {
            $result = $this->buildIndexedByCohortTable($columnsData, $cohortPeriods, $filter_limit);
        } else {
            $result = $this->buildSingleMetricTable($columnsData, $cohortPeriods, $metric, $filter_limit);

            // add segment value
            $result->filter(function (DataTable $table) use ($period) {
                if ($period == 'range') {
                    $period = 'day';
                }

                foreach ($table->getRows() as $row) {
                    $date = $row->getMetadata('date');
                    $periodObj = Factory::build($period, $date);

                    $start = $periodObj->getDateStart()->toString();
                    $end = $periodObj->getDateEnd()->addDay(1)->getStartOfDay()->toString();

                    $segment = sprintf('visitorFirstVisitTime>=%s;visitorFirstVisitTime<%s', $start, $end);
                    $row->addMetadata('segment', $segment);
                }
            });
        }

        $result->filter(function (DataTable $table) use ($idSite, $multiplePeriod) {
            $table->setMetadata('site', new Site($idSite));
            $table->setMetadata('period', $multiplePeriod);
        });

        $this->prettifyCohortLabels($columnsData, $period);

        return $result;
    }

    /**
     * @param DataTable\Map $columnsData
     * @param Period[] $periodObjs
     */
    private function buildIndexedByCohortTable(DataTable\Map $columnsData, $periodObjs, $periodsFromStart)
    {
        $periodsFromStart = $periodsFromStart > 0 ? $periodsFromStart : $this->configuration->getPeriodsFromStartToShow();

        $today = Date::today()->getEndOfDay();

        $result = new DataTable\Map();
        $result->setKeyName('cohort');
        foreach ($periodObjs as $period) {
            $cohortLabel = $period->getDateStart()->toString();

            $table = new DataTable();
            $table->setMetadata('date', $cohortLabel);
            for ($i = 0; $i <= $periodsFromStart; ++$i) {
                $columnPeriodDateObj = $this->getNthPeriodDate($period, $i);
                $columnPeriodDate = $this->getColumnsTableKeyForPeriod($columnPeriodDateObj);

                if (!$columnsData->hasTable($columnPeriodDate)
                    || $columnPeriodDateObj->getDateEnd()->getEndOfDay()->isLater($today)
                ) {
                    continue;
                }

                $columnTable = $columnsData->getTable($columnPeriodDate);
                $tableRow = $columnTable->getRowFromLabel($cohortLabel);

                if ($tableRow) {
                    $newRow = clone $tableRow;
                    $newRow->setColumn('label', 'Cohorts_' . $period->getLabel() . $i);
                    $table->addRow($newRow);
                }
            }
            $result->addTable($table, $cohortLabel);
        }
        return $result;
    }

    /**
     * @param DataTable\Map $columnsData
     * @param Period[] $periodObjs
     */
    private function buildSingleMetricTable(DataTable\Map $columnsData, $periodObjs, $metric, $periodsFromStart)
    {
        $periodsFromStart = $periodsFromStart > 0 ? $periodsFromStart : $this->configuration->getPeriodsFromStartToShow();

        $today = Date::today()->getEndOfDay();

        $result = new DataTable();
        foreach ($periodObjs as $period) {
            $cohortLabel = $period->getDateStart()->toString();

            $row = new DataTable\Row();
            $row->setColumn('label', $period->getPrettyString());
            $row->setMetadata('date', $cohortLabel);
            for ($i = 0; $i <= $periodsFromStart; ++$i) {
                $columnPeriodDateObj = $this->getNthPeriodDate($period, $i);
                $columnPeriodDate = $this->getColumnsTableKeyForPeriod($columnPeriodDateObj);

                if (!$columnsData->hasTable($columnPeriodDate)
                    || $columnPeriodDateObj->getDateEnd()->getEndOfDay()->isLater($today)
                ) {
                    continue;
                }

                $table = $columnsData->getTable($columnPeriodDate);
                $tableRow = $table->getRowFromLabel($cohortLabel);

                $column = 'Cohorts_' . $period->getLabel() . $i;
                $value = $tableRow ? $tableRow->getColumn($metric) : 0;
                if (empty($value)) {
                    $currency = Site::getCurrencySymbolFor($table->getMetadata('site')->getId());
                    $value = $this->formatEmptyValue($metric, $currency);
                }

                $row->setColumn($column, $value);
            }

            $result->addRow($row);
        }
        return $result;
    }

    private function checkSiteIsSingleSite($idSite)
    {
        if (!is_numeric($idSite)) {
            throw new \Exception("Using multiple sites is not supported (got '$idSite').");
        }
    }

    /**
     * @param int
     * @param string[] $cohorts
     * @param string|bool $segment
     * @return DataTable\Map
     * @hide
     */
    public function getByPeriodOfFirstVisit($idSite, $cohorts, $period, $segment = false, $periodsFromStart = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $cohortPeriods = explode(',', $cohorts);
        if (empty($cohortPeriods)) {
            throw new \Exception('At least one cohort must be specified for the cohorts parameter.');
        }

        /** @var Period[] $cohorts */
        $cohortPeriods = array_map(function ($cohortDate) use ($period) {
            return Factory::build($period, $cohortDate);
        }, $cohortPeriods);

        // the archives are the columns of this report, so we don't use the given period (which is for the rows)
        $periodsFromStart = $periodsFromStart > 0 ? $periodsFromStart : $this->configuration->getPeriodsFromStartToShow();

        $archivePeriodStart = reset($cohortPeriods)->getDateStart();
        $archivePeriodEnd = end($cohortPeriods)->getDateStart()->addPeriod($periodsFromStart, $period);
        $archivePeriodDateStr = $archivePeriodStart->toString() . ',' . $archivePeriodEnd->toString();

        // $columnsData will be a datatable map where each table contains the metrics for a single day, grouped by day of first visit
        $archive = Archive::build($idSite, $period, $archivePeriodDateStr, $segment);
        $columnsData = $archive->getDataTable(Archiver::COHORTS_ARCHIVE_RECORD);

        $this->cohortRecordProcessor->removeRowsNotWithinRequestedCohortPeriods($columnsData, $cohortPeriods);
        $this->cohortRecordProcessor->convertDayOfFirstVisitToPeriodOfFirstVisit($columnsData, $period);

        $isUniqueVisitorsEnabled = SettingsPiwik::isUniqueVisitorsEnabled($period);
        $this->cohortRecordProcessor->integrateUniqueVisitorMetrics($columnsData, $period, $archive, $isUniqueVisitorsEnabled);

        $this->cohortRecordProcessor->flattenGoalsColumns($columnsData);

        $columnsData->queueFilter('ReplaceColumnNames');
        return $columnsData;
    }

    private function getNthPeriodDate(Period $period, $n)
    {
        $columnPeriodDate = $period->getDateStart()->addPeriod($n, $period->getLabel());
        $columnPeriodDate = Factory::build($period->getLabel(), $columnPeriodDate);
        return $columnPeriodDate;
    }

    private function getColumnsTableKeyForPeriod(Period $period)
    {
        return $period->getLabel() == 'week' ? $period->getRangeString() : $period->getPrettyString();
    }

    private function checkMultiplePeriod($period, $date)
    {
        if (!Period::isMultiplePeriod($date, $period)
            && $period !== 'range'
        ) {
            throw new \Exception("This API method can only be used with multiple periods/ranges.");
        }
    }

    private function prettifyCohortLabels(DataTable\DataTableInterface $columnsData, $period)
    {
        $effectivePeriod = $period == 'range' ? 'day' : $period;
        $columnsData->queueFilter(ColumnCallbackReplace::class, ['label', function ($label) use ($effectivePeriod) {
            return Factory::build($effectivePeriod, $label)->getPrettyString();
        }]);
    }

    private function formatEmptyValue($metric, $currency)
    {
        if (strpos($metric, 'rate') !== false
            || strpos($metric, 'evolution') !== false
            || strpos($metric, 'percent') !== false
        ) {
            return '0.00%';
        } else if (strpos($metric, 'revenue') !== false) {
            return $this->numberFormatter->formatCurrency(0, $currency);
        } else {
            return 0;
        }
    }
}
