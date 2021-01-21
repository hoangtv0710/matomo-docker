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
namespace Piwik\Plugins\HeatmapSessionRecording\Input;

use Piwik\Piwik;
use Piwik\Plugins\HeatmapSessionRecording\Configuration;
use Piwik\Site;

class Validator
{
    private $configuration;

    public function __construct()
    {
        $this->configuration = new Configuration();
    }

    private function supportsMethod($method)
    {
        return method_exists('Piwik\Piwik', $method);
    }

    public function checkHasSomeWritePermission()
    {
        if ($this->supportsMethod('checkUserHasSomeWriteAccess')) {
            // since Matomo 3.6.0
            Piwik::checkUserHasSomeWriteAccess();
            return;
        }

        Piwik::checkUserHasSomeAdminAccess();
    }

    public function checkWritePermission($idSite)
    {
        $this->checkSiteExists($idSite);
        Piwik::checkUserIsNotAnonymous();

        if ($this->supportsMethod('checkUserHasWriteAccess')) {
            // since Matomo 3.6.0
            Piwik::checkUserHasWriteAccess($idSite);
            return;
        }

        Piwik::checkUserHasAdminAccess($idSite);
    }

    public function checkHeatmapReportViewPermission($idSite)
    {
        $this->checkSiteExists($idSite);
        Piwik::checkUserHasViewAccess($idSite);
    }

    public function checkSessionReportViewPermission($idSite)
    {
        $this->checkSiteExists($idSite);
        $this->checkUserIsNotAnonymousForView($idSite);
        Piwik::checkUserHasViewAccess($idSite);
    }

    private function checkUserIsNotAnonymousForView($idSite)
    {
        if ($this->configuration->isAnonymousSessionRecordingAccessEnabled($idSite)) {
            Piwik::checkUserHasViewAccess($idSite);
            return;
        }

        Piwik::checkUserIsNotAnonymous();
    }

    private function checkSiteExists($idSite)
    {
        new Site($idSite);
    }

    public function canViewSessionReport($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        if (!$this->configuration->isAnonymousSessionRecordingAccessEnabled($idSite)
            && Piwik::isUserIsAnonymous()
        ) {
            return false;
        }

        return Piwik::isUserHasViewAccess($idSite);
    }

    public function canViewHeatmapReport($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        return Piwik::isUserHasViewAccess($idSite);
    }

    public function canWrite($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        if ($this->supportsMethod('isUserHasWriteAccess')) {
            // since Matomo 3.6.0
            return Piwik::isUserHasWriteAccess($idSite);
        }

        return Piwik::isUserHasAdminAccess($idSite);
    }


}
