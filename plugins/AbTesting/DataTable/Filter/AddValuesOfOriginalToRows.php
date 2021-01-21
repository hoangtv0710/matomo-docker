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
namespace Piwik\Plugins\AbTesting\DataTable\Filter;

use Piwik\DataTable\Row;
use Piwik\DataTable;
use Piwik\Plugins\AbTesting\Archiver;
use Piwik\Plugins\AbTesting\Metrics;

class AddValuesOfOriginalToRows extends BaseFilter
{
    const COLUMN_NAME_PREFIX = 'original_';

    /**
     * @var string
     */
    private $metricName;

    /**
     * Constructor.
     *
     * @param DataTable $table The table to eventually filter.
     */
    public function __construct($table, $metricName)
    {
        parent::__construct($table);
        $this->metricName = $metricName;
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $originalVariationRow = null;

        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            if ($this->isOriginalVariationRow($row)) {
                $originalVariationRow = $row;
            }
        }
        
        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            if ($originalVariationRow && $row !== $originalVariationRow) {
                $this->copyMetric($row, $originalVariationRow, $this->metricName);
                $this->copyMetric($row, $originalVariationRow, $this->metricName . Archiver::APPENDIX_TTEST_STDDEV_SAMP);
                $this->copyMetric($row, $originalVariationRow, $this->metricName . Archiver::APPENDIX_TTEST_SUM);
                $this->copyMetric($row, $originalVariationRow, $this->metricName . Archiver::APPENDIX_TTEST_COUNT);
                $this->copyMetric($row, $originalVariationRow, Metrics::METRIC_VISITS);
                $this->copyMetric($row, $originalVariationRow, Metrics::METRIC_VISITS_ENTERED);
                $this->copyMetric($row, $originalVariationRow, Metrics::METRIC_UNIQUE_VISITORS);
                $this->copyMetric($row, $originalVariationRow, Metrics::METRIC_UNIQUE_VISITORS_ENTERED);
            }
        }
    }

    private function copyMetric(Row $row, Row $originalRow, $metricName)
    {
        $row->setColumn(self::COLUMN_NAME_PREFIX . $metricName, $originalRow->getColumn($metricName));
    }
}