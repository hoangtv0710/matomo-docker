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

use Piwik\DataTable;
use Piwik\Plugins\AbTesting\Segment;
use Piwik\Plugins\AbTesting\Tracker\RequestProcessor;

class AddSegmentValue extends BaseFilter
{
    private $experimentName = '';

    /**
     * Constructor.
     *
     * @param DataTable $table The table to eventually filter.
     * @param string $experimentName
     */
    public function __construct($table, $experimentName)
    {
        parent::__construct($table);

        $this->experimentName = $experimentName;
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        foreach ($table->getRowsWithoutSummaryRow() as $id => $row) {
            if ($this->isOriginalVariationRow($row)) {
                $row->setMetadata('segment', $this->buildSegment(RequestProcessor::VARIATION_NAME_ORIGINAL));
            } else {
                $variation = $row->getColumn('label');

                $row->setMetadata('segment', $this->buildSegment($variation));
            }
        }
    }

    private function buildSegment($variation)
    {
        return sprintf('%s==%s,%s==%s', Segment::NAME_EXPERIMENT_SEGMENT, urlencode($this->experimentName), Segment::NAME_VARIATION_SEGMENT, urlencode($variation));
    }
}