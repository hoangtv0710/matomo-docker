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

namespace Piwik\Plugins\AbTesting\Tracker;

use Piwik\Date;

class Schedule
{
    /**
     * @var null|string
     */
    private $startDateTime;

    /**
     * @var null|string
     */
    private $endDateTime;

    public function __construct($startDateTime, $endDateTime)
    {
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
    }

    public function matchesTimestamp($currentTimestamp)
    {
        $validStart = $this->isValidStartDateTime($currentTimestamp);
        $validEnd = $this->isValidEndDateTime($currentTimestamp);

        return $validStart && $validEnd;
    }

    protected function isValidStartDateTime($currentTimestamp)
    {
        // there always has to be a start time in order for an experiment to be started
        if (!empty($this->startDateTime)) {
            $startDate = Date::factory($this->startDateTime);

            if ($startDate->getTimestampUTC() <= $currentTimestamp) {
                return true;
            }
        }

        return false;
    }

    protected function isValidEndDateTime($currentTimestamp)
    {
        // there is not always an end time as an experiment may run until it has been stopped manually
        if (!empty($this->endDateTime)) {
            $endDate = Date::factory($this->endDateTime);

            if ($endDate->getTimestampUTC() < $currentTimestamp) {
                return false;
            }
        }

        return true;
    }

}
