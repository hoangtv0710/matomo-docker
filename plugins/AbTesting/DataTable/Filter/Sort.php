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


class Sort extends BaseFilter
{

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $self = $this;
        $table->sort(function (Row $rowA, Row $rowB) use ($self) {

            if ($self->isOriginalVariationRow($rowA)) {
                return -1;
            }

            if ($self->isOriginalVariationRow($rowB)) {
                return 1;
            }

            $labelA = $rowA->getColumn('label');
            $labelB = $rowB->getColumn('label');
            
            return strcmp($labelA, $labelB);

        }, $columnSortedBy = 'label');

        $table->disableFilter('Sort');
    }
}