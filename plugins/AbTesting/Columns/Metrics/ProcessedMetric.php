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
use Piwik\Plugin;
use Piwik\Plugins\AbTesting\Archiver;
use Piwik\Plugins\AbTesting\Tracker\RequestProcessor;

abstract class ProcessedMetric extends Plugin\ProcessedMetric
{
    public function isOriginalVariationRow(Row $row)
    {
        $label = $row->getColumn('label');
        return (!$label || $label === Archiver::LABEL_NOT_DEFINED || $label == RequestProcessor::VARIATION_ORIGINAL_ID);
    }
}