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
use Piwik\Plugins\AbTesting\Metrics as PluginMetrics;

class ConversionRate extends ProcessedMetric
{
    const METRIC_NAME = 'conversion_rate';

    /**
     * @var string
     */
    private $metricName;

    public function __construct($metricName)
    {
        $this->metricName = $metricName;
    }

    public function getName()
    {
        return self::METRIC_NAME;
    }

    public function getTranslatedName()
    {
        if ($this->metricName === PluginMetrics::METRIC_TOTAL_CONVERSIONS) {
            return Piwik::translate('AbTesting_ColumnConversionsPerVisit');
        }

        if ($this->metricName === PluginMetrics::METRIC_TOTAL_ORDERS) {
            return Piwik::translate('AbTesting_ColumnOrdersPerVisit');
        }

        return Piwik::translate('General_ColumnConversionRate');
    }

    public function compute(Row $row)
    {
        $nbVisits = $row->getColumn(PluginMetrics::METRIC_VISITS);
        $nbVisitsConverted = $row->getColumn($this->metricName);

        return Piwik::getQuotientSafe($nbVisitsConverted, $nbVisits, $precision = 4);
    }

    public function format($value, Formatter $formatter)
    {
        if ($this->metricName === PluginMetrics::METRIC_TOTAL_CONVERSIONS
            || $this->metricName === PluginMetrics::METRIC_TOTAL_ORDERS) {
            if ($value > 2) {
                return round($value, 1);
            }
            return round($value, 2);
        }

        return $formatter->getPrettyPercentFromQuotient($value);
    }

    public function getDependentMetrics()
    {
        return array();
    }
}