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

class BounceRate extends ProcessedMetric
{
    const METRIC_NAME = 'bounce_rate';

    public function getName()
    {
        return self::METRIC_NAME;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('General_ColumnBounceRate');
    }

    public function compute(Row $row)
    {
        $nbVisits = $row->getColumn(PluginMetrics::METRIC_VISITS_ENTERED);
        $nbVisitsConverted = $row->getColumn(PluginMetrics::METRIC_BOUNCE_COUNT);

        return Piwik::getQuotientSafe($nbVisitsConverted, $nbVisits, $precision = 3);
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyPercentFromQuotient($value);
    }

    public function getDependentMetrics()
    {
        return array(PluginMetrics::METRIC_BOUNCE_COUNT, PluginMetrics::METRIC_VISITS_ENTERED);
    }
}