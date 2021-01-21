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

use Piwik\DataTable\Row;

use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\AbTesting\DataTable\Filter\AddValuesOfOriginalToRows;
use Piwik\Plugins\AbTesting\Metrics as PluginMetrics;

class DetectedEffect extends ProcessedMetric
{
    const METRIC_NAME = 'detected_effect';
    
    /**
     * @var string
     */
    private $metricName;

    public function __construct($metricName)
    {
        $this->metricName = $metricName;
    }

    public function getDocumentation()
    {
        return Piwik::translate('AbTesting_ColumnDetectedEffectDocumentation');
    }

    public function getName()
    {
        return self::METRIC_NAME;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('AbTesting_ColumnDetectedEffect');
    }

    public function compute(Row $row)
    {
        if ($this->isOriginalVariationRow($row)) {
            return '-';
        }

        $originalPrefix = AddValuesOfOriginalToRows::COLUMN_NAME_PREFIX;

        $rowVisits = $row->getColumn(PluginMetrics::METRIC_VISITS);
        $originalVisits = $row->getColumn($originalPrefix . PluginMetrics::METRIC_VISITS);

        $metricValue = $row->getColumn($this->metricName);
        $originalMetricValue = $row->getColumn($originalPrefix . $this->metricName);

        $factor = 1;
        if (PluginMetrics::isBounceMetric($this->metricName)) {
            $factor = -1; // lower is better

            // we only calculate detected effect / improvement rate on entered visits
            $rowVisits = $row->getColumn(PluginMetrics::METRIC_VISITS_ENTERED);
            $originalVisits = $row->getColumn($originalPrefix . PluginMetrics::METRIC_VISITS_ENTERED);
        }

        $currentValue = Piwik::getQuotientSafe($metricValue, $rowVisits, 4);
        $pastValue = Piwik::getQuotientSafe($originalMetricValue, $originalVisits, 4);

        $dividend = $currentValue - $pastValue;
        $divisor = $pastValue;

        if ($dividend == 0) {
            $rate = 0;
        } else if ($divisor == 0) {
            $rate = $factor * 1;
        } else {
            $rate = $factor * Piwik::getQuotientSafe($dividend, $divisor, 4);
        }

        return $rate * 100;
    }

    public function format($value, Formatter $formatter)
    {
        if ($value === '-') {
            return $value;
        }

        $precision = 1;
        if (abs($value) >= 80) {
            $precision = 0;
        } elseif (abs($value) < 8) {
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