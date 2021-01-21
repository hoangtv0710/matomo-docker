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
namespace Piwik\Plugins\MultiChannelConversionAttribution\DataTable\Filter;

use Piwik\DataTable;
use Piwik\DataTable\BaseFilter;

require_once PIWIK_INCLUDE_PATH . '/plugins/Referrers/functions.php';

class RenameChannelType extends BaseFilter
{
    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            $referrerType = $row->getColumn('label');

            $label = \Piwik\Plugins\Referrers\getReferrerTypeLabel($referrerType);
            $row->setColumn('label', $label);

            if ($subtable = $row->getSubtable()) {
                $subtable->filter('Piwik\Plugins\MultiChannelConversionAttribution\DataTable\Filter\RenameChannelName');
            }
        }
    }
}