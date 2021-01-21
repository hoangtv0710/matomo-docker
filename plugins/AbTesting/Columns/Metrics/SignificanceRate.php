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
namespace Piwik\Plugins\AbTesting\Columns\Metrics;


use Piwik\DataTable;
use Piwik\DataTable\Row;

use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\AbTesting\Archiver;
use Piwik\Plugins\AbTesting\Stats\Strategy;
use Piwik\Plugins\AbTesting\DataTable\Filter\AddValuesOfOriginalToRows;
use Piwik\Plugins\AbTesting\Metrics as PluginMetrics;

// especially calculating the significance rate for revenue may be a bit slow. In this case we would need to
// move this into the archiver and eg modify `aggregateTableRecords` to accept a list of processed metrics that will
// be processed on archiving
class SignificanceRate extends ProcessedMetric
{
    const NUM_REQUIRED_VISITS_FOR_SIGNIFICANCE = 15;
    const NOT_ENOUGH_VISITORS = 'NOT_ENOUGH_VISITS';
    const METRIC_NAME = 'significance_rate';

    /**
     * @var string
     */
    private $metricName;

    /**
     * @var DataTable
     */
    private $table;

    /**
     * @var Strategy
     */
    private $strategyFactory;

    /**
     * @var array
     */
    private $experiment;

    /**
     * See Stats\Factory which strategy should be used
     * @var string
     */
    private $strategy;

    public function __construct(Strategy $strategy, $experiment, $metricName)
    {
        $this->strategyFactory = $strategy;
        $this->experiment = $experiment;
        $this->metricName = $metricName;
        $this->strategy = $this->strategyFactory->getBestStrategyForMetric(
            $this->metricName,
            $this->experiment['idexperiment'],
            $this->experiment['idsite']
        );
    }

    public function getRecordNameIfNeedsDataTable()
    {
        if ($this->strategy === Strategy::MANN_WHITNEY) {
            return Archiver::getExperimentSampleRecordName($this->experiment['idexperiment'], $this->metricName);
        }
    }

    public function setDataTableWithSamples(DataTable $table)
    {
        $this->table = $table;
    }

    public function getName()
    {
        return self::METRIC_NAME;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('AbTesting_ColumnSignificanceRate');
    }

    public function getDocumentation()
    {
        return Piwik::translate('AbTesting_ColumnSignificanceRateDocumentation');
    }

    public function compute(Row $row)
    {
        if ($this->isOriginalVariationRow($row)) {
            return '-';
        }

        $visitMetric = PluginMetrics::METRIC_UNIQUE_VISITORS;
        if (PluginMetrics::isBounceMetric($this->metricName)) {
            $visitMetric = PluginMetrics::METRIC_UNIQUE_VISITORS_ENTERED;
        }

        $controlVisits = (int) $row->getColumn(AddValuesOfOriginalToRows::COLUMN_NAME_PREFIX . $visitMetric);
        $experimentVisits = (int) $row->getColumn($visitMetric);

        if ($controlVisits < static::NUM_REQUIRED_VISITS_FOR_SIGNIFICANCE
            || $experimentVisits < static::NUM_REQUIRED_VISITS_FOR_SIGNIFICANCE) {

            return static::NOT_ENOUGH_VISITORS;
        }

        switch ($this->strategy) {
            case Strategy::MANN_WHITNEY:
                $rate = $this->strategyFactory->performMannWhitneyTest($row, $this->table);
                break;
            case Strategy::CHI_SQUARE:
                $rate = $this->strategyFactory->performChiSquareTest($row, $this->metricName);
                break;
            default:
                $rate = $this->strategyFactory->performTtest($row, $this->metricName);
                break;
        }

        return $rate;
    }

    public function format($value, Formatter $formatter)
    {
        if ($value === '-') {
            return $value;
        }

        if ($value === static::NOT_ENOUGH_VISITORS || $value <= 50) {
            return '<= 50%';
        }

        $precision = 1;
        if (abs($value) < 90) {
            $precision = 0;
        } elseif (abs($value) >= 98) {
            $precision = 2;
        }

        return $this->roundDown($value, $precision) . '%';
    }

    private function roundDown($value, $precision)
    {
        $number = (int) str_pad('1', $precision + 1, '0');

        return (floor($value * $number) / $number);
    }

    public function getDependentMetrics()
    {
        return array();
    }
}