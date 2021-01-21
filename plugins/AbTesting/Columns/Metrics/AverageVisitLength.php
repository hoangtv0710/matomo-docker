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

class AverageVisitLength extends ProcessedMetric
{
    public function getName()
    {
        return PluginMetrics::METRIC_AVERAGE_PREFIX . PluginMetrics::METRIC_SUM_VISIT_LENGTH;
    }

    public function compute(Row $row)
    {
        $sumVisitLength = $this->getMetric($row, PluginMetrics::METRIC_SUM_VISIT_LENGTH);
        $nbVisits = $this->getMetric($row, PluginMetrics::METRIC_VISITS);

        return Piwik::getQuotientSafe($sumVisitLength, $nbVisits, $precision = 0);
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyTimeFromSeconds($value);
    }

    public function getTranslatedName()
    {
        return Piwik::translate('General_ColumnAvgTimeOnSite');
    }

    public function getDependentMetrics()
    {
        return array(PluginMetrics::METRIC_SUM_VISIT_LENGTH, PluginMetrics::METRIC_VISITS);
    }
}