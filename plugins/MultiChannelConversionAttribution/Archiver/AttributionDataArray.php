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

namespace Piwik\Plugins\MultiChannelConversionAttribution\Archiver;

use Piwik\Common;
use Piwik\DataArray;
use Piwik\Plugins\MultiChannelConversionAttribution\Metrics;
use Piwik\Plugins\MultiChannelConversionAttribution\Models\Base;
use Piwik\Plugins\MultiChannelConversionAttribution\Archiver;

class AttributionDataArray extends DataArray
{
    private $columns = array();
    private $defaultRow = array();

    public function __construct(array $data = array(), array $dataArrayByLabel = array())
    {
        parent::__construct($data, $dataArrayByLabel);

        $this->columns = array();
        foreach (Base::getAll() as $attribution) {
            if ($attribution->getAttributionQuery('1', '1')) {
                $this->columns[] = Metrics::completeAttributionMetric(Metrics::SUM_CONVERSIONS, $attribution);
                $this->columns[] = Metrics::completeAttributionMetric(Metrics::SUM_REVENUE, $attribution);
            }
        }
        foreach ($this->columns as $column) {
            $this->defaultRow[$column] = 0;
        }
    }

    public function addDefaultRowColumns($columns)
    {
        // this is to make sure we intialize last non direct metrics with zero as well
        // bit of a hack but works for now. there would be better solutions like iterating over all rows on
        // setColumnsContext call but would be slower!
        foreach ($columns as $column) {
            $this->defaultRow[$column] = 0;
        }
    }

    public function setColumnsContext($columns)
    {
        $this->columns = $columns;
    }

    /**
     * @param $row
     * @param Base[] $attributions
     */
    public function computeMetrics($row)
    {
        $label = $row['label'];
        $sublabel = $row['sublabel'];

        if (empty($label) && $label !== '0') {
            $label = Archiver::LABEL_NOT_DEFINED;
        }
        if (empty($sublabel) && $sublabel !== '0') {
            $sublabel = Archiver::LABEL_NOT_DEFINED;
        }

        if (!isset($this->data[$label])) {
            $this->data[$label] = $this->defaultRow;
            $this->dataTwoLevels[$label] = array();
        }

        if (!isset($this->dataTwoLevels[$label][$sublabel])) {
            $this->dataTwoLevels[$label][$sublabel] = $this->defaultRow;
        }

        foreach ($this->columns as $column) {
            $rounded = round($row[$column], 4);
            $this->data[$label][$column] += $rounded;
            $this->dataTwoLevels[$label][$sublabel][$column] += $rounded;
        }
    }

    public function asDataTable()
    {
        // this will never have a subtable
        unset($this->dataTwoLevels[Common::REFERRER_TYPE_DIRECT_ENTRY]);

        return parent::asDataTable();
    }

}
