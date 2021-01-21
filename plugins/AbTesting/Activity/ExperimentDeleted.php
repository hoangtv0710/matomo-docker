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
namespace Piwik\Plugins\AbTesting\Activity;

class ExperimentDeleted extends BaseActivity
{
    protected $eventName = 'API.AbTesting.deleteExperiment.end';

    public function extractParams($eventData)
    {
        list($result, $finalAPIParameters) = $eventData;

        if (empty($finalAPIParameters['parameters'])) {
            return false;
        }

        $idExperiment = $finalAPIParameters['parameters']['idExperiment'];
        $idSite = $finalAPIParameters['parameters']['idSite'];

        return $this->formatActivityData($idExperiment, $idSite);
    }

    public function getTranslatedDescription($activityData, $performingUser)
    {
        $siteName = $this->getSiteNameFromActivityData($activityData);

        $experimentName = $this->getExperimentNameFromActivityData($activityData);

        $desc = sprintf('deleted an experiment "%1$s" for site "%2$s"', $experimentName, $siteName);

        return $desc;
    }
}
