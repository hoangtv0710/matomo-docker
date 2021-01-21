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
namespace Piwik\Plugins\Cohorts\Columns;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugin\Segment;
use Piwik\Site;

// time is in the site's timezone
class VisitorFirstVisitTime extends VisitDimension
{
    protected $nameSingular = 'Cohorts_VisitorFirstVisitTime';
    protected $segmentName = 'visitorFirstVisitTime';
    protected $acceptValues = 'Any timestamp.';
    protected $type = self::TYPE_NUMBER;

    protected function configureSegments()
    {
        // HACK: sometimes the segment will be configured without an idSite parameter in the request. this usually happens with API.getSegmentsMetadata
        // where it supplies idSites. Unfortunately, this segment's SQL is specific to a site's timezone, so we can't completely configure it in that
        // case. it seems like API.getSegmentsMetadata isn't used in contexts where that is required, so hopefully this will be ok.
        $idSites = Common::getRequestVar('idSites', false);

        $idSite = Common::getRequestVar('idSite', false, 'int');
        if (empty($idSite)
            && empty($idSites)
        ) {
            return;
        }

        $siteTimezoneOffset = 0;
        if (!empty($idSite)) {
            $siteTimezone = Site::getTimezoneFor($idSite);
            $siteTimezoneOffset = Date::getUtcOffset($siteTimezone);
        }

        $secondsSinceFirstVisit = "(log_visit.visitor_days_since_first * 86400)";
        $firstActionTimeUtc = "UNIX_TIMESTAMP(log_visit.visit_first_action_time)";
        $firstVisitStartTime = "($firstActionTimeUtc - $secondsSinceFirstVisit)";
        $adjustedFirstVisitTime = "(UNIX_TIMESTAMP(CONVERT_TZ(FROM_UNIXTIME($firstVisitStartTime), @@session.time_zone, '+00:00')) + $siteTimezoneOffset)";

        $segment = new Segment();
        $segment->setSqlSegment($adjustedFirstVisitTime);
        $segment->setSqlFilterValue(function ($value) {
            if (is_numeric($value)) {
                return (int)$value;
            }

            try {
                return Date::factory($value)->getTimestamp();
            } catch (\Exception $ex) {
                return $value;
            }
        });
        $this->addSegment($segment);
    }
}
