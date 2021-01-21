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
 *
 */
namespace Piwik\Plugins\Funnels\Widgets;

use Piwik\Access;
use Piwik\API\Request;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Site;
use Piwik\Tracker\GoalManager;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class SalesFunnel extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('Goals_Ecommerce');
        $config->setSubcategoryId('General_Overview');
        $config->setName('Funnels_SalesFunnel');
        $config->setIsWide();
        $config->setIsNotWidgetizable();
        $config->setOrder(999);

        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!Site::isEcommerceEnabledFor($idSite) || !self::getAccessValidator()->canWrite($idSite)) {
            $config->disable();
        }
    }

    private static function getAccessValidator()
    {
        return StaticContainer::get('Piwik\Plugins\Funnels\Input\Validator');
    }

    public function render()
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        self::getAccessValidator()->checkWritePermission($idSite);

        $funnel = $this->getFunnel($idSite, GoalManager::IDGOAL_ORDER);
        $idFunnel = null;
        if (!empty($funnel['idfunnel'])) {
            $idFunnel = $funnel['idfunnel'];
        }

        return $this->renderTemplate('salesFunnel', array(
            'idFunnel' => $idFunnel
        ));
    }

    private function getFunnel($idSite, $idGoal)
    {
        return Access::doAsSuperUser(function () use ($idSite, $idGoal) {
            return Request::processRequest('Funnels.getGoalFunnel', ['idSite' => $idSite, 'idGoal' => $idGoal]);
        });
    }
}
