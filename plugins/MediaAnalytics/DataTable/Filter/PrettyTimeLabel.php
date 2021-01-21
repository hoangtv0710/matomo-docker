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

use Piwik\DataTable;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\MediaAnalytics\Archiver;
use Piwik\Plugins\MediaAnalytics\Dao\LogMediaPlays;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;

class PrettyTimeLabel extends DataTable\BaseFilter
{
    /**
     * @param DataTable $table
     */
    public function filter($table)
    {
        $formatter = new Formatter();
        $table->filter('ColumnCallbackReplace', array('label', function ($value) use($formatter) {
            if ($value === Archiver::LABEL_NOT_DEFINED) {
                return $value;
            }
            if ($value > 3600 && $value % 60 === LogMediaPlays::DEFAULT_SEGMENT_LENGTH) {
                // needed for media segment secondary dimension. otherwise it would print eg 1 hour 12 minutes but we
                // may want 1 hour 12.5 minutes
                $hours = floor($value / 3600);
                $hoursInSec = $hours * 3600;
                $minutes = floor(($value - $hoursInSec) / 60);

                $return = sprintf(Piwik::translate('General_HoursMinutes'), $hours, $minutes . '.5');
                return $return;
            }

            return $formatter->getPrettyTimeFromSeconds($value, $sentence = true);
        }));
    }
}