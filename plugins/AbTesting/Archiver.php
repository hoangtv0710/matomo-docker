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

use Piwik\ArchiveProcessor;
use Piwik\Common;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\AbTesting\Archiver\Aggregator;
use Piwik\Plugins\AbTesting\Archiver\DataArray;
use Piwik\Plugins\AbTesting\Model\Experiments;
use Piwik\Plugins\AbTesting\Stats\Strategy;
use Piwik\Plugins\AbTesting\Tracker\RequestProcessor;
use Piwik\Tracker\GoalManager;

class Archiver extends \Piwik\Plugin\Archiver
{
    const APPENDIX_TTEST_STDDEV_SAMP = '_stddev_samp';
    const APPENDIX_TTEST_SUM = '_sum';
    const APPENDIX_TTEST_COUNT = '_count';

    /**
     * @var int
     */
    private $maximumRowsInDataTable;

    /**
     * @var Experiments
     */
    private $experiments;

    /**
     * @var Aggregator
     */
    private $aggregator;

    /**
     * @var Strategy
     */
    private $strategy;

    const ABTESTING_ARCHIVE_RECORD = "AbTesting_experiment_";

    const LABEL_NOT_DEFINED = 'AbTesting_ValueNotSet';

    public function __construct(ArchiveProcessor $processor)
    {
        parent::__construct($processor);

        $generalConfig = Config::getInstance()->General;
        
        $this->maximumRowsInDataTable = $generalConfig['datatable_archiving_maximum_rows_standard'];
        $this->experiments = StaticContainer::get('Piwik\Plugins\AbTesting\Model\Experiments');
        $this->strategy = StaticContainer::get('Piwik\Plugins\AbTesting\Stats\Strategy');
        $this->aggregator = new Aggregator($this->getLogAggregator());
    }

    /**
     * Get the record name for an experiment archive.
     * @param int $idExperiment
     * @return string
     */
    public static function getExperimentRecordName($idExperiment)
    {
        return static::ABTESTING_ARCHIVE_RECORD . (int) $idExperiment;
    }

    /**
     * Get the record name for an experiment archive.
     * @param int $idExperiment
     * @param int $metricName
     * @return string
     */
    public static function getExperimentSampleRecordName($idExperiment, $metricName)
    {
        return static::ABTESTING_ARCHIVE_RECORD . (int) $idExperiment . '_sample_' . $metricName;
    }

    public function aggregateDayReport()
    {
        $idSite = $this->getIdSite();

        if (!isset($idSite)) {
            return;
        }

        $experiments = $this->experiments->getExperimentsWithReports($idSite);
        
        foreach ($experiments as $experiment) {
            $idExperiment = $experiment['idexperiment'];

            $expectedNumRows = array();

            $idGoals = $this->getIdGoalsToArchiveFromExperiment($experiment);

            $dataArray = new DataArray();
            $dataArray->setIdGoals($idGoals);

            // we always archive this one as it archives visits, unique visitors
            $cursor = $this->aggregator->aggregateVisitMetrics($experiment);
            $this->addRowsToDataArray($dataArray, $cursor);

            // we always archive this one as it archives visits entered , unique visitors entered
            $cursor = $this->aggregator->aggregateBouncesAndEnteredVisits($experiment);
            $this->addRowsToDataArray($dataArray, $cursor);

            // TODO only archive for finished reports when they finished today, all others do not need to be archived anymore
            // do they? on the other side we can still archive them and it would simply generate 0 data, they should not archive

            if ($this->hasSuccessMetric($experiment, Metrics::METRIC_PAGEVIEWS)) {
                $cursor = $this->aggregator->aggregatePageviews($experiment);
                $this->addRowsToDataArray($dataArray, $cursor);
            }

            if ($this->hasSuccessMetric($experiment, array(Metrics::METRIC_TOTAL_CONVERSIONS, Metrics::METRIC_TOTAL_REVENUE))) {
                $cursor = $this->aggregator->aggregateGoalConversions($experiment, $idGoal = null, Metrics::METRIC_TOTAL_CONVERSIONS, Metrics::METRIC_TOTAL_REVENUE);
                $this->addRowsToDataArray($dataArray, $cursor);
            }

            if ($this->hasSuccessMetric($experiment, array(Metrics::METRIC_TOTAL_ORDERS, Metrics::METRIC_TOTAL_ORDERS_REVENUE))) {
                $idGoal = GoalManager::IDGOAL_ORDER;
                $cursor = $this->aggregator->aggregateGoalConversions($experiment, $idGoal, Metrics::METRIC_TOTAL_ORDERS, Metrics::METRIC_TOTAL_ORDERS_REVENUE);
                $this->addRowsToDataArray($dataArray, $cursor);
            }

            foreach ($idGoals as $idGoal) {
                $conversionName = Metrics::getMetricNameConversionGoal($idGoal);
                $revenueName = Metrics::getMetricNameRevenueGoal($idGoal);

                if ($this->hasSuccessMetric($experiment, array($conversionName, $revenueName))) {
                    $cursor = $this->aggregator->aggregateGoalConversions($experiment, $idGoal, $conversionName, $revenueName);
                    $this->addRowsToDataArray($dataArray, $cursor);
                }
            }

            $labels = $dataArray->getLabels();
            foreach ($labels as $index => $label) {
                if ($label === Archiver::LABEL_NOT_DEFINED) {
                    $label = '';
                    $labels[$index] = $label;
                }
                
                $expectedNumRows[$label] = $dataArray->getNumVisitsForLabel($label);
            }

            $conversionMetrics = array(
                Metrics::METRIC_TOTAL_CONVERSIONS => null,
                Metrics::METRIC_TOTAL_ORDERS => GoalManager::IDGOAL_ORDER
            );
            $revenueMetrics = array(
                Metrics::METRIC_TOTAL_REVENUE => null,
                Metrics::METRIC_TOTAL_ORDERS_REVENUE => GoalManager::IDGOAL_ORDER
            );

            foreach ($idGoals as $idGoal) {
                $conversionMetrics[Metrics::getMetricNameConversionGoal($idGoal)] = $idGoal;
                $revenueMetrics[Metrics::getMetricNameRevenueGoal($idGoal)] = $idGoal;
            }

            // TTEST CALCULATIONS
            foreach ($labels as $label) {
                
                if ($this->hasSuccessMetric($experiment, Metrics::METRIC_PAGEVIEWS)) {
                    $query = $this->aggregator->getDistributionPageviewQuery($experiment, $label);
                    $cursor = $this->aggregator->calcDistributionValues(Metrics::METRIC_PAGEVIEWS, $query);
                    $this->addRowsToDataArray($dataArray, $cursor);
                }

                if ($this->hasSuccessMetric($experiment, Metrics::METRIC_SUM_VISIT_LENGTH)) {
                    $query = $this->aggregator->getDistributionVisitTotalTimeQuery($experiment, $label);
                    $cursor = $this->aggregator->calcDistributionValues(Metrics::METRIC_SUM_VISIT_LENGTH, $query);
                    $this->addRowsToDataArray($dataArray, $cursor);
                }

                foreach ($revenueMetrics as $revenueMetric => $idGoal) {
                    if (!$this->hasSuccessMetric($experiment, $revenueMetric)) {
                        continue;
                    }
                    
                    $bestStrategy = $this->strategy->getBestStrategyForMetric($revenueMetric, $idExperiment, $idSite);
                    if ($bestStrategy === Strategy::TTEST) {
                        $query = $this->aggregator->getDistributionRevenueForTtest($experiment, $label, $idGoal);
                        $cursor = $this->aggregator->calcDistributionValues($revenueMetric, $query);
                        $this->addRowsToDataArray($dataArray, $cursor);
                    }
                }

                foreach ($conversionMetrics as $conversionMetric => $idGoal) {
                    if (!$this->hasSuccessMetric($experiment, $conversionMetric)) {
                        continue;
                    }
                    $bestStrategy = $this->strategy->getBestStrategyForMetric($conversionMetric, $idExperiment, $idSite);
                    if ($bestStrategy === Strategy::TTEST) {
                        $query = $this->aggregator->getDistributionConversionForTtest($experiment, $label, $idGoal);
                        $cursor = $this->aggregator->calcDistributionValues($conversionMetric, $query);
                        $this->addRowsToDataArray($dataArray, $cursor);
                    }
                }
            }

            $recordName = self::getExperimentRecordName($idExperiment);
            $this->insertDataArray($recordName, $dataArray);
            unset($dataArray);
            unset($cursor);

            // MANN WHITNEY HISTOGRAM TABLE CALCULATIONS
            foreach ($revenueMetrics as $metric => $idGoal) {
                if (!$this->hasSuccessMetric($experiment, $metric)) {
                    continue;
                }

                $bestStrategy = $this->strategy->getBestStrategyForMetric($metric, $idExperiment, $idSite);

                if ($bestStrategy === Strategy::MANN_WHITNEY) {
                    $dataArray = new DataArray();

                    foreach ($labels as $label) {
                        $cursor = $this->aggregator->getDistributionRevenueForMannWhitney($experiment, $label, $idGoal);
                        $this->addVariationRowsToDataArray($label, $expectedNumRows[$label], $dataArray, $cursor);
                    }

                    $recordName = self::getExperimentSampleRecordName($idExperiment, $metric);
                    $this->insertDataArray($recordName, $dataArray);
                    unset($cursor);
                    unset($dataArray);
                }
            }
        }
    }

    protected function getSuccessMetricsFromExperiment($experiment)
    {
        $metrics = array();
        foreach ($experiment['success_metrics'] as $metric) {
            if (empty($metric['metric'])) {
                continue;
            }
            $metrics[] = $metric['metric'];
        }
        return $metrics;
    }

    protected function hasSuccessMetric($experiment, $neededMetric)
    {
        if (!is_array($neededMetric)) {
            $neededMetric = array($neededMetric);
        }
        $experimentMetrics = $this->getSuccessMetricsFromExperiment($experiment);
        foreach ($experimentMetrics as $metricName) {

            foreach ($neededMetric as $m) {
                if ($metricName === $m) {
                    return true;
                }
            }

        }

        return false;
    }

    protected function getIdGoalsToArchiveFromExperiment($experiment)
    {
        $idGoals = array();

        $experimentMetrics = $this->getSuccessMetricsFromExperiment($experiment);

        foreach ($experimentMetrics as $successMetric) {
            $idGoal = Metrics::getGoalIdFromMetricName($successMetric);

            if (!empty($idGoal)) {
                $idGoals[] = (int) $idGoal;
            }
        }

        return array_values(array_unique($idGoals));
    }

    private function insertDataArray($recordName, DataArray $dataArray)
    {
        $table = $dataArray->asDataTable();

        $serialized = $table->getSerialized($this->maximumRowsInDataTable);
        $this->getProcessor()->insertBlobRecord($recordName, $serialized);

        Common::destroy($table);
        unset($table);
        unset($serialized);
    }

    public function aggregateMultipleReports()
    {
        $idSite = $this->getIdSite();

        if (!isset($idSite)) {
            return;
        }

        $experiments = $this->experiments->getExperimentsWithReports($idSite);

        foreach ($experiments as $experiment) {
            $idExperiment = $experiment['idexperiment'];

            $experimentMetrics = $this->getSuccessMetricsFromExperiment($experiment);

            // AGGREGATE MANN WHITNEY HISTOGRAM TABLES
            foreach ($experimentMetrics as $metric) {
                $bestStrategy = $this->strategy->getBestStrategyForMetric($metric, $idExperiment, $idSite);

                if ($bestStrategy === Strategy::MANN_WHITNEY) {
                    $recordName = static::getExperimentSampleRecordName($idExperiment, $metric);
                    $this->getProcessor()->aggregateDataTableRecords($recordName, $this->maximumRowsInDataTable);
                }
            }

            // AGGREGATE REGULAR TABLE INCLUDING TTEST METRICS
            $recordName = self::getExperimentRecordName($idExperiment);

            $columnsAggregationOperation = $this->getMultipleReportsAggregationOperations($experiment);

            $this->getProcessor()->aggregateDataTableRecords(
                $recordName,
                $this->maximumRowsInDataTable,
                $maximumRowsInSubDataTable = null,
                $columnToSortByBeforeTruncation = null,
                $columnsAggregationOperation
            );
        }
    }

    public function getMultipleReportsAggregationOperations($experiment)
    {
        $experimentMetrics = $this->getSuccessMetricsFromExperiment($experiment);

        $ttestMetrics = array();

        foreach ($experimentMetrics as $metric) {
            $ttestMetrics[] = $metric;
        }

        $unique = $this->aggregator->getUniqueVisitors($experiment, $onlyEntered = false);
        $uniqueEntered = $this->aggregator->getUniqueVisitors($experiment, $onlyEntered = true);

        $columnsAggregationOperation = array();

        $aggregate = function ($thisValue, $otherValue, $thisRow, $otherRow) {
            if (!is_array($thisValue)) {
                $thisValue = array($thisValue);
            }

            if (is_array($otherValue)) {
                foreach ($otherValue as $val) {
                    $thisValue[] = $val;
                }
            } else {
                $thisValue[] = $otherValue;
            }

            return $thisValue;
        };

        foreach ($ttestMetrics as $metric) {
            $columnsAggregationOperation[$metric . Archiver::APPENDIX_TTEST_SUM] = $aggregate;
            $columnsAggregationOperation[$metric . Archiver::APPENDIX_TTEST_COUNT] = $aggregate;
            $columnsAggregationOperation[$metric . Archiver::APPENDIX_TTEST_STDDEV_SAMP] = $aggregate;
        }

        $columnsAggregationOperation[Metrics::METRIC_UNIQUE_VISITORS] = function ($theFirstRow, $other, $thisRow, $otherRow) use ($unique) {
            $label = $thisRow->getColumn('label');
            if ($label === Archiver::LABEL_NOT_DEFINED) {
                $label = RequestProcessor::VARIATION_ORIGINAL_ID;
            }

            foreach ($unique as $row) {
                if ($row['label'] == $label) {
                    return $row['uniqueVisitors'];
                }
            }
        };

        $columnsAggregationOperation[Metrics::METRIC_UNIQUE_VISITORS_ENTERED] = function ($theFirstRow, $other, $thisRow, $otherRow) use ($uniqueEntered) {
            $label = $thisRow->getColumn('label');

            if ($label === Archiver::LABEL_NOT_DEFINED) {
                $label = RequestProcessor::VARIATION_ORIGINAL_ID;
            }

            foreach ($uniqueEntered as $row) {
                if ($row['label'] == $label) {
                    return $row['uniqueVisitors'];
                }
            }
        };

        return $columnsAggregationOperation;
    }

    private function addRowsToDataArray(DataArray $dataArray, $cursor)
    {
        while ($row = $cursor->fetch()) {
            $dataArray->computeMetrics($row);
        }
        $cursor->closeCursor();
    }
    
    private function addVariationRowsToDataArray($label, $totalRowsExpected, DataArray $dataArray, $cursor)
    {
        $numRows = 0;
        while ($row = $cursor->fetch()) {
            $dataArray->compureVariationMetrics($label, $row);
            $numRows += $row['revenueCount'];
        }

        $numFurtherZeros = $totalRowsExpected - $numRows;
        if ($numFurtherZeros > 0) {
            $dataArray->compureVariationMetrics($label, array('revenue' => 0, 'revenueCount' => $numFurtherZeros));
        }

        $cursor->closeCursor();
    }

    protected function getIdSite()
    {
        $idSites = $this->getProcessor()->getParams()->getIdSites();

        if (count($idSites) > 1) {
            return null;
        }

        return reset($idSites);
    }
}
