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

use Piwik\Archive;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Metrics;
use Piwik\Period;
use Piwik\Period\Factory;
use Piwik\Piwik;
use Piwik\Plugins\Cohorts\Columns\Metrics\VisitorRetentionPercent;
use Piwik\Plugins\Goals\Goals;
use Piwik\Tracker\GoalManager;

class CohortRecordProcessor
{
    /**
     * Removes rows for whom the day of first visit is not within $cohortPeriods.
     *
     * @param DataTable $record
     * @param Period[] $cohortPeriods (assumed to be in order)
     */
    public function removeRowsNotWithinRequestedCohortPeriods(DataTable\DataTableInterface $record, array $cohortPeriods)
    {
        $startTime = reset($cohortPeriods)->getDateStart()->getTimestamp();
        $endTime = end($cohortPeriods)->getDateEnd()->addDay(1)->getTimestamp();

        $record->filter(function (DataTable $table) use ($startTime, $endTime) {
            foreach ($table->getRows() as $key => $row) {
                $label = $row->getColumn('label');
                $time = (int) $label;
                if ($time < $startTime
                    || $time >= $endTime
                ) {
                    $table->deleteRow($key);
                }
            }
        });
    }

    /**
     * Initially, the record contains timestamps as label values. This function converts them to
     * the requested period type, gets the period's start date, and then groups rows by that start
     * date.
     *
     * The result is a datatable w/ metrics for visitors whose first visit fell within certain periods.
     *
     * @param DataTable\DataTableInterface $record
     * @param string $periodType
     */
    public function convertDayOfFirstVisitToPeriodOfFirstVisit(DataTable\DataTableInterface $record, $periodType)
    {
        $record->filter(DataTable\Filter\GroupBy::class, ['label', function ($label) use ($periodType) {
            return $this->getPeriodStartDate($label, $periodType);
        }]);
    }

    /**
     * @param DataTable\Map $record
     * @param $period
     * @param Archive\ArchiveQuery $archive
     * @param $isUniqueVisitorsEnabled
     * @throws \Exception
     */
    public function integrateUniqueVisitorMetrics(DataTable\Map $record, $period, $archive, $isUniqueVisitorsEnabled)
    {
        // if unique visitors need to be merged into this report (because it's non-day, but unique visitors are enabled.
        // merge the COHORTS_UNIQUE_VISITORS_ARCHIVE_RECORD record in). for day periods, unique visitors is stored in the
        // same record.
        if ($isUniqueVisitorsEnabled
            && $period !== 'day'
        ) {
            $uniqueVisitorsData = $archive->getDataTable(Archiver::COHORTS_UNIQUE_VISITORS_ARCHIVE_RECORD);
            $this->convertDayOfFirstVisitToPeriodOfFirstVisit($uniqueVisitorsData, $period);

            foreach ($record->getDataTables() as $label => $table) {
                if (!$uniqueVisitorsData->hasTable($label)) {
                    continue;
                }

                $uniqueVisitors = $uniqueVisitorsData->getTable($label);
                $table->addDataTable($uniqueVisitors);
            }
        }

        if ($isUniqueVisitorsEnabled) {
            // calculate visitor retention percent
            $record->filter(function (DataTable $table) use ($record, $period) {
                $extraProcessedMetrics = $table->getMetadata(DataTable::EXTRA_PROCESSED_METRICS_METADATA_NAME) ?: [];
                $extraProcessedMetrics[] = new VisitorRetentionPercent($record, $period);
                $table->setMetadata(DataTable::EXTRA_PROCESSED_METRICS_METADATA_NAME, $extraProcessedMetrics);
            });
        }
    }

    /**
     * Split up 'goals' columns into goal_X_... columns so individual goal metrics can be selected in the UI.
     *
     * @param DataTable $record
     */
    public function flattenGoalsColumns(DataTable\DataTableInterface $record)
    {
        $record->queueFilter(function (DataTable $table) {
            foreach ($table->getRows() as $row) {
                $goalsColumn = $row->getColumn(Metrics::INDEX_GOALS);
                if (empty($goalsColumn)
                    || !is_array($goalsColumn)
                ) {
                    continue;
                }

                foreach ($goalsColumn as $idGoalExpr => $metrics) {
                    $idGoal = str_replace('idgoal=', '', $idGoalExpr);
                    if ($idGoal == GoalManager::IDGOAL_ORDER) {
                        $idGoal = Piwik::LABEL_ID_GOAL_IS_ECOMMERCE_ORDER;
                    } else if ($idGoal == GoalManager::IDGOAL_CART) {
                        $idGoal = Piwik::LABEL_ID_GOAL_IS_ECOMMERCE_CART;
                    }

                    foreach ($metrics as $metric => $value) {
                        if (is_numeric($metric)
                            && isset(Metrics::$mappingFromIdToNameGoal[$metric])
                        ) {
                            $metric = Metrics::$mappingFromIdToNameGoal[$metric];
                        }

                        $newColumn = Goals::makeGoalColumn($idGoal, $metric, $forceInt = false);
                        $row->addColumn($newColumn, $value);
                    }
                }

                $row->deleteColumn(Metrics::INDEX_GOALS);
            }
        });
    }

    private function getPeriodStartDate($timestamp, $periodType)
    {
        $date = Date::factory($timestamp);
        return Factory::build($periodType, $date)->getDateStart()->toString();
    }
}