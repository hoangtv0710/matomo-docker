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

use Piwik\Container\StaticContainer;
use Piwik\DataTable\Row;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;

use Piwik\Plugins\AbTesting\Columns\Metrics\DetectedEffect;
use Piwik\Plugins\AbTesting\Columns\Metrics\RemainingVisitors;
use Piwik\Plugins\AbTesting\Columns\Metrics\SignificanceRate;

class Conclusion extends BaseFilter
{
    /**
     * @var ViewDataTable
     */
    private $view;

    /**
     * @var array
     */
    private $experiment = array();

    /**
     * Constructor.
     *
     * @param DataTable $table The table to eventually filter.
     * @param ViewDataTable $view
     * @param array $experiment
     */
    public function __construct($table, $view, $experiment)
    {
        parent::__construct($table);

        $this->view = $view;
        $this->experiment = $experiment;
    }

    private function isSignificant(Row $row)
    {
        $significanceRate = $row->getColumn(SignificanceRate::METRIC_NAME);

        $isSignificant = $significanceRate
                      && is_numeric($significanceRate)
                      && $significanceRate >= $this->experiment['confidence_threshold'];

        return $isSignificant;
    }

    private function hasEnoughVisitors(Row $row)
    {
        $remaining = $row->getColumn(RemainingVisitors::METRIC_NAME);
        return $remaining === RemainingVisitors::ENOUGH_VISITORS || $remaining <= 0;
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $totalWinner = false;
        $totalSignificant = false;
        $totalLoser = false;

        $mde = $this->experiment['mde_relative'];

        $fakeWinner = $this->shouldFakeWinner();

        $haveAllRowsEnoughVisitors = true;
        $areAllRowsSignificant = true;
        $hasDataForAnyOtherVariationRecorded = false;

        foreach ($table->getRowsWithoutSummaryRow() as $row) {
            $row->setMetadata('is_winner', 0);
            $row->setMetadata('is_loser', 0);
            $row->setMetadata('is_significant', 0);

            if ($fakeWinner && !$this->isOriginalVariationRow($row)) {
                // FOR UI TESTS ONLY
                $fakeWinner = false;
                $totalWinner = true;
                $row->setMetadata('is_winner', 1);
            }

            if ($this->isOriginalVariationRow($row)) {
                continue;
            }

            $hasDataForAnyOtherVariationRecorded = true;

            $hasRowEnoughVisitors = $this->hasEnoughVisitors($row);
            $isRowSignificant = $this->isSignificant($row);

            if (!$hasRowEnoughVisitors) {
                $haveAllRowsEnoughVisitors = false;
            }
            if (!$isRowSignificant) {
                $areAllRowsSignificant = false;
            }

            if (!$hasRowEnoughVisitors || !$isRowSignificant) {
                continue;
            }

            $detectedEffect = $row->getColumn(DetectedEffect::METRIC_NAME);

            $hasImproved = $detectedEffect > 0;
            $hasLost = $detectedEffect < 0;

            // enough visitors, check for significance
            if ($hasImproved && $detectedEffect >= $mde) {
                $totalWinner = true;
                $row->setMetadata('is_winner', 1);
            } elseif ($hasImproved) {
                $totalSignificant = true;
                $row->setMetadata('is_significant', 1);
            } elseif ($hasLost) {
                $totalLoser = true;
                $row->setMetadata('is_loser', 1);
            }
        }

        if (!$hasDataForAnyOtherVariationRecorded) {
            $this->view->config->show_footer_message = Piwik::translate('AbTesting_ConclusionNoVariationRecordedYet');
        } elseif ($totalWinner) {
            $this->view->config->show_footer_message = Piwik::translate('AbTesting_ConclusionWinningVariation', $mde . '%');
        } elseif ($totalSignificant) {
            $this->view->config->show_footer_message = Piwik::translate('AbTesting_ConclusionSignificantVariation', $mde . '%');
        } elseif ($totalLoser) {
            $this->view->config->show_footer_message = Piwik::translate('AbTesting_ConclusionLosingVariation');
        } elseif (!$haveAllRowsEnoughVisitors) {
            $this->view->config->show_footer_message = Piwik::translate('AbTesting_ConclusionNoVariationHasEnoughVisitors');
        } elseif (!$areAllRowsSignificant) {
            $this->view->config->show_footer_message = Piwik::translate('AbTesting_ConclusionNoVariationIsSignificant');
        } else {
            $this->view->config->show_footer_message = Piwik::translate('AbTesting_ConclusionNoConclusion');
        }
    }

    // FOR UI TESTS ONLY
    private function shouldFakeWinner()
    {
        try {
            $fakeWinnner = StaticContainer::get('test.vars.fakeExperimentConclusionWinner');
        } catch (\Exception $e) {
            return false;
        }

        return !empty($fakeWinnner);
    }
}