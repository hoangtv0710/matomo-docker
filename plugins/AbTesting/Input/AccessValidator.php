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

use Piwik\Piwik;
use Piwik\Site;

class AccessValidator
{
    private function supportsMethod($method)
    {
        return method_exists('Piwik\Piwik', $method);
    }

    public function checkWritePermission($idSite)
    {
        $this->checkSiteExists($idSite);

        if ($this->supportsMethod('checkUserHasWriteAccess')) {
            // since 3.6.0
            Piwik::checkUserHasWriteAccess($idSite);
            return;
        }

        Piwik::checkUserHasAdminAccess($idSite);
    }

    public function checkReportViewPermission($idSite)
    {
        $this->checkSiteExists($idSite);
        Piwik::checkUserHasViewAccess($idSite);
    }

    public function checkSiteExists($idSite)
    {
        new Site($idSite);
    }

    public function canViewReport($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        return Piwik::isUserHasViewAccess($idSite);
    }

    public function checkHasSomeWritePermission()
    {
        if ($this->supportsMethod('checkUserHasSomeWriteAccess')) {
            // since 3.6.0
            Piwik::checkUserHasSomeWriteAccess();
            return;
        }

        Piwik::checkUserHasSomeAdminAccess();
    }

    public function canWrite($idSite)
    {
        if (empty($idSite)) {
            return false;
        }

        if ($this->supportsMethod( 'isUserHasWriteAccess')) {
            // since 3.6.0
            return Piwik::isUserHasWriteAccess($idSite);
        }

        return Piwik::isUserHasAdminAccess($idSite);
    }


}

