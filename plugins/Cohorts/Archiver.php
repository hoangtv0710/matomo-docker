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

use Piwik\ArchiveProcessor;
use Piwik\Config;
use Piwik\DataArray;
use Piwik\Date;
use Piwik\Metrics;
use Piwik\Plugin\ReportsProvider;
use Piwik\SettingsPiwik;
use Zend_Db_Statement;

class Archiver extends \Piwik\Plugin\Archiver
{
    const COHORTS_ARCHIVE_RECORD = "Cohorts_archive_record";
    const COHORTS_UNIQUE_VISITORS_ARCHIVE_RECORD = "Cohorts_uniq_visitors_archive_record";
    const DEFAULT_ARCHIVING_MAX_ROWS = 1000;

    /**
     * @var int
     */
    private $maximumRowsInDataTable;

    public function __construct(ArchiveProcessor $processor)
    {
        parent::__construct($processor);

        $this->maximumRowsInDataTable = $this->getMaximumRowsInDataTable();
    }

    public function aggregateDayReport()
    {
        $timezone = $this->getProcessor()->getParams()->getSite()->getTimezone();
        $siteTimezoneOffset = Date::getUtcOffset($timezone);
        $dimensions = [$this->getSelectDimension($siteTimezoneOffset, 'day')];

        $result = new DataArray();
        $this->aggregateVisitLogs($result, $dimensions);
        $this->aggregateConversionLogs($result, $dimensions);

        $this->insertBlobData($result, Metrics::INDEX_NB_UNIQ_VISITORS);

        unset($result);
    }

    private function aggregateVisitLogs(DataArray $result, array $dimensions, $metrics = false)
    {
        /** @var Zend_Db_Statement $query */
        $query = $this->getLogAggregator()->queryVisitsByDimension($dimensions, $where = false, $additionalSelects = [], $metrics);
        while ($row = $query->fetch()) {
            $label = $row['label'];
            unset($row['label']);

            $label = strtotime($label);

            $result->sumMetrics($label, $row);
        }
    }

    private function aggregateConversionLogs(DataArray $result, array $dimensions)
    {
        $extraFrom = [
            [
                'table' => 'log_visit',
                'joinOn' => 'log_visit.idvisit = log_conversion.idvisit',
            ],
        ];

        /** @var Zend_Db_Statement $query */
        $query = $this->getLogAggregator()->queryConversionsByDimension($dimensions, $where = false, $additionalSelects = [], $extraFrom);
        while ($row = $query->fetch()) {
            $label = $row['label'];
            unset($row['label']);

            $label = strtotime($label);

            $result->sumMetricsGoals($label, $row);
        }

        $result->enrichMetricsWithConversions();
    }

    private function insertBlobData(DataArray $data, $columnToSortBy, $recordName = self::COHORTS_ARCHIVE_RECORD)
    {
        $dataTable = $data->asDataTable();
        $report = $dataTable->getSerialized($this->maximumRowsInDataTable, null, $columnToSortBy);
        $this->getProcessor()->insertBlobRecord($recordName, $report);
    }

    public function aggregateMultipleReports()
    {
        $this->getProcessor()->aggregateDataTableRecords(
            self::COHORTS_ARCHIVE_RECORD, $this->maximumRowsInDataTable, null, Metrics::INDEX_NB_VISITS);

        // NOTE: on cloud this is not a performance killer
        $periodLabel = $this->getProcessor()->getParams()->getPeriod()->getLabel();
        if (SettingsPiwik::isUniqueVisitorsEnabled($periodLabel)
            // it doesn't make sense to aggregate this record for range periods, since ranges are arbitrary. so we can't know which arbitrary range a first visit is on.
            // we still trigger the above archiving, since it will pre-archive days if they have not been already.
            && $periodLabel != 'range'
        ) {
            $this->aggregateUniqueVisitorsForNonDay();
        }
    }

    private function aggregateUniqueVisitorsForNonDay()
    {
        $timezone = $this->getProcessor()->getParams()->getSite()->getTimezone();
        $siteTimezoneOffset = Date::getUtcOffset($timezone);

        $dimension = $this->getSelectDimension($siteTimezoneOffset);
        if ($dimension === null) {
            return; // can't aggregate for period
        }

        $dimensions = [$dimension];

        $result = new DataArray();
        $this->aggregateVisitLogs($result, $dimensions, [Metrics::INDEX_NB_UNIQ_VISITORS]);
        $this->insertBlobData($result, Metrics::INDEX_NB_UNIQ_VISITORS, self::COHORTS_UNIQUE_VISITORS_ARCHIVE_RECORD);

        unset($result);
    }

    public static function getMetricsToAggregate()
    {
        $apiGetReport = ReportsProvider::factory('API', 'get');
        $metrics = array_keys($apiGetReport->getMetrics());
        $metrics = array_map(function ($metric) {
            return 'Cohorts_' . $metric;
        }, $metrics);
        return $metrics;
    }

    /**
     * @param int $siteTimezoneOffset timezone offset in hours
     */
    private function getSelectDimension($siteTimezoneOffset, $period = false)
    {
        $period = $period ?: $this->getProcessor()->getParams()->getPeriod()->getLabel();

        // label is the number of days since the epoch that the first visit of this visitor was in in site's timezone,
        // rounded to start of period
        $secondsSinceFirstVisit = "(log_visit.visitor_days_since_first * 86400)";
        $firstActionTimeUtc = "UNIX_TIMESTAMP(log_visit.visit_first_action_time)";
        $firstVisitStartTime = "($firstActionTimeUtc - $secondsSinceFirstVisit)";
        $adjustedFirstVisitTime = "(UNIX_TIMESTAMP(CONVERT_TZ(FROM_UNIXTIME($firstVisitStartTime), @@session.time_zone, '+00:00')) + $siteTimezoneOffset)";

        $firstVisitTimeStartDay = "DATE_FORMAT(FROM_UNIXTIME($adjustedFirstVisitTime), '%Y-%m-%d')";
        if ($period == 'day') {
            $roundToPeriodStart = $firstVisitTimeStartDay;
        } else if ($period == 'week') {
            $roundToPeriodStart = "DATE_ADD($firstVisitTimeStartDay, INTERVAL - WEEKDAY($firstVisitTimeStartDay) DAY)";
        } else if ($period == 'month') {
            $roundToPeriodStart = "DATE_FORMAT(FROM_UNIXTIME($adjustedFirstVisitTime), '%Y-%m-01')";
        } else if ($period == 'year') {
            $roundToPeriodStart = "DATE_FORMAT(FROM_UNIXTIME($adjustedFirstVisitTime), '%Y-01-01')";
        } else {
            return null;
        }

        return "$roundToPeriodStart AS label";
    }

    private function getMaximumRowsInDataTable()
    {
        $config = Config::getInstance()->Cohorts;
        if (empty($config['datatable_archiving_maximum_rows'])) {
            return self::DEFAULT_ARCHIVING_MAX_ROWS;
        }

        $maxRows = $config['datatable_archiving_maximum_rows'];
        if ($maxRows <= 0) {
            return self::DEFAULT_ARCHIVING_MAX_ROWS;
        }

        return $maxRows;
    }
}
