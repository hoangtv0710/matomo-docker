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
namespace Piwik\Plugins\HeatmapSessionRecording\Activity;

class HeatmapScreenshotDeleted extends BaseActivity
{
    protected $eventName = 'API.HeatmapSessionRecording.deleteHeatmapScreenshot.end';

    public function extractParams($eventData)
    {
        list($result, $finalAPIParameters) = $eventData;

        if (empty($finalAPIParameters['parameters'])) {
            return false;
        }

        $idSiteHsr = $finalAPIParameters['parameters']['idSiteHsr'];
        $idSite = $finalAPIParameters['parameters']['idSite'];

        return $this->formatActivityData($idSiteHsr, $idSite);
    }

    public function getTranslatedDescription($activityData, $performingUser)
    {
        $siteName = $this->getSiteNameFromActivityData($activityData);
        $hsrName = $this->getHsrNameFromActivityData($activityData);

        $desc = sprintf('deleted the heatmap screenshot "%1$s" for site "%2$s"', $hsrName, $siteName);

        return $desc;
    }
}
