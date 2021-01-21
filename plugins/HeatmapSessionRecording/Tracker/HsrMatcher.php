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
namespace Piwik\Plugins\HeatmapSessionRecording\Tracker;

use Piwik\Common;
use Piwik\Plugins\HeatmapSessionRecording\Dao\LogHsrSite;
use Piwik\Plugins\HeatmapSessionRecording\Dao\SiteHsrDao;
use Piwik\Tracker\Cache;

class HsrMatcher
{
    // USER IS NOT FORCED TO BE OR NOT TO BE IN GROUP
    const SAMPLE_GROUP_TO_BE_DETECTED = null;

    // USER IS FORCED TO BE IN SAMPLE GROUP
    const SAMPLE_GROUP_IS_PART = true;

    // USER IS FORCED TO NOT BE IN SAMPLE GROUP
    const SAMPLE_GROUP_NOT_PART = false;

    const QUERY_NAMESPACE = 'hsr'; // namespace has to match tracker namespace

    public static function doesScreenshotUrlMatch($screenshotUrl, $url)
    {
        if ($screenshotUrl === false || $screenshotUrl === null || $screenshotUrl === '') {
            return true;
        }

        $screenshotUrl = (string) strtolower($screenshotUrl);
        $url = (string) strtolower($url);

        if ($screenshotUrl === $url) {
            return true;
        }

        if (strpos($screenshotUrl, '#') === false) {
            // we require the hash to be there and only match exactly when user specifically requested it as screenshot url

            if (strpos($url, '#') !== false) {
                // we ignore the hash in the url
                $url = substr($url, 0, strpos($url, '#'));
            }
        }

        if (strpos($screenshotUrl, '//') === 0) {
            // in this case we allow any protocol
            if ($screenshotUrl === str_replace(array('http://', 'https://'), '//', $url)) {
                return true;
            }
        }

        // in this case we allow any protocol
        $protocolsToIgnore = array('http://', 'https://');
        $url = str_replace($protocolsToIgnore, '', $url);
        $screenshotUrl = str_replace($protocolsToIgnore, '', $screenshotUrl);

        $url = rtrim($url, '/');
        $screenshotUrl = rtrim($screenshotUrl, '/');

        return $screenshotUrl === $url;
    }

    public static function checkIsNotEnded($hsr)
    {
        if ($hsr['status'] !== SiteHsrDao::STATUS_ACTIVE) {
            // eg when it is a session we might have cached ended sessions, we need to make sure to not add any new
            // sessions
            return false;
        }

        if (empty($hsr['sample_limit'])) {
            return false;
        }

        $idSite = $hsr['idsite'];
        $idSiteHsr = $hsr['idsitehsr'];

        $logHsrSite = new LogHsrSite();

        $lazyCache = \Piwik\Cache::getLazyCache();
        $samplesKey = 'hsrNumSamples' . (int) $idSite . '_' . (int) $idSiteHsr;
        $numSamples = $lazyCache->fetch($samplesKey);

        if ($hsr['record_type'] == SiteHsrDao::RECORD_TYPE_HEATMAP) {
            if (empty($numSamples)) {
                $numSamples = $logHsrSite->getNumPageViews($idSiteHsr);
                $lazyCache->save($samplesKey, $numSamples, $ttlInSec = 300);
            }

            if ($numSamples >= $hsr['sample_limit']) {

                $siteHsr = \Piwik\Container\StaticContainer::get('Piwik\Plugins\HeatmapSessionRecording\Model\SiteHsrModel');
                $siteHsr->endHeatmap($idSite, $idSiteHsr);
                // we make sure in the future the completed HSR won't receive any recordings anymore afterwards
                Cache::deleteCacheWebsiteAttributes($idSite);

                return false;
            }

        } elseif ($hsr['record_type'] == SiteHsrDao::RECORD_TYPE_SESSION) {
            if (empty($numSamples)) {
                $numSamples = $logHsrSite->getNumSessions($idSiteHsr);
                $lazyCache->save($samplesKey, $numSamples, $ttlInSec = 300);
            }

            if ($numSamples >= $hsr['sample_limit']) {
                $siteHsr = \Piwik\Container\StaticContainer::get('Piwik\Plugins\HeatmapSessionRecording\Model\SiteHsrModel');

                $siteHsr->endSessionRecording($idSite, $idSiteHsr);
                // we make sure in the future the completed HSR won't receive any recordings anymore afterwards
                Cache::deleteCacheWebsiteAttributes($idSite);

                return false;
            }
        }

        return true;
    }

    public static function makeCookieSampleKey($idSiteHsr)
    {
        return self::QUERY_NAMESPACE . (int) $idSiteHsr;
    }

    public static function isUserPartOfSampleGroup($recordType, $idSiteHsr)
    {
        if ($recordType == SiteHsrDao::RECORD_TYPE_SESSION) {
            $key = self::makeCookieSampleKey($idSiteHsr);
            if (isset($_GET[$key]) && $_GET[$key] == '1') {
                return self::SAMPLE_GROUP_IS_PART;
            } else if (isset($_GET[$key]) && $_GET[$key] == '0') {
                return self::SAMPLE_GROUP_NOT_PART;
            }
        }

        return self::SAMPLE_GROUP_TO_BE_DETECTED;
    }

    /**
     * @param $matchPageRules
     * @param $url
     * @return bool
     */
    public static function matchesAllPageRules($matchPageRules, $url)
    {
        if (empty($matchPageRules)) {
            // no restrictions, we track any page
            return true;
        }

        if (is_array($matchPageRules)) {
            // ALL RULES NEED TO MATCH!

            foreach ($matchPageRules as $pageRule) {
                $pageRule = new PageRuleMatcher($pageRule);
                if (!$pageRule->matches($url)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

}
