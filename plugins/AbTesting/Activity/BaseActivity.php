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

use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugins\ActivityLog\Activity\Activity;
use Piwik\Site;

abstract class BaseActivity extends Activity
{
    protected function getExperimentNameFromActivityData($activityData)
    {
        if (!empty($activityData['experiment']['name'])) {
            return $activityData['experiment']['name'];
        }

        if (!empty($activityData['experiment']['id'])) {
            return $activityData['experiment']['id'];
        }

        return '';
    }

    protected function getSiteNameFromActivityData($activityData)
    {
        if (!empty($activityData['site']['site_name'])) {
            return $activityData['site']['site_name'];
        }

        if (!empty($activityData['site']['site_id'])) {
            return $activityData['site']['site_id'];
        }

        return '';
    }

    protected function formatActivityData($idExperiment, $idSite)
    {
        if (!is_numeric($idSite) || !is_numeric($idExperiment)) {
            return;
        }

        return array(
            'site' => $this->getSiteData($idSite),
            'version' => 'v1',
            'experiment' => $this->getExperimentData($idExperiment, $idSite),
        );
    }

    private function getSiteData($idSite)
    {
        return array(
            'site_id'   => $idSite,
            'site_name' => Site::getNameFor($idSite)
        );
    }

    private function getExperimentData($idExperiment, $idSite)
    {
        $experiment = $this->getDao()->getExperiment($idExperiment, $idSite);

        $experimentName = '';
        if (!empty($experiment['name'])) {
            // experiment name might not be set when we are handling deleteExperiment activity
            $experimentName = $experiment['name'];
        }

        return array(
            'id' => $idExperiment,
            'name' => $experimentName
        );
    }

    public function getPerformingUser($eventData = null)
    {
        $login = Piwik::getCurrentUserLogin();

        if ($login === self::USER_ANONYMOUS || empty($login)) {
            // anonymous cannot change an experiment, in this case the system changed it, eg during tracking it started
            // an experiment
            return self::USER_SYSTEM;
        }

        return $login;
    }

    private function getDao()
    {
        // we do not get it via DI as it would slow down creation of all activities on all requests. Instead only
        // create instance when needed
        return StaticContainer::get('Piwik\Plugins\AbTesting\Dao\Experiment');
    }
}
