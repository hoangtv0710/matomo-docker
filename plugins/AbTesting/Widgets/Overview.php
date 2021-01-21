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

namespace Piwik\Plugins\AbTesting\Widgets;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class Overview extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('AbTesting_Experiments');
        $config->setIsNotWidgetizable();
        $config->setName('');
        $config->setOrder(11);

        $idSite = Common::getRequestVar('idSite', 0, 'int');
        if (!empty($idSite)) {
            $experiments = self::getExperimentsWithReports($idSite);
            if (count($experiments) !== 0) {
                // we only make it visible in the UI when there are no experiments. We cannot disable/enable it
                // as we otherwise would show an error message "not allowed to view widget" when suddenly
                // experiments are configured
                $config->setSubcategoryId('General_Overview');
            }
        }
    }

    private static function getExperimentsWithReports($idSite)
    {
        return Request::processRequest('AbTesting.getExperimentsWithReports', ['idSite' => $idSite, 'filter_limit' => -1], $default = []);
    }

    public function render()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasViewAccess($idSite);

        $experiments = self::getExperimentsWithReports($idSite);

        return $this->renderTemplate('overview.twig', array(
            'experiments' => $experiments,
            'idSite' => $idSite
        ));
    }
}