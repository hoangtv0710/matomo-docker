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

use Piwik\Piwik;
use Piwik\Metrics\Formatter;
use Piwik\Plugin\ProcessedMetric;
use Piwik\DataTable\Row;
use Piwik\Plugins\MultiChannelConversionAttribution\Models\Base;
use Piwik\Plugins\MultiChannelConversionAttribution\Metrics;

class AttributionComparison extends ProcessedMetric
{
    /**
     * @var string
     */
    private $baseName;

    /**
     * @var string
     */
    private $baseMetric;

    /**
     * @var Base
     */
    private $compareName;

    /**
     * @var Base
     */
    private $compareMetric;

    /**
     * AttributionComparison constructor.
     * @param string $metric
     * @param Base $baseModel
     * @param Base $compareModel
     */
    public function __construct($metric, $baseModel, $compareModel)
    {
        $this->baseName = $baseModel->getName();
        $this->compareName = $compareModel->getName();
        $this->baseMetric = Metrics::completeAttributionMetric($metric, $baseModel);
        $this->compareMetric = Metrics::completeAttributionMetric($metric, $compareModel);
    }

    public function getTranslatedName()
    {
        return $this->compareName;
    }

    public function getDocumentation()
    {
        return Piwik::translate('MultiChannelConversionAttribution_XInComparisonTo', array($this->baseName, $this->compareName));
    }

    public function getName()
    {
        return 'comparison_' . $this->compareMetric;
    }

    public function compute(Row $row)
    {
        $value = $row->getColumn($this->compareMetric);
        $previousValue = $row->getColumn($this->baseMetric);
        $diff = $value - $previousValue;

        if ($diff == 0) {
            return 0;
        }

        if ($previousValue == 0) {
            return 1;
        }

        return Piwik::getQuotientSafe($diff, $previousValue, 3);
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyPercentFromQuotient($value);
    }

    public function getDependentMetrics()
    {
        return array($this->baseMetric, $this->compareMetric);
    }
}