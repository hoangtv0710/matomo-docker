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

namespace Piwik\Plugins\AbTesting\Archiver;

use Piwik\DataAccess\LogAggregator;
use Piwik\Db;
use Piwik\Plugins\AbTesting\Archiver;
use Piwik\Plugins\AbTesting\Metrics;

use Piwik\Tracker;

class Aggregator
{
    /**
     * @var LogAggregator
     */
    private $logAggregator;

    public function __construct(LogAggregator $logAggregator)
    {
        $this->logAggregator = $logAggregator;
    }

    public function calcDistributionValues($metric, $innerQuery)
    {
        $query = sprintf('SELECT 
                            label,
                            STDDEV_SAMP(totalCountPerVisit) as %1$s%2$s, 
                            SUM(totalCountPerVisit) as %1$s%3$s, 
                            COUNT(totalCountPerVisit) as %1$s%4$s FROM (%5$s) t',
                            $metric,
                            Archiver::APPENDIX_TTEST_STDDEV_SAMP,
                            Archiver::APPENDIX_TTEST_SUM,
                            Archiver::APPENDIX_TTEST_COUNT,
                            $innerQuery['sql']);

        return $this->logAggregator->getDb()->query($query, $innerQuery['bind']);
    }

    public function getDistributionConversionForTtest($experiment, $variationName, $idGoal)
    {
        return $this->getDistributionFromConversionTableForTtest('SUM(IF(log_conversion.idvisit is null, 0, 1))', $experiment, $variationName, $idGoal);
    }

    public function getDistributionRevenueForTtest($experiment, $variationName, $idGoal)
    {
        return $this->getDistributionFromConversionTableForTtest('SUM(IFNULL(log_conversion.revenue, 0))', $experiment, $variationName, $idGoal);
    }

    private function getDistributionFromConversionTableForTtest($columnToRead, $experiment, $idVariation, $idGoal)
    {
        // Important: We need to make sure to get the zero values as well. Not only the values that had a revenue or conversion
        $baseQuery = $this->getBaseQuery($experiment, 'log_abtesting', 'server_time');

        $select = sprintf('log_abtesting.idvariation as label, 
                           %s as totalCountPerVisit',
                           $columnToRead);

        $where = ' AND log_abtesting.idvariation = ' . (int) $idVariation;

        $customJoin = '';
        if (isset($idGoal)) {
            $customJoin = ' AND log_conversion.idgoal = ' . (int) $idGoal;
            // it is not in where clause so we get also the null / 0 values for revenue
        }

        $baseQuery['from'] = array('log_abtesting', array(
            'table' => 'log_conversion',
            'joinOn' => 'log_abtesting.idvisit = log_conversion.idvisit ' . $customJoin
        ));

        return $this->getQueryByVisit($baseQuery, $select, $from = array(), $where);
    }

    public function getDistributionPageviewQuery($experiment, $variationId)
    {
        // Important: We need to make sure to get the zero values as well. Not only the values when a user had a pageview
        // but usually all users have a pageview anyway
        $baseQuery = $this->getBaseQuery($experiment, 'log_link_visit_action', 'server_time');

        $select = sprintf('log_abtesting.idvariation as label, 
                          count(log_link_visit_action.idaction_url) as totalCountPerVisit');

        $from = array(array(
            'table'  => 'log_action',
            'joinOn' => 'log_link_visit_action.idaction_url = log_action.idaction'
        ));

        $where = ' AND log_link_visit_action.idaction_url IS NOT NULL 
                   AND log_link_visit_action.idaction_event_category IS NULL
                   AND log_action.type = ' . Tracker\Action::TYPE_PAGE_URL . '
                   AND log_abtesting.idvariation = ' . (int) $variationId;

        return $this->getQueryByVisit($baseQuery, $select, $from, $where);
    }

    public function getDistributionVisitTotalTimeQuery($experiment, $variationId)
    {
        $baseQuery = $this->getBaseQuery($experiment, 'log_visit', 'visit_last_action_time');

        // as it is grouped by idvisit anyway we do not need to use "sum(log_visit.visit_total_time)"
        // the idvisit column is needed as it will be joined via a subselect and otherwise would fail

        $select = 'log_abtesting.idvariation as label,  
                   log_abtesting.idvisit as idvisit,
                   log_visit.visit_total_time as totalCountPerVisit';
        $where = ' AND log_abtesting.idvariation = ' . (int) $variationId;

        return $this->getQueryByVisit($baseQuery, $select, $from = array(), $where);
    }

    public function getDistributionRevenueForMannWhitney($experiment, $variationId, $idGoal)
    {
        // Important: We need to make sure to get the zero values as well. Not only the values that had a revenue
        $baseQuery = $this->getBaseQuery($experiment, 'log_abtesting', 'server_time');

        $select = 'SUM(IFNULL(log_conversion.revenue, 0)) as revenue';

        $where = ' AND log_abtesting.idvariation = ' . (int) $variationId;

        $customJoin = '';
        if (isset($idGoal)) {
            $customJoin = ' AND log_conversion.idgoal = ' . (int) $idGoal;
            // it is not in where clause so we get also the null / 0 values for revenue
        }

        $baseQuery['from'] = array('log_abtesting', array(
            'table' => 'log_conversion',
            'joinOn' => 'log_abtesting.idvisit = log_conversion.idvisit ' . $customJoin
        ));

        $query = $this->getQueryByVisit($baseQuery, $select, $from = array(), $where);

        $sql = 'select count(revenue) as revenueCount, revenue FROM (' . $query['sql'] . ') t group by revenue';

        return $this->logAggregator->getDb()->query($sql, $query['bind']);
    }

    public function getUniqueVisitors($experiment, $onlyEntered = false)
    {
        $from = array('log_abtesting');
        $where = "log_abtesting.server_time >= ? 
                AND log_abtesting.server_time <= ? 
                AND log_abtesting.idsite = ? 
                AND log_abtesting.idexperiment = " .(int) $experiment['idexperiment'];

        if ($onlyEntered) {
            $where .= ' AND log_abtesting.entered = 1';
        }

        $baseQuery = array('where' => $where, 'bind' => array(), 'from' => $from);

        $select = 'log_abtesting.idvariation as label, count(distinct log_abtesting.idvisitor) as uniqueVisitors';

        $cursor = $this->queryByVariation($baseQuery, $select);
        $all = $cursor->fetchAll();
        $cursor->closeCursor();
        unset($cursor);

        return $all;
    }

    public function aggregateGoalConversions($experiment, $idGoal, $conversionMetricName, $revenueMetricName)
    {
        $baseQuery = $this->getBaseQuery($experiment, 'log_conversion', 'server_time');

        $select = sprintf('log_abtesting.idvariation as label, 
                           count(log_abtesting.idvisit) as %s, 
                           sum(ifnull(log_conversion.revenue, 0)) as %s', $conversionMetricName, $revenueMetricName);

        $where = '';

        if (isset($idGoal)) {
            $where = ' AND log_conversion.idgoal = ' . (int) $idGoal;
        }

        return $this->queryByVariation($baseQuery, $select, $from = array(), $where);
    }

    public function aggregateVisitMetrics($experiment)
    {
        $baseQuery = $this->getBaseQuery($experiment, 'log_visit', 'visit_last_action_time');

        $select = sprintf(
                      'log_abtesting.idvariation as label, 
                       count(log_abtesting.idvisit) as %s,
                       count(distinct log_abtesting.idvisitor) as %s,
                       sum(log_visit.visit_total_time) as %s',
                       Metrics::METRIC_VISITS, Metrics::METRIC_UNIQUE_VISITORS,
                       Metrics::METRIC_SUM_VISIT_LENGTH);

        return $this->queryByVariation($baseQuery, $select);
    }

    public function aggregateBouncesAndEnteredVisits($experiment)
    {
        $baseQuery = $this->getBaseQuery($experiment, 'log_visit', 'visit_last_action_time');

        $select = sprintf(
                      'log_abtesting.idvariation as label, 
                       count(log_abtesting.idvisit) as %s,
                       count(distinct log_abtesting.idvisitor) as %s,
                       sum(case log_visit.visit_total_actions when 1 then 1 when 0 then 1 else 0 end) as %s',
                       Metrics::METRIC_VISITS_ENTERED,
                       Metrics::METRIC_UNIQUE_VISITORS_ENTERED,
                       Metrics::METRIC_BOUNCE_COUNT);

        $where = ' AND log_abtesting.entered = 1';

        return $this->queryByVariation($baseQuery, $select, $from = array(), $where);
    }

    public function aggregatePageviews($experiment)
    {
        $baseQuery = $this->getBaseQuery($experiment, 'log_link_visit_action', 'server_time');

        $select = sprintf(
                       'log_abtesting.idvariation as label, 
                        count(log_action.idaction) as %s', Metrics::METRIC_PAGEVIEWS);

        $from = array(array(
            'table'  => 'log_action',
            'joinOn' => 'log_link_visit_action.idaction_url = log_action.idaction'
        ));

        $where = ' AND log_link_visit_action.idaction_url IS NOT NULL 
                   AND log_link_visit_action.idaction_event_category IS NULL
                   AND log_action.type = ' . Tracker\Action::TYPE_PAGE_URL;

        return $this->queryByVariation($baseQuery, $select, $from, $where);
    }

    private function queryByVariation($baseQuery, $select, $from = array(), $where = '')
    {
        $query = $this->query($baseQuery, $select, $from, $where, $groupBy = 'log_abtesting.idvariation');

        return $this->logAggregator->getDb()->query($query['sql'], $query['bind']);
    }

    private function getQueryByVisit($baseQuery, $select, $from = array(), $where = '')
    {
        return $this->query($baseQuery, $select, $from, $where, $groupBy = 'log_abtesting.idvisit');
    }

    private function query($baseQuery, $select, $from = array(), $where = '', $groupBy)
    {
        foreach ($from as $table) {
            $baseQuery['from'][] = $table;
        }

        if (!empty($where)) {
            $baseQuery['where'] .= $where;
        }

        $orderBy = '';

        // just fyi: we cannot add any bind as any argument as it would otherwise break segmentation
        $query = $this->logAggregator->generateQuery($select, $baseQuery['from'], $baseQuery['where'], $groupBy, $orderBy);

        return $query;
    }

    private function getBaseQuery($experiment, $table, $timeColumn)
    {
        $idExperiment = (int) $experiment['idexperiment'];
        $from = array($table, 'log_abtesting');
        $where = "$table.$timeColumn >= ? 
                AND $table.$timeColumn <= ? 
                AND $table.idsite = ? 
                AND log_abtesting.idexperiment = $idExperiment";
        // we cannot really use any "bind" here as it otherwise breaks segmentation. Therefore we convert the idExperiment
        // to int and inline it directly

        return array('where' => $where, 'from' => $from);
    }

}
