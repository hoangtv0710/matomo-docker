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

namespace Piwik\Plugins\Cohorts\Columns\Metrics;


use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Metrics\Formatter;
use Piwik\Period;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;

class VisitorRetentionPercent extends ProcessedMetric
{
    const NAME = 'Cohorts_returning_visitors_percent';

    /**
     * @var DataTable\Map
     */
    private $allTables;

    /**
     * @var string
     */
    private $period;

    public function __construct(DataTable\Map $allTables, $period)
    {
        $this->allTables = $allTables;
        $this->period = $period;
    }

    public function getName()
    {
        return self::NAME;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('Cohorts_ReturningVisitorsPercent');
    }

    public function compute(Row $row)
    {
        $cohort = $row->getColumn('label');
        $nbVisitors = self::getMetric($row, 'nb_uniq_visitors');

        $cohortPeriod = Period\Factory::build($this->period, $cohort);
        if ($this->period == 'week' || $this->period == 'range') {
            $tableLabel = $cohortPeriod->getRangeString();
        } else {
            $tableLabel = $cohortPeriod->getPrettyString();
        }

        $cohortsForPeriod = $this->allTables->getTable($tableLabel);
        if (empty($cohortsForPeriod)) {
            return null;
        }

        $dataForCohortPeriod = $cohortsForPeriod->getRowFromLabel($cohort);
        if (empty($dataForCohortPeriod)) {
            $totalVisitors = 0;
        } else {
            $totalVisitors = (int) self::getMetric($dataForCohortPeriod, 'nb_uniq_visitors');
        }

        if ($totalVisitors == 0) {
            return false;
        }

        $result = Piwik::getQuotientSafe($nbVisitors, $totalVisitors, $precision = 4);
        return min($result, 1.0); // due to how visitor_days_since_first is tracked, some cells can have > 100% visitors
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyPercentFromQuotient($value);
    }

    public function getDependentMetrics()
    {
        $period = Common::getRequestVar('period', false);
        if (empty($period)
            || !in_array('period', ['day', 'week', 'month', 'year'])
        ) {
            return [];
        }
        return $period . '0';
    }
}