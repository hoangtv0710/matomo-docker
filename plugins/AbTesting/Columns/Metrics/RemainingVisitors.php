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

use Piwik\API\Request;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Date;

use Piwik\Metrics\Formatter;
use Piwik\Piwik;


use Piwik\Plugins\AbTesting\DataTable\Filter\AddValuesOfOriginalToRows;
use Piwik\Plugins\AbTesting\Stats\SampleSize;
use Piwik\Plugins\AbTesting\Metrics as PluginMetrics;
use Piwik\Plugins\AbTesting\Stats\Strategy;

// especially calculating the significance rate for revenue may be a bit slow. In this case we would need to
// move this into the archiver and eg modify `aggregateTableRecords` to accept a list of processed metrics that will
// be processed on archiving
class RemainingVisitors extends ProcessedMetric
{
    const MIN_REQUIRED_VISITORS = 40;
    const MIN_REQUIRED_VISITS_FOR_CONVERSIONS = 100;
    const MIN_REQUIRED_CONVERSIONS = 10;

    const ENOUGH_VISITORS = '-';

    const METRIC_NAME = 'remaining_visitors';

    /**
     * @var string
     */
    private $metricName;

    /**
     * @var array
     */
    private $experiment;

    /**
     * @var string
     */
    private $bestStrategy;

    public function __construct(Strategy $strategy, $experiment, $metricName)
    {
        $this->experiment = $experiment;
        $this->metricName = $metricName;
        $this->bestStrategy = $strategy->getBestStrategyForMetric($this->metricName, $experiment['idexperiment'], $experiment['idsite']);
    }

    public function getName()
    {
        return self::METRIC_NAME;
    }

    public function getTranslatedName()
    {
        return Piwik::translate('AbTesting_ColumnRemainingVisitors');
    }

    public function getDocumentation()
    {
        return Piwik::translate('AbTesting_ColumnRemainingVisitorsDocumentation');
    }

    public function compute(Row $row)
    {
        if ($this->isOriginalVariationRow($row)) {
            return '-';
        }

        $sampleSize = new SampleSize();

        $currentNumVisitors = $this->getNumCurrentVisitorsForThisVariation($row);

        if (Strategy::CHI_SQUARE === $this->bestStrategy) {
            $conversionRate = $this->getConversionRateFromOriginalVariation($row);

            if (!isset($conversionRate)) {
                // fallback to past data
                $conversionRate = $this->getConversionRateFromPastData();

                if (empty($conversionRate)) {
                    // no conversion rate for this exists so far, we wait for more visits
                    $neededVisitors = self::MIN_REQUIRED_VISITS_FOR_CONVERSIONS - $currentNumVisitors;

                    if ($neededVisitors < 10) { // 10 is just a randomly picked value
                        // there are possibly enough visitors, but not enough conversions yet to calculate an initial
                        // conversion rate, we need more visitors. This happens when conversion rate is very low and
                        // we need to adjust the estimation
                        return self::MIN_REQUIRED_VISITS_FOR_CONVERSIONS;
                    }

                    return $neededVisitors;
                }
            }

            if (is_string($conversionRate)) {
                $conversionRate = $this->convertStringToNumber($conversionRate);
            }

            if (is_string($this->experiment['mde_relative'])) {
                $this->experiment['mde_relative'] = $this->convertStringToNumber($this->experiment['mde_relative']);
            }

            if (empty($this->experiment['mde_relative'])) {
                $this->experiment['mde_relative'] = 15;
            }

            $minimumDetectableEffectAbsolute = ($conversionRate / 100) * $this->experiment['mde_relative'];

            $neededVisitors = $sampleSize->estimateForConversions($this->experiment['confidence_threshold'], $conversionRate, $minimumDetectableEffectAbsolute);

            if ($neededVisitors < self::MIN_REQUIRED_VISITORS) {
                $neededVisitors = self::MIN_REQUIRED_VISITORS;
            }

        } else {

            $relativeImprovement = SampleSize::DEFAULT_PAGEVIEW_CONVERSIONRATE * ($this->experiment['mde_relative'] / 100);
            
            $neededVisitors = $sampleSize->estimateForPageviews($this->experiment['confidence_threshold'], $relativeImprovement);
        }

        return $neededVisitors - $currentNumVisitors;
    }

    private function convertStringToNumber($conversionRate)
    {
        $conversionRate = trim($conversionRate);
        $thousandSeparator = Piwik::translate('Intl_NumberSymbolGroup');
        $decimalSeparator = Piwik::translate('Intl_NumberSymbolDecimal');

        $conversionRate = str_replace('%', '', $conversionRate);
        $conversionRate = str_replace($thousandSeparator, '', $conversionRate);
        $conversionRate = str_replace($decimalSeparator, '.', $conversionRate);
        $conversionRate = trim($conversionRate);

        $conversionRate = filter_var($conversionRate, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_SCIENTIFIC);

        return $conversionRate;
    }

    public function getNumCurrentVisitorsForThisVariation(Row $row)
    {
        if (PluginMetrics::isBounceMetric($this->metricName)) {
            $visitors = $row->getColumn(PluginMetrics::METRIC_UNIQUE_VISITORS_ENTERED);
        } else {
            $visitors = $row->getColumn(PluginMetrics::METRIC_UNIQUE_VISITORS);
        }

        if (empty($visitors)) {
            return 0;
        }

        return $visitors;
    }

    public function getConversionRateFromOriginalVariation(Row $row)
    {
        $originalPrefix = AddValuesOfOriginalToRows::COLUMN_NAME_PREFIX;

        if (PluginMetrics::isBounceMetric($this->metricName)) {
            $nbVisits = $row->getColumn($originalPrefix . PluginMetrics::METRIC_VISITS_ENTERED);
        } else {
            $nbVisits = $row->getColumn($originalPrefix . PluginMetrics::METRIC_VISITS);
        }

        $nbVisitsConverted = $row->getColumn($originalPrefix . $this->metricName);

        if ($nbVisitsConverted >= self::MIN_REQUIRED_CONVERSIONS && $nbVisits >= self::MIN_REQUIRED_VISITS_FOR_CONVERSIONS) {

            $conversionRate = Piwik::getQuotientSafe($nbVisitsConverted, $nbVisits, $precision = 4);
            $conversionRate = abs($conversionRate);
            $conversionRate = $conversionRate * 100;

            return $conversionRate;
        }

        return null;
    }

    public function getConversionRateFromPastData()
    {
        $apiMethod = 'Goals.get';
        $columns = 'conversion_rate';
        if (PluginMetrics::isBounceMetric($this->metricName)) {
            $apiMethod = 'VisitsSummary.get';
            $columns = 'bounce_rate';
        }

        $startDate = Date::factory($this->experiment['start_date'])->subDay(1);
        $previousDate = Date::factory($this->experiment['start_date'])->subDay(29);

        $params = array(
            'period' => 'range', 'date' => $previousDate->toString() . ',' . $startDate->toString(),
            'idSite' => $this->experiment['idsite'],
            'columns' => $columns);

        $idGoal = PluginMetrics::getGoalIdFromMetricName($this->metricName);

        if (!empty($idGoal)) {
            $params['idGoal'] = $idGoal;
        }

        /** @var DataTable $data */
        $data = Request::processRequest($apiMethod, $params);

        $firstRow = $data->getFirstRow();

        if (!empty($firstRow)) {
            $conversionRate = $firstRow->getColumn('conversion_rate');

            if (false === $conversionRate) {
                $conversionRate = $firstRow->getColumn('bounce_rate');
            }

            if (false === $conversionRate) {
                $conversionRate = $firstRow->getColumn('value');
            }

            if (false !== $conversionRate) {
                $conversionRate = str_replace('%', '', $conversionRate);
                return Common::forceDotAsSeparatorForDecimalPoint($conversionRate);
            }
        }

        return null;
    }

    public function format($value, Formatter $formatter)
    {
        if ($value === '-') {
            return $value;
        }

        if ($value <= 0) {
            return self::ENOUGH_VISITORS;
        }

        return (int) ceil($value);
    }

    public function getDependentMetrics()
    {
        return array();
    }
}