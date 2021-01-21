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

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Archive\DataTableFactory;
use Piwik\Metrics\Formatter;
use Piwik\DataTable\Row;
use Piwik\Plugin\ProcessedMetric;
use Piwik\DataTable;
use Piwik\Plugins\MultiChannelConversionAttribution\Models;

class Revenue extends ProcessedMetric
{
    /**
     * @var int
     */
    private $idSite;

    /**
     * @var string
     */
    private $metric;

    /**
     * @var string
     */
    private $attributionModelName;

    public function __construct($metric, Models\Base $model)
    {
        $this->metric = $metric;
        $this->attributionModelName = $model->getName();
    }

    public function getName()
    {
        return $this->metric;
    }

    public function getTranslatedName()
    {
        // in the html table UI we don't show full metric name
        return Piwik::translate('General_ColumnRevenue');
    }

    public function getDocumentation()
    {
        return Piwik::translate('MultiChannelConversionAttribution_ColumnRevenueDocumentation', array('"' . $this->attributionModelName . '"'));
    }

    public function compute(Row $row)
    {
        return $row->getColumn($this->metric);
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyMoney($value, $this->idSite);
    }

    public function beforeFormat($report, DataTable $table)
    {
        $idSite = DataTableFactory::getSiteIdFromMetadata($table);
        if (empty($idSite)) {
            // possible when using search in visualization
            $idSite = Common::getRequestVar('idSite', 0, 'int');
        }
        $this->setSiteId($idSite);
        return !empty($idSite);
    }

    public function setSiteId($idSite)
    {
        $this->idSite = $idSite;
    }

    public function getDependentMetrics()
    {
        return array($this->metric);
    }
}