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

namespace Piwik\Plugins\AbTesting\Stats;

use Piwik\API\Request;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Plugins\AbTesting\Archiver;
use Piwik\Plugins\AbTesting\Dao;
use Piwik\Plugins\AbTesting\Metrics;

use Piwik\Site;
use Piwik\Plugins\AbTesting\DataTable\Filter\AddValuesOfOriginalToRows;
use Piwik\Plugins\AbTesting\Metrics as PluginMetrics;

class Strategy
{
    const MANN_WHITNEY = 'MW';
    const TTEST = 'TT';
    const CHI_SQUARE = 'CS';

    /**
     * @var Dao\Strategy
     */
    private $dao;

    private $cache = array();

    public function __construct(Dao\Strategy $dao)
    {
        $this->dao = $dao;
    }

    public function getAvailableStrategies()
    {
        return array(self::MANN_WHITNEY, self::TTEST, self::CHI_SQUARE);
    }

    public function isValidStrategy($strategy)
    {
        return !empty($strategy) && in_array($strategy, $this->getAvailableStrategies());
    }

    private function getCachedStrategyIfCached($idExperiment, $metric)
    {
        if (!isset($this->cache[$idExperiment])) {
            $this->cache[$idExperiment] = array();
        }

        if (isset($this->cache[$idExperiment][$metric])) {
            return $this->cache[$idExperiment][$metric];
        }
    }

    private function cacheStrategy($metric, $idExperiment, $strategy)
    {
        $this->cache[$idExperiment][$metric] = $strategy;
    }

    public function getBestStrategyForMetric($metric, $idExperiment, $idSite)
    {
        $tTestMetrics = array(
            PluginMetrics::METRIC_PAGEVIEWS,
            PluginMetrics::METRIC_SUM_VISIT_LENGTH,
            PluginMetrics::METRIC_TOTAL_ORDERS // total orders because there can be more than one orders per visit
        );
        
        if (in_array($metric, $tTestMetrics)) {
            return static::TTEST;
        }

        $cached = $this->getCachedStrategyIfCached($idExperiment, $metric);

        if (isset($cached)) {
            return $cached;
        }

        if (PluginMetrics::isConversionMetric($metric)) {
            $strategy = $this->dao->getStrategy($idExperiment, $metric);
            
            if (!$this->isValidStrategy($strategy)) {
                $strategy = $this->getBestStrategyForConversion($metric, $idSite);
                $this->dao->setStrategy($idExperiment, $metric, $strategy);
            }

            $this->cacheStrategy($metric, $idExperiment, $strategy);
            return $strategy;
        } elseif (PluginMetrics::isBounceMetric($metric)) {
            return static::CHI_SQUARE;
        } elseif (PluginMetrics::isRevenueMetric($metric)) {
            $strategy = $this->dao->getStrategy($idExperiment, $metric);

            if (!$this->isValidStrategy($strategy)) {
                $strategy = $this->getBestStrategyForRevenue($metric, $idSite);
                $this->dao->setStrategy($idExperiment, $metric, $strategy);
            }

            $this->cacheStrategy($metric, $idExperiment, $strategy);
            return $strategy;
        }

        return static::TTEST;
    }

    public function performTtest(Row $row, $metricName)
    {
        $rowStdDev = $row->getColumn($metricName . Archiver::APPENDIX_TTEST_STDDEV_SAMP);
        $rowSum    = $row->getColumn($metricName . Archiver::APPENDIX_TTEST_SUM);
        $rowCount  = $row->getColumn($metricName . Archiver::APPENDIX_TTEST_COUNT);

        $originalPrefix = AddValuesOfOriginalToRows::COLUMN_NAME_PREFIX;
        $originalStdDev = $row->getColumn($originalPrefix . $metricName . Archiver::APPENDIX_TTEST_STDDEV_SAMP);
        $originalSum    = $row->getColumn($originalPrefix . $metricName . Archiver::APPENDIX_TTEST_SUM);
        $originalCount  = $row->getColumn($originalPrefix . $metricName . Archiver::APPENDIX_TTEST_COUNT);

        $significance = new TTest();

        list($originalSum, $originalCount, $originalStdDev) = $significance->flattenValues($originalSum, $originalCount, $originalStdDev);
        list($rowSum, $rowCount, $rowStdDev) = $significance->flattenValues($rowSum, $rowCount, $rowStdDev);

        return $significance->getSignificanceRate($originalSum, $originalCount, $originalStdDev, $rowSum, $rowCount, $rowStdDev);
    }

    public function performChiSquareTest(Row $row, $metricName)
    {
        $visitsMetric = PluginMetrics::METRIC_VISITS;
        if (PluginMetrics::isBounceMetric($metricName)) {
            $visitsMetric = PluginMetrics::METRIC_VISITS_ENTERED;
        }

        $controlVisits = (int) $row->getColumn(AddValuesOfOriginalToRows::COLUMN_NAME_PREFIX . $visitsMetric);
        $controlConversions = (int) $row->getColumn(AddValuesOfOriginalToRows::COLUMN_NAME_PREFIX . $metricName);
        $experimentVisits = (int) $row->getColumn($visitsMetric);
        $experimentConversions = (int) $row->getColumn($metricName);

        $isLowerBetter = PluginMetrics::isBounceMetric($metricName);
        if ($isLowerBetter) {
            // we substract bounces from visits as usually a lower bounce rate is better
            $controlConversions = $controlVisits - $controlConversions;
            $experimentConversions = $experimentVisits - $experimentConversions;
        }

        $chi = new ChiSquare();
        return $chi->getSignificanceRate($controlVisits, $controlConversions, $experimentVisits, $experimentConversions);
    }

    public function performMannWhitneyTest(Row $row, DataTable $table)
    {
        if (!isset($table)) {
            throw new \Exception('No table was set');
        }

        $label = $row->getColumn('label');

        $originalSamples = $table->getRowFromLabel(Archiver::LABEL_NOT_DEFINED);
        if (empty($originalSamples)) {
            return 0;
        }

        $variationSamples = $table->getRowFromLabel($label);
        if (empty($variationSamples)) {
            return 0;
        }

        $originalSamples = $originalSamples->getColumns();
        unset($originalSamples['label']);

        $variationSamples = $variationSamples->getColumns();
        unset($variationSamples['label']);

        if (count($originalSamples) === 0 || count($variationSamples) === 0) {
            return 0;
        }

        $test = new MannWhitneyUTestHistogram();
        return $test->getSignificanceRate($originalSamples, $variationSamples);
    }

    private function getBestStrategyForConversion($metric, $idSite)
    {
        $idGoal = Metrics::getGoalIdFromMetricName($metric);

        if (!empty($idGoal)) {
            $goal = Request::processRequest('Goals.getGoal', array('idSite' => $idSite, 'idGoal' => $idGoal));

            if (!empty($goal['allow_multiple'])) {
                // no binomial distribution
                return static::TTEST;
            }
        } elseif ($metric === PluginMetrics::METRIC_TOTAL_CONVERSIONS) {
            // total orders because there can be many conversions per visit
            return static::TTEST;
        }

        // likely unknown distribution
        return static::CHI_SQUARE;
    }

    private function getBestStrategyForRevenue($metric, $idSite)
    {
        if ($metric === Metrics::METRIC_TOTAL_ORDERS_REVENUE) {
            // likely unknown distribution
            return static::MANN_WHITNEY;

        } elseif ($metric === Metrics::METRIC_TOTAL_REVENUE && !Site::isEcommerceEnabledFor($idSite)) {
            // likely rather similar values as only revenue from goals
            return static::TTEST;
        }

        $idGoal = Metrics::getGoalIdFromMetricName($metric);

        if (!empty($idGoal)) {
            $goal = Request::processRequest('Goals.getGoal', array('idSite' => $idSite, 'idGoal' => $idGoal));

            if (!empty($goal['revenue'])) {
                // likely many similar values
                return static::TTEST;
            }
        }

        // likely unknown distribution
        return static::MANN_WHITNEY;
    }

}
