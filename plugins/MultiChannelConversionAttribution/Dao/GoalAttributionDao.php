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
namespace Piwik\Plugins\MultiChannelConversionAttribution\Dao;

use Piwik\Common;
use Piwik\DbHelper;
use Piwik\Db;

class GoalAttributionDao
{
    private $table = 'goal_attribution';
    private $prefixedTable = '';

    public function __construct()
    {
        $this->prefixedTable = Common::prefixTable('goal_attribution');
    }

    private function getDb()
    {
        return Db::get();
    }

    public function install()
    {
        DbHelper::createTable($this->table, "
                  `idsite` int(11) UNSIGNED NOT NULL,
                  `idgoal` int(11) UNSIGNED NOT NULL,
                  PRIMARY KEY (`idsite`, `idgoal`)");
    }

    public function uninstall()
    {
        Db::query(sprintf('drop table if exists `%s`', $this->prefixedTable));
    }

    /**
     * @param int $idSite
     * @param int $idGoal
     * @return array|false
     */
    public function isAttributionEnabled($idSite, $idGoal)
    {
        $query = sprintf('select * from %s where idgoal = ? and idsite = ?', $this->prefixedTable);
        $db = $this->getDb();
        $row = $db->fetchRow($query, array($idGoal, $idSite));

        return !empty($row);
    }

    /**
     * @param int $idSite
     * @param int $idGoal
     */
    public function addGoalAttribution($idSite, $idGoal)
    {
        $query = sprintf('insert into %s (`idsite`,`idgoal`)
                          values (?, ?) 
                          on duplicate key update `idgoal` = ?', $this->prefixedTable);

        $db = $this->getDb();
        $db->query($query, array($idSite, $idGoal, $idGoal));
    }

    /**
     * @param int $idSite
     * @param int $idGoal
     */
    public function removeGoalAttribution($idSite, $idGoal)
    {
        $query = sprintf('delete from %s where idsite = ? and idgoal = ?', $this->prefixedTable);
        $db = $this->getDb();
        $db->query($query, array($idSite, $idGoal));
    }

    /**
     * @param int $idSite
     * @param int $idGoal
     */
    public function removeSiteAttributions($idSite)
    {
        $query = sprintf('delete from %s where idsite = ?', $this->prefixedTable);
        $db = $this->getDb();
        $db->query($query, array($idSite));
    }

    /**
     * @param int $idSite
     * @return array
     */
    public function getSiteAttributionGoalIds($idSite)
    {
        $query = sprintf('select idgoal from %s where idsite = ?', $this->prefixedTable);
        $db = $this->getDb();
        $rows = $db->fetchAll($query, array($idSite));
        $idGoals = array();

        foreach ($rows as $row) {
            $idGoals[] = (int) $row['idgoal'];
        }

        return $idGoals;
    }

}

