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

namespace Piwik\Plugins\AbTesting;

use Piwik\Date;
use Piwik\Plugins\AbTesting\Model\Experiments;
use Piwik\Plugins\AbTesting\Tracker\Schedule;

class Tasks extends \Piwik\Plugin\Tasks
{
    /**
     * @var Experiments
     */
    private $experiments;

    /**
     * Constructor.
     */
    public function __construct(Experiments $experiments)
    {
        $this->experiments = $experiments;
    }

    public function schedule()
    {
        $this->hourly('changeExperimentStatusIfNeeded');
    }

    protected function getCurrentTimestamp()
    {
        return Date::now()->getTimestampUTC();
    }

    // we run this task every hour. This means it may take up to almost an hour to change the status eg from created
    // to running. this means we need to always consider a created experiment as possibly running and cannot rely on
    // the status only. Instead we need to check the scheduled date eg during tracking etc. This way an experiment
    // will actually start at the exact configured time.
    public function changeExperimentStatusIfNeeded()
    {
        $experiments = $this->experiments->getAllActiveExperiments();

        $currentTimestamp = $this->getCurrentTimestamp();

        foreach ($experiments as $experiment) {
            $isCreated = $experiment['status'] === Experiments::STATUS_CREATED;
            $isRunning = $experiment['status'] === Experiments::STATUS_RUNNING;

            if ($isCreated && !empty($experiment['start_date'])) {
                // we handle this kind of already in Model\Experiments::enrichExperiment. However we do this here as well
                // so in case there are eg queries that only fetch type running on DB level, it will return the correct result
                $schedule = new Schedule($experiment['start_date'], $experiment['end_date']);

                if ($schedule->matchesTimestamp($currentTimestamp)) {
                    $this->experiments->setStatus($experiment['idexperiment'], $experiment['idsite'], Experiments::STATUS_RUNNING);
                }
            }

            if ($isRunning && empty($experiment['start_date'])) {
                $this->experiments->setStartDateToCurrentTime($experiment['idexperiment'], $experiment['idsite']);
            }

            if (($isRunning || $isCreated) && !empty($experiment['end_date'])) {
                $endDate = Date::factory($experiment['end_date']);

                if ($currentTimestamp > $endDate->getTimestampUTC()) {
                    if (empty($experiment['start_date'])) {
                        $this->experiments->setStartDateToCurrentTime($experiment['idexperiment'], $experiment['idsite']);
                    }

                    $this->experiments->setStatus($experiment['idexperiment'], $experiment['idsite'], Experiments::STATUS_FINISHED);
                }
            }

        }
    }

}
