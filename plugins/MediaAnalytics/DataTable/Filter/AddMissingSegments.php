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
namespace Piwik\Plugins\MediaAnalytics\DataTable\Filter;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Plugins\MediaAnalytics\Archiver;
use Piwik\Plugins\MediaAnalytics\Dao\LogMediaPlays;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\Plugins\MediaAnalytics\Metrics;

class AddMissingSegments extends DataTable\BaseFilter
{
    private $sumPlays;
    private $maxLength;

    public function __construct(DataTable $table, $sumPlays, $maxLength)
    {
        parent::__construct($table);
        $this->sumPlays = $sumPlays;
        $this->maxLength = $maxLength;
    }

    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $maxMediaLength = $this->maxLength;

        if (!$maxMediaLength) {
            return;
        }

        $segments = LogMediaPlays::getSegments();
        $smallSegments = LogMediaPlays::getSmallGapsSegments();
        $regularSizeGaps = LogMediaPlays::getSmallGapsSegmentsMadeRegularSize();
        $maxSmallSegment = max($smallSegments);

        $isLongMediaAndEnforceSameGaps = $maxMediaLength >= LogMediaPlays::USE_SMALL_SEGMENT_UP_TO_SECONDS;
        if ($isLongMediaAndEnforceSameGaps) {
            // for media > 5 minutes we only want to show the 30 second segments, no 15 second segments in between... these we want to remove
            // and instead of the 30 column, we need to use the 30_grouped column since this will include plays that had either the 15 second or
            // the 30 second segment.

            $table->filter('ColumnCallbackDeleteRow', array('label', function ($segmentLabel) use ($maxSmallSegment) {
                // remove all rows that are "small gap", eg 5,15,20, ... they won't be displayed
                return $maxSmallSegment >= $segmentLabel && !Common::stringEndsWith($segmentLabel,Archiver::GROUPED_MEDIA_SEGMENT_APPENDIX);
            }));
            $table->filter('ColumnCallbackReplace', array('label', function ($segmentLabel) use ($maxSmallSegment) {
                // rename grouped segments to the regular segment name, eg "30_grouped" => 30
                if (Common::stringEndsWith($segmentLabel,Archiver::GROUPED_MEDIA_SEGMENT_APPENDIX)) {
                    $segmentLabel = (int) str_replace(Archiver::GROUPED_MEDIA_SEGMENT_APPENDIX, '', $segmentLabel);
                }
                return $segmentLabel;
            }));

            $segments = array_filter($segments, function ($segment) use ($regularSizeGaps, $smallSegments, $maxSmallSegment) {
                if ($segment <= $maxSmallSegment && in_array($segment, $smallSegments) && !in_array($segment, $regularSizeGaps)) {
                    // remove eg 15, 45, 75, ... but keep 30, 60,90
                    return false;
                }
                return true;
            });
        } else {
            $table->filter('ColumnCallbackDeleteRow', array('label', function ($segmentLabel) use ($maxSmallSegment) {
                // delete all grouped segments as we show the regular columns with the small gaps
                return Common::stringEndsWith($segmentLabel,Archiver::GROUPED_MEDIA_SEGMENT_APPENDIX);
            }));
        }

        $existingSegments = $table->getColumn('label');

        foreach ($segments as $i) {
            if (!in_array($i, $existingSegments) && $i <= $maxMediaLength) {
                $table->addRowFromSimpleArray(array('label' => $i, Metrics::METRIC_NB_PLAYS => 0));
                $table->setLabelsHaveChanged();
            }
        }

        $table->filter('Sort', array('label', 'asc'));
        $table->disableFilter('Sort');
    }
}