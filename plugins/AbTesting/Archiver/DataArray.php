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

namespace Piwik\Plugins\AbTesting\Archiver;



use Piwik\Plugins\AbTesting\Archiver;
use Piwik\Plugins\AbTesting\Metrics;
use Piwik\Plugins\AbTesting\Tracker\RequestProcessor;

class DataArray extends \Piwik\DataArray
{
    /**
     * @var array
     */
    private $idGoals = array();
    
    public function getLabels()
    {
        return array_keys($this->data);
    }

    public function getNumVisitsForLabel($label)
    {
        if (empty($label) && !isset($this->data[$label])) {
            $label = Archiver::LABEL_NOT_DEFINED;
        }
        
        if (isset($this->data[$label][Metrics::METRIC_VISITS])) {
            return $this->data[$label][Metrics::METRIC_VISITS];
        }
        
        return 0;
    }
    
    public function setIdGoals($idGoals)
    {
        $this->idGoals = $idGoals;
    }

    /**
     * Returns an empty row containing default metrics
     *
     * @return array
     */
    public function createEmptyRow()
    {
        $metrics = array(
            Metrics::METRIC_VISITS => 0,
            Metrics::METRIC_VISITS_ENTERED => 0,
            Metrics::METRIC_UNIQUE_VISITORS => 0,
            Metrics::METRIC_UNIQUE_VISITORS_ENTERED => 0,
            Metrics::METRIC_BOUNCE_COUNT => 0,
            Metrics::METRIC_SUM_VISIT_LENGTH => 0,
            Metrics::METRIC_TOTAL_CONVERSIONS => 0,
            Metrics::METRIC_TOTAL_REVENUE => 0,
            Metrics::METRIC_PAGEVIEWS => 0,
            Metrics::METRIC_TOTAL_ORDERS => 0,
            Metrics::METRIC_TOTAL_ORDERS_REVENUE => 0
        );

        foreach ($this->idGoals as $idGoal) {
            $metrics[Metrics::getMetricNameConversionGoal($idGoal)] = 0;
            $metrics[Metrics::getMetricNameRevenueGoal($idGoal)] = 0;
        }

        return $metrics;
    }

    /**
     * @param $row
     */
    public function computeMetrics($row)
    {
        $label = $row['label'];

        if (empty($label) || $label == RequestProcessor::VARIATION_ORIGINAL_ID) {
            $label = Archiver::LABEL_NOT_DEFINED;
        }

        if (!isset($this->data[$label])) {
            $this->data[$label] = $this->createEmptyRow();
        }

        foreach ($row as $column => $value) {
            if (isset($this->data[$label][$column])) {
                $this->data[$label][$column] += $value;
            } else {
                $this->data[$label][$column] = $value;
            }
        }
    }

    /**
     * @param string $label
     * @param $row
     */
    public function compureVariationMetrics($label, $row)
    {
        if (empty($label)) {
            $label = Archiver::LABEL_NOT_DEFINED;
        }

        if (!isset($this->data[$label])) {
            $this->data[$label] = array();
        }

        $column = (string) $row['revenue'];
        $value = $row['revenueCount'];

        if (isset($this->data[$label][$column])) {
            $this->data[$label][$column] += $value;
        } else {
            $this->data[$label][$column] = $value;
        }
    }

}
