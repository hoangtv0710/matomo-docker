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

use Piwik\Piwik;
use Piwik\Plugins\MultiChannelConversionAttribution\Models\Base;

/**
 * Metrics
 *
 * @method static \Piwik\Plugins\MultiChannelConversionAttribution\Metrics getInstance()
 */
class Metrics
{
    const SUM_CONVERSIONS = 'nb_attribution_conversions';
    const SUM_REVENUE = 'nb_attribution_revenue';

    public static function completeAttributionMetric($metric, Base $attribution)
    {
        return $metric . '_' . $attribution->getId();
    }

    public static function getMetricsTranslations()
    {
        $metrics = array();
        $conversions = Piwik::translate('Goals_ColumnConversions');
        $revenue = Piwik::translate('General_ColumnRevenue');

        foreach (Base::getAll() as $attribution) {
            $metrics[self::completeAttributionMetric(Metrics::SUM_CONVERSIONS, $attribution)] = $conversions . ' ' . $attribution->getName();
            $metrics[self::completeAttributionMetric(Metrics::SUM_REVENUE, $attribution)] = $revenue . ' ' . $attribution->getName();
        }

        return $metrics;
    }

    public static function getMetricsDocumentationTranslations()
    {
        $metrics = array();
        foreach (Base::getAll() as $attribution) {
            $metrics[self::completeAttributionMetric(Metrics::SUM_CONVERSIONS, $attribution)] =  Piwik::translate('MultiChannelConversionAttribution_ColumnConversionsDocumentation', array('"' . $attribution->getName(). '"'));
            $metrics[self::completeAttributionMetric(Metrics::SUM_REVENUE, $attribution)] = Piwik::translate('MultiChannelConversionAttribution_ColumnRevenueDocumentation', array('"' . $attribution->getName(). '"'));
        }

        return $metrics;
    }

}
