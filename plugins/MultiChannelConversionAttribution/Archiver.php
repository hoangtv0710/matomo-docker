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

namespace Piwik\Plugins\MultiChannelConversionAttribution;

use Piwik\ArchiveProcessor;
use Piwik\Common;
use Piwik\Db;
use Piwik\Container\StaticContainer;
use Piwik\DataArray as PiwikDataArray;
use Piwik\Plugins\MultiChannelConversionAttribution\Archiver\AttributionDataArray;
use Piwik\Plugins\MultiChannelConversionAttribution\Model\GoalAttributionModel;
use Piwik\Plugins\MultiChannelConversionAttribution\Models\Base;
use Piwik\Plugins\MultiChannelConversionAttribution\Models\LastNonDirect;
use Piwik\Plugins\MultiChannelConversionAttribution\Models\Linear;

class Archiver extends \Piwik\Plugin\Archiver
{
    const RECORD_CHANNEL_TYPES = "MultiChannelConversionAttribution_channelTypes";
    const LABEL_NOT_DEFINED = 'MultiChannelConversionAttribution_LabelNotDefined';

    /**
     * @var GoalAttributionModel
     */
    private $attributions;

    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(ArchiveProcessor $processor)
    {
        parent::__construct($processor);

        $this->attributions = StaticContainer::get('Piwik\Plugins\MultiChannelConversionAttribution\Model\GoalAttributionModel');
        $this->configuration = StaticContainer::get('Piwik\Plugins\MultiChannelConversionAttribution\Configuration');
    }

    public function aggregateDayReport()
    {
        $start = $this->getProcessor()->getParams()->getDateStart();
        $idGoals = $this->getGoalIdsToArchive();
        $idSite = $this->getIdSite();

        $columnsLastNonDirect = $this->getLastNonDirectColumns();
        $daysPriorConversions = $this->configuration->getDaysPriorToConversion();

        foreach ($idGoals as $idGoal) {
            foreach ($daysPriorConversions as $daysPriorToConverison) {
                $sinceTime = $start->subDay($daysPriorToConverison)->getDatetime();

                $channelDataArray = new AttributionDataArray();
                $channelDataArray->addDefaultRowColumns($columnsLastNonDirect);
                $cursor = $this->query($idSite, $idGoal, $sinceTime);
                $this->addRowsToDataArray($channelDataArray, $cursor);
                unset($cursor);

                $cursor = $this->queryNonDirectVisits($idSite, $idGoal, $sinceTime);
                $channelDataArray->setColumnsContext($columnsLastNonDirect);
                $this->addRowsToDataArray($channelDataArray, $cursor);
                unset($cursor);

                $recordName = self::completeChannelAttributionRecordName($idGoal, $daysPriorToConverison);
                $this->insertDataArray($recordName, $channelDataArray);
            }
        }
    }

    private function getLastNonDirectColumns()
    {
        $model = new LastNonDirect();
        return array(
            Metrics::completeAttributionMetric(Metrics::SUM_CONVERSIONS, $model),
            $revenueColumn = Metrics::completeAttributionMetric(Metrics::SUM_REVENUE, $model)
        );
    }

    /**
     * @param AttributionDataArray $dataArray
     * @param $cursor
     */
    private function addRowsToDataArray($dataArray, $cursor)
    {
        while ($row = $cursor->fetch()) {
            $dataArray->computeMetrics($row);
        }
        $cursor->closeCursor();
    }

    private function insertDataArray($recordName, PiwikDataArray $dataArray)
    {
        $sortBy = $this->getColumnToSort();

        $dataTable = $dataArray->asDataTable();
        $serialized = $dataTable->getSerialized($maxRows = 500, $maxSubRows = 500, $sortBy);
        $this->getProcessor()->insertBlobRecord($recordName, $serialized);

        unset($serialized);
        unset($dataTable);

        Common::destroy($dataTable);
    }

    public static function completeChannelAttributionRecordName($idGoal, $daysPriorToConverison)
    {
        return self::RECORD_CHANNEL_TYPES . '_' . (int) $idGoal . '_prior' . (int) $daysPriorToConverison;
    }

    private function queryNonDirectVisits($idSite, $idGoal, $sinceTime)
    {
        // we cannot add any bind as any argument as it would otherwise break segmentation
        $aggregator = $this->getLogAggregator();

        $from = array('log_conversion',
            array('table' => 'log_visit', 'joinOn' => 'log_conversion.idvisit = log_visit.idvisit'),
            array('table' => 'log_visit', 'tableAlias' => 'log_vpast', 'join' => 'RIGHT JOIN',
                  'joinOn' => 'log_conversion.idvisitor = log_vpast.idvisitor'));

        $select = 'log_conversion.idvisitor, log_conversion.revenue, max(log_vpast.visit_last_action_time) as lastaction';
        $where = $aggregator->getWhereStatement('log_conversion', 'server_time');
        $where .= sprintf('AND log_conversion.idgoal = %d 
                           AND log_vpast.idsite = %d AND log_vpast.visit_last_action_time >= \'%s\' 
                           AND log_vpast.visit_last_action_time <= log_visit.visit_last_action_time
                           AND log_vpast.referer_type != %s', (int) $idGoal, (int) $idSite, $sinceTime, Common::REFERRER_TYPE_DIRECT_ENTRY);

        $groupBy = 'log_conversion.idvisit, log_conversion.buster';
        $query = $aggregator->generateQuery($select, $from, $where, $groupBy, $orderBy = false);

        $model = new LastNonDirect();
        $conversionColumn = Metrics::completeAttributionMetric(Metrics::SUM_CONVERSIONS, $model);
        $revenueColumn = Metrics::completeAttributionMetric(Metrics::SUM_REVENUE, $model);

        $visitTable = Common::prefixTable('log_visit');
        $sql = sprintf('
          select log_vvv.referer_type as label, log_vvv.referer_name as sublabel, sum(revenue) as %s, count(*) as %s
          from (%s) as yyy  
          left join %s as log_vvv on log_vvv.idvisitor = yyy.idvisitor 
                                and log_vvv.idsite = %d 
                                and log_vvv.visit_last_action_time = lastaction
          group by label, sublabel', $revenueColumn, $conversionColumn, $query['sql'], $visitTable, (int) $idSite);

        return $this->getLogAggregator()->getDb()->query($sql, $query['bind']);
    }

    private function query($idSite, $idGoal, $sinceTime)
    {
        $aggregationSelect = '';
        foreach (Base::getAll() as $model) {
            $query = $model->getAttributionQuery('num_pos', 'num_total');
            if (!empty($query)) {
                $aggregationSelect .= ', sum(' . $query . ') as ' . Metrics::completeAttributionMetric(Metrics::SUM_CONVERSIONS, $model);
                $aggregationSelect .= ', sum(if(revenue = 0, 0, revenue * ' . $query . ')) as ' . Metrics::completeAttributionMetric(Metrics::SUM_REVENUE, $model);
            }
        }

        // we cannot add any bind as any argument as it would otherwise break segmentation
        $aggregator = $this->getLogAggregator();
        $maxNumVisitsBack = 110;
        $from = array('log_conversion',
                array('table' => 'log_visit', 'joinOn' => 'log_conversion.idvisit = log_visit.idvisit'),
                array('table' => 'log_visit', 'tableAlias' => 'log_vpast', 'join' => 'RIGHT JOIN', 'joinOn' => 'log_conversion.idvisitor = log_vpast.idvisitor'));

        $select = 'log_conversion.idvisitor, log_conversion.idvisit, log_conversion.buster, log_conversion.revenue, log_visit.visit_last_action_time as lastactiontime, least(count(*),' . $maxNumVisitsBack . ') as num_total';
        $where = $aggregator->getWhereStatement('log_conversion', 'server_time');
        $where .= sprintf('AND log_conversion.idgoal = %d 
                           AND log_vpast.idsite = %d AND log_vpast.visit_last_action_time >= \'%s\' 
                           AND log_vpast.visit_last_action_time <= log_visit.visit_last_action_time', (int) $idGoal, (int) $idSite, $sinceTime);

        $groupBy = 'log_conversion.idvisit, log_conversion.idgoal, log_conversion.buster';
        $query = $aggregator->generateQuery($select, $from, $where, $groupBy, $orderBy = false);

        $logVisitTable = Common::prefixTable('log_visit');

        $outerWhere = 'num_pos < ' . $maxNumVisitsBack;

        $db = $this->getLogAggregator()->getDb();
        $db->query('SET @rnk=0, @curscore=0;');
        $sql = sprintf('
       select referer_type as label, referer_name as sublabel %s from (  
            select (@rnk:=IF(@curscore = concat(r.idvisit, \'_\', r.buster),@rnk+1,1)) num_pos,
                 r.num_total as num_total,
                (@curscore:=concat(r.idvisit, \'_\', r.buster)) conversionId,
                logv.idvisitor, logv.referer_type, logv.referer_name, r.revenue
            from (%s) as r
            RIGHT JOIN '.$logVisitTable.' logv on logv.idvisitor = r.idvisitor WHERE logv.idsite = %s 
                  AND logv.visit_last_action_time >= \'%s\' 
                  AND logv.visit_last_action_time <= lastactiontime
          ) as yyy where %s group by label, sublabel',

            $aggregationSelect, $query['sql'], $idSite, $sinceTime, $outerWhere);

        return $db->query($sql, $query['bind']);
    }

    public function aggregateMultipleReports()
    {
        $idGoals = $this->getGoalIdsToArchive();

        $blobRecordNames = array();

        foreach ($this->configuration->getDaysPriorToConversion() as $daysPriorToConverison) {
            foreach ($idGoals as $idGoal) {
                $blobRecordNames[] = self::completeChannelAttributionRecordName($idGoal, $daysPriorToConverison);
            }
        }

        $sortBy = $this->getColumnToSort();
        $columnsAggregationOperation = null;
        $this->getProcessor()->aggregateDataTableRecords(
            $blobRecordNames, $maxRows = 500, $maxSubRows = 500, $sortBy,
            $columnsAggregationOperation, $columnsToRenameAfterAggregation = null, $countRecursive = false
        );
    }

    private function getColumnToSort()
    {
        // we should sort on one column as it is otherwise random which ones are shown and we have to decide for one
        // model. Using Linear for now as every interactions gets some attribution there but it might look much different
        // for lastInteraction etc. Could change it later
        return Metrics::completeAttributionMetric(Metrics::SUM_CONVERSIONS, new Linear());
    }

    private function getGoalIdsToArchive()
    {
        $idSite = $this->getIdSite();

        if (empty($idSite)) {
            return array();
        }

        return $this->attributions->getSiteAttributionGoalIds($idSite);
    }

    private function getIdSite()
    {
        return $this->getProcessor()->getParams()->getSite()->getId();
    }
}
