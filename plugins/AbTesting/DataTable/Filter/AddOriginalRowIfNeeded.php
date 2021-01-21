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


class AddOriginalRowIfNeeded extends BaseFilter
{

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            if ($this->isOriginalVariationRow($row)) {
                return;
            }
        }

        $row = new Row(array(Row::COLUMNS => array('label' => Archiver::LABEL_NOT_DEFINED)));
        $table->addRow($row);
    }
}