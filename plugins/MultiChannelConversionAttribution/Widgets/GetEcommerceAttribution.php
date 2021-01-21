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
namespace Piwik\Plugins\MultiChannelConversionAttribution\Widgets;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Site;
use Piwik\Tracker\GoalManager;
use Piwik\Widget\WidgetConfig;

class GetEcommerceAttribution extends GetMultiAttribution
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('Goals_Ecommerce');
        $config->setSubcategoryId('MultiChannelConversionAttribution_MultiAttribution');
        $config->setName('MultiChannelConversionAttribution_MultiChannelConversionAttribution');
        $config->setOrder(98);

        $idSite = Common::getRequestVar('idSite', 0, 'int');
        if (!empty($idSite) && Piwik::isUserHasViewAccess($idSite) && Site::isEcommerceEnabledFor($idSite)) {
            $config->enable();
        } else {
            $config->disable();
        }
    }

    protected function getGoals($idSite)
    {
        if (Site::isEcommerceEnabledFor($idSite)) {
            return array($this->model->getGoal($idSite, GoalManager::IDGOAL_ORDER));
        }

        return array();
    }

}