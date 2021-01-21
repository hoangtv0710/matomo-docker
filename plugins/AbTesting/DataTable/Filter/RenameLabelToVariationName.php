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
use Piwik\Piwik;

class RenameLabelToVariationName extends BaseFilter
{
    /**
     * @var array
     */
    private $allVariations = array();

    /**
     * Constructor.
     *
     * @param DataTable $table The table to eventually filter.
     * @param array $variations
     */
    public function __construct($table, $variations)
    {
        parent::__construct($table);

        if (!empty($variations)) {
            foreach ($variations as $variation) {
                $this->allVariations[$variation['idvariation']] = $variation;
            }
        }
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $idsToDelete = array();

        foreach ($table->getRowsWithoutSummaryRow() as $id => $row) {
            if ($this->isOriginalVariationRow($row)) {
                $row->setColumn('label', Piwik::translate('AbTesting_NameOriginalVariation'));
            } else {
                $variationId = $row->getColumn('label');

                if (!empty($this->allVariations[$variationId]['name']) && empty($this->allVariations[$variationId]['deleted'])) {
                    $label = $this->allVariations[$variationId]['name'];
                    $row->setColumn('label', $label);
                } else {
                    $idsToDelete[] = $id;
                }
            }
        }

        foreach ($idsToDelete as $id) {
            $table->deleteRow($id);
        }
    }
}