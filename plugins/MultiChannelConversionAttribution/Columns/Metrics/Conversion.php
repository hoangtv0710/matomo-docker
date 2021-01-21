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
namespace Piwik\Plugins\MultiChannelConversionAttribution\Columns\Metrics;

use Piwik\DataTable;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Metrics\Formatter;
use Piwik\DataTable\Row;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugins\MultiChannelConversionAttribution\Models\Base;

class Conversion extends ProcessedMetric
{
    /**
     * @var string
     */
    private $metric;

    /**
     * @var string
     */
    private $attributionModelName;

    public function __construct($metric, Base $model)
    {
        $this->metric = $metric;
        $this->attributionModelName = $model->getName();
    }

    /**
     * Executed before formatting all metrics for a report. Implementers can return `false`
     * to skip formatting this metric and can use this method to access information needed for
     * formatting (for example, the site ID).
     *
     * @param Report $report
     * @param DataTable $table
     * @return bool Return `true` to format the metric for the table, `false` to skip formatting.
     */
    public function beforeFormat($report, DataTable $table)
    {
        if ($table && $table->getRowsCount() === 1) {
            $row = $table->getFirstRow();
            if ($row && $row->getColumn('label') === -2) {
                // TODO in 4.X replace -2 with DataTable::LABEL_TOTALS_ROW... not using the constant for BC

                // it is the totals row prevent it from being formatted. Workaround for DEV-1838
                // what happens is that ReportTotalsCalculator creates a new dataTable with only the totals row
                // it then calls formatMetrics... because we have regular metrics that use processedMetrics, these
                // regular metrics in the totals row get formatted eg 2524 => 2,524. As a result, the percentages on
                // hover will be shown incorrectly... eg instead of 2524 out of 2942 it will convert the 2,524 to a 2
                // and then calculate percentage 2 out of 2942.
                return false;
            }
        }

        return true;
    }

    public function getName()
    {
        return $this->metric;
    }

    public function getDocumentation()
    {
        return Piwik::translate('MultiChannelConversionAttribution_ColumnConversionsDocumentation', array('"' . $this->attributionModelName . '"'));
    }

    public function getTranslatedName()
    {
        // in the html table UI we don't show full metric name
        return Piwik::translate('Goals_ColumnConversions');
    }

    public function compute(Row $row)
    {
        return $row->getColumn($this->metric);
    }

    public function format($value, Formatter $formatter)
    {
        return $value;
        // will be formatted in the ui/twig template and not here... prevents percentage total values are not calculated correctly
        // when each row has value like 11,876 which then gets converted to 11.
    }

    public function getDependentMetrics()
    {
        return array($this->metric);
    }

}