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

namespace Piwik\Plugins\Cohorts;


use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Period;
use Piwik\Period\Factory;

class CohortRanges
{
    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct()
    {
        $this->configuration = new Configuration();
    }

    /**
     * @ignore
     */
    public function getMultipleDateForCohortLength($date, $period, $filter_limit)
    {
        if ($period == 'range') {
            $periodObj = Factory::build($period, $date);
            $date = $periodObj->getRangeString();
        } else if (!Period::isMultiplePeriod($date, $period)) {
            $date = Date::factory($date)->toString();
            $date = self::getCohortsStartDateToDisplay($date, $period, $filter_limit) . ',' . $date;
        }
        return $date;
    }

    private function getCohortsStartDateToDisplay($date, $period, $filter_limit)
    {
        $configuration = $this->configuration;
        $amount = $filter_limit > 0 ? $filter_limit : $configuration->getNumberOfCohortsToDisplay();
        $endDate = Date::factory($date)->subPeriod($amount, $period);
        return $endDate;
    }
}