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
namespace Piwik\Plugins\MultiChannelConversionAttribution\Model;

use Exception;
use Piwik\Piwik;
use Piwik\Site;
use Piwik\Exception\UnexpectedWebsiteFoundException;
use Piwik\API\Request;
use Piwik\Plugins\MultiChannelConversionAttribution\Dao\GoalAttributionDao;
use Piwik\Tracker\GoalManager;

class GoalAttributionModel
{
    /**
     * @var GoalAttributionDao
     */
    private $dao;

    /**
     * @var array
     */
    private $goalsCache = array();

    public function __construct(GoalAttributionDao $goalAttribution)
    {
        $this->dao = $goalAttribution;
    }

    public function checkAttributionEnabled($idSite, $idGoal)
    {
        $this->checkGoalExists($idSite, $idGoal);

        if ($idGoal == GoalManager::IDGOAL_ORDER && $this->hasEcommerce($idSite)) {
            // valid as ecommerce is automatically added currently (checkGoalExists won't fail for idGoal Order)
            return;
        }

        $isEnabled = $this->dao->isAttributionEnabled($idSite, $idGoal);

        if (empty($isEnabled)) {
            throw new Exception(Piwik::translate('MultiChannelConversionAttribution_ErrorMultiAttributionNotEnabled'));
        }
    }

    public function checkGoalExists($idSite, $idGoal)
    {
        $goal = $this->getGoal($idSite, $idGoal);

        if (empty($goal)) {
            throw new Exception(Piwik::translate('MultiChannelConversionAttribution_ErrorGoalDoesNotExist'));
        }
    }

    public function setAttribution($idSite, $idGoal, $isEnabled)
    {
        $this->clearGoalsCache();
        $this->checkGoalExists($idSite, $idGoal);

        if ($isEnabled) {
            $this->dao->addGoalAttribution($idSite, $idGoal);
        } else {
            $this->dao->removeGoalAttribution($idSite, $idGoal);
        }
    }

    public function getAttribution($idSite, $idGoal)
    {
        $this->checkGoalExists($idSite, $idGoal);

        $isEnabled = $this->dao->isAttributionEnabled($idSite, $idGoal);

        return array(
            'isEnabled' => $isEnabled ? 1 : 0
        );
    }

    public function getSiteAttributionGoalIds($idSite)
    {
        $goalIds = $this->dao->getSiteAttributionGoalIds($idSite);

        $validIds = array();

        if ($this->hasEcommerce($idSite)) {
            // we add orders automatically and show them first as likely most important
            $validIds[] = GoalManager::IDGOAL_ORDER;
        }

        foreach ($goalIds as $goalId) {
            $goal = $this->getGoal($idSite, $goalId);
            if (!empty($goal)) {
                $validIds[] = $goalId;
            }
        }

        return $validIds;
    }

    public function getSiteAttributionGoals($idSite)
    {
        $goalIds = $this->getSiteAttributionGoalIds($idSite);

        $goals = array();
        foreach ($goalIds as $goalId) {
            $goal = $this->getGoal($idSite, $goalId);
            if (!empty($goal)) {
                $goals[] = $goal;
            }
        }

        return $goals;
    }

    private function clearGoalsCache()
    {
        $this->goalsCache = array();
    }

    private function getAllGoals($idSite)
    {
        if (!isset($this->goalsCache[$idSite])) {
            $this->goalsCache[$idSite] = Request::processRequest('Goals.getGoals', array(
                'idSite' => $idSite,
                'filter_limit' => '-1', // when requesting a report it might eg set filter_limit=5, we need to overwrite this
                'filter_offset' => 0,
                'filter_truncate' => '-1',
                'filter_pattern' => '',
                'hideColumns' => '',
                'showColumns' => '',
                'filter_pattern_recursive' => ''
            ));
        }

        return $this->goalsCache[$idSite];
    }

    private function hasEcommerce($idSite)
    {
        try {
            if (Site::isEcommerceEnabledFor($idSite)) {
                return true;
            }
        } catch (UnexpectedWebsiteFoundException $e) {
            // ignore this error, site was just deleted
        }

        return false;
    }

    public function getGoal($idSite, $idGoal)
    {
        $goals = $this->getAllGoals($idSite);

        if (isset($goals[$idGoal])) {
            return $goals[$idGoal];
        }

        if ($idGoal == GoalManager::IDGOAL_ORDER) {
            return array(
                'name' => Piwik::translate('Goals_EcommerceOrder'),
                'idsite' => $idSite,
                'idgoal' => GoalManager::IDGOAL_ORDER,
            );
        }
    }

}

