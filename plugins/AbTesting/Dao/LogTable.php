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
namespace Piwik\Plugins\AbTesting\Dao;

use Piwik\Common;

use Piwik\Db;
use Piwik\DbHelper;

class LogTable
{
    private $table = 'log_abtesting';
    private $tablePrefixed = '';

    public function __construct()
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
    }
    
    private function getDb()
    {
        return Db::get();
    }

    public function install()
    {
        // TODO should we log the URL it was used on? problem: the experiment could be used on many pages which means
        // we would need to create a log entry per experiment usage per visit.
        DbHelper::createTable($this->table, "
                  `idvisitor` binary(8) NOT NULL,
                  `idvisit` BIGINT unsigned NOT NULL,
                  `idsite` int(11) unsigned NOT NULL,
                  `idexperiment` int(11) UNSIGNED NOT NULL,
                  `idvariation` int(11) UNSIGNED NOT NULL,
                  `entered` tinyint(1) UNSIGNED DEFAULT 0,
                  `server_time` DATETIME NOT NULL,
                  PRIMARY KEY(`idvisit`,`idexperiment`),
                  KEY(`idsite`,`idexperiment`,`server_time`),
                  KEY(`idvisitor`)");
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
    }

    public function getRunningExperimentsForVisitor($idVisitor, $runningExperimentIds, $beforeServerTime)
    {
        $runningExperimentIds = array_map('intval', $runningExperimentIds);

        $sql = sprintf('SELECT distinct idexperiment, idvariation 
                       FROM %s 
                       WHERE idvisitor = ? 
                            AND idexperiment IN(%s)
                            AND server_time <= ?', $this->tablePrefixed, implode(',', $runningExperimentIds));

        $bind = array(
            $idVisitor,
            // server time is important so we do not add experiments that were actually entered only to a later point
            $beforeServerTime
        );

        return Db::fetchAll($sql, $bind);
    }

    public function enterVisitorAutomatically($experiments, $idVisitor, $idVisit, $idSite, $serverTime)
    {
        foreach ($experiments as $experiment) {
            $this->record($idVisitor, $idVisit, $idSite, $experiment['idexperiment'], $experiment['idvariation'], $entered = 0, $serverTime);
        }
    }

    public function record($idVisitor, $idVisit, $idSite, $idExperiment, $variationId, $entered, $serverTime)
    {
        $values = array(
            'idvisitor' => $idVisitor,
            'idvisit' => $idVisit,
            'idsite' => $idSite,
            'idexperiment' => $idExperiment,
            'idvariation' => $variationId,
            'entered' => !empty($entered) ? 1 : 0,
            'server_time' => $serverTime,
        );

        $columns = implode('`,`', array_keys($values));

        $sql = sprintf('INSERT INTO %s (`%s`) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE idvariation = ?, entered = ?',
                       $this->tablePrefixed, $columns);

        $bind = array_values($values);
        $bind[] = $variationId;
        $bind[] = $entered;

        $this->getDb()->query($sql, $bind);
    }

    public function getAllRecords()
    {
        return $this->getDb()->fetchAll('SELECT * FROM ' . $this->tablePrefixed);
    }

    private function getDbReader()
    {
        if (method_exists(Db::class, 'getReader')) {
            return Db::getReader();
        } else {
            return Db::get();
        }
    }

    public function getRecordsForIdVisits($idVisits)
    {
        if (empty($idVisits)) {
            return [];
        }

        $idVisits = array_map('intval', $idVisits);
        $query = sprintf('SELECT * FROM %1$s WHERE idvisit IN (%2$s) AND entered = 1', $this->tablePrefixed, implode(',', $idVisits));
        return $this->getDbReader()->fetchAll($query);
    }
}

