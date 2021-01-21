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
namespace Piwik\Plugins\HeatmapSessionRecording\Dao;

use Piwik\Common;

use Piwik\Db;
use Piwik\DbHelper;

class LogHsrEvent
{
    private $table = 'log_hsr_event';
    private $tablePrefixed = '';

    // HAS TO MATCH VALUE IN TRACKER!
    const OFFSET_ACCURACY = 2000;

    const MAX_SIZE = 32767;

    /**
     * @var Db|Db\AdapterInterface|\Piwik\Tracker\Db
     */
    private $db;

    /**
     * @var LogHsrBlob
     */
    private $logBlobHsr;

    public function __construct(LogHsrBlob $logBlobHsr)
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
        $this->logBlobHsr = $logBlobHsr;
    }

    private function getDb()
    {
        if (!isset($this->db)) {
            $this->db = Db::get();
        }
        return $this->db;
    }

    public function install()
    {
        DbHelper::createTable($this->table, "
                  `idhsrevent` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `idloghsr` INT(10) UNSIGNED NOT NULL,
                  `time_since_load` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 0,
                  `event_type` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                  `idselector` INT(10) UNSIGNED NULL DEFAULT NULL,
                  `x` SMALLINT(5) NOT NULL DEFAULT 0,
                  `y` SMALLINT(5) NOT NULL DEFAULT 0,
                  `idhsrblob` INT(10) UNSIGNED DEFAULT NULL,
                  PRIMARY KEY(`idhsrevent`),
                  INDEX idloghsr (`idloghsr`),
                  INDEX idhsrblob (`idhsrblob`)");
        // x and y is not unsigned on purpose as it may hold rarely a negative value
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
    }

    public function record($idloghsr, $timeSinceLoad, $eventType, $idSelector, $x, $y, $text)
    {
        if ($x > self::MAX_SIZE) {
            $x = self::MAX_SIZE;
        }

        if ($y > self::MAX_SIZE) {
            $y = self::MAX_SIZE;
        }

        if ($x === null || $x === false) {
            $x = 0;
        }

        if ($y === null || $y === false) {
            $y = 0;
        }

        $idHsrBlob = $this->logBlobHsr->record($text);

        $values = array(
            'idloghsr' => $idloghsr,
            'time_since_load' => $timeSinceLoad,
            'event_type' => $eventType,
            'idselector' => $idSelector,
            'x' => $x,
            'y' => $y,
            'idhsrblob' => $idHsrBlob,
        );

        $columns = implode('`,`', array_keys($values));

        $sql = sprintf('INSERT INTO %s (`%s`) VALUES(?,?,?,?,?,?,?) ', $this->tablePrefixed, $columns);

        $bind = array_values($values);

        $this->getDb()->query($sql, $bind);
    }

    public function getEventsForPageview($idLogHsr)
    {
        $sql = sprintf('SELECT %1$s.time_since_load, %1$s.event_type, %1$s.x, %1$s.y, %2$s.name as selector, %3$s.value as text, %3$s.compressed
                        FROM %1$s 
                        LEFT JOIN %2$s ON %1$s.idselector = %2$s.idaction 
                        LEFT JOIN %3$s ON %1$s.idhsrblob = %3$s.idhsrblob 
                        WHERE %1$s.idloghsr = ? 
                        ORDER BY time_since_load ASC', $this->tablePrefixed, Common::prefixTable('log_action'), Common::prefixTable('log_hsr_blob'));

        $rows = $this->getDb()->fetchAll($sql, array($idLogHsr));
        foreach ($rows as $index => $row) {
            if (!empty($row['compressed'])) {
                $rows[$index]['text'] = gzuncompress($row['text']);
            }
            unset($rows[$index]['compressed']);
        }
        return $rows;
    }

    public function getAllRecords()
    {
        return $this->getDb()->fetchAll('SELECT * FROM ' . $this->tablePrefixed);
    }
}

