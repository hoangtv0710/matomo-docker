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

class Strategy
{
    private $table = 'experiments_strategy';
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
        DbHelper::createTable($this->table, "
                  `idexperiment` int(11) UNSIGNED NOT NULL,
                  `metric` VARCHAR(60) NOT NULL,
                  `strategy` VARCHAR(5) NOT NULL,
                  PRIMARY KEY(`idexperiment`, `metric`)");
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
    }

    /**
     * @return string
     */
    public function getUnprefixedTableName()
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getPrefixedTableName()
    {
        return $this->tablePrefixed;
    }

    /**
     * @param int $idExperiment
     * @param string $metricName
     * @return array|false
     */
    public function getStrategy($idExperiment, $metricName)
    {
        $table = $this->tablePrefixed;
        $strategies = $this->getDb()->fetchOne("SELECT strategy FROM $table where idexperiment = ? and metric = ?", array($idExperiment, $metricName));

        return $strategies;
    }

    /**
     * @param int $idExperiment
     * @param string $metricName
     * @param string $strategy
     * @return array
     */
    public function setStrategy($idExperiment, $metricName, $strategy)
    {
        $sql = sprintf('INSERT INTO %s (`idexperiment`, `metric`, `strategy`) VALUES(?,?,?) ON DUPLICATE KEY UPDATE strategy = ?',
            $this->tablePrefixed);

        $bind = array($idExperiment, $metricName, $strategy, $strategy);

        $this->getDb()->query($sql, $bind);
    }

}

