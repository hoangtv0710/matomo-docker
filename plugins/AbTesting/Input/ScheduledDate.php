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

namespace Piwik\Plugins\AbTesting\Input;

use \Exception;
use Piwik\Date;
use Piwik\Piwik;

class ScheduledDate
{
    /**
     * @var string|false
     */
    private $startDate;

    /**
     * @var string|false
     */
    private $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function check()
    {
        if (empty($this->startDate) && empty($this->endDate)) {
            return;
        }

        if (empty($this->endDate)) {
            // validate start date
            Date::factory($this->startDate);
            return;
        }

        $startDateTitle = Piwik::translate('AbTesting_StartDate');
        $endDateTitle = Piwik::translate('AbTesting_FinishDate');

        // validate end date
        $end = Date::factory($this->endDate);

        if ($end->getTimestampUTC() < Date::now()->getTimestampUTC()) {
            throw new Exception(Piwik::translate('AbTesting_ErrorXNotInFuture', $endDateTitle));
        };

        if (empty($this->startDate)) {
            return;
        }

        $start = Date::factory($this->startDate);
        $end = Date::factory($this->endDate);

        if ($end->getTimestampUTC() === $start->getTimestampUTC()) {
            throw new Exception(Piwik::translate('AbTesting_ErrorXLaterThanYButEqual', array($endDateTitle, $startDateTitle)));
        }

        if ($end->isEarlier($start)) {
            throw new Exception(Piwik::translate('AbTesting_ErrorXLaterThanY', array($startDateTitle, $endDateTitle)));
        }

    }

}