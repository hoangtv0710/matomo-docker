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
namespace Piwik\Plugins\MediaAnalytics\Dao;

use Piwik\Common;

use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Segment;
use Piwik\Plugins\MediaAnalytics\Archiver;

class LogMediaPlays
{
    private $table = 'log_media_plays';
    private $tablePrefixed = '';

    const DEFAULT_SEGMENT_LENGTH = 30;
    const DEFAULT_SEGMENT_LENGTH_SMALL = 15;
    const USE_SMALL_SEGMENT_UP_TO_SECONDS = 300;
    const MAX_SEGMENT_SECTONDS = 7200;

    /**
     * @var Db|Db\AdapterInterface|\Piwik\Tracker\Db
     */
    private $db;

    public function __construct()
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
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
        $columns = array_map(function ($segmentColumn) {
            return '`' . $segmentColumn . '` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0';
        }, self::getSegmentColumns());
        $columns = implode(',', $columns);

        DbHelper::createTable($this->table, "
                  `idview` VARCHAR(".LogTable::MAX_LENGTH_IDVIEW.") NOT NULL,
                  `idvisit` BIGINT UNSIGNED NOT NULL,
                  $columns,
                  PRIMARY KEY(`idvisit`,`idview`)");
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
    }

    public function hasRecord($idVisit, $idView)
    {
        $sql = sprintf('SELECT 1 FROM %s WHERE idvisit = ? AND idview = ? LIMIT 1', $this->tablePrefixed);
        $bind = array($idVisit, $idView);
        $hasRecord = Db::fetchOne($sql, $bind);

        return !empty($hasRecord);
    }

    public function record($idView, $idVisit, $playedSegments, $mediaLength)
    {
        if (empty($playedSegments)) {
            return; // nothing needed to be written
        }

        $idView = !empty($idView) ? Common::mb_substr(trim($idView), 0, LogTable::MAX_LENGTH_IDVIEW) : '';
        $values = array(
            'idview' => $idView,
            'idvisit' => $idVisit,
        );

        $mappedSegments = $this->putMediaSegmentsIntoBuckets($playedSegments, $mediaLength);

        if (empty($mappedSegments)) {
            return; // nothing needed to be written
        }

        if ($this->hasRecord($idVisit, $idView)) {
            $this->updateRecord($idVisit, $idView, $mappedSegments);
            return;
        }

        foreach ($mappedSegments as $segment) {
            $values[self::makeSegmentColumn($segment)] = 1;
        }

        $columns = implode('`,`', array_keys($values));
        $vals = Common::getSqlStringFieldsArray($values);

        $sql = sprintf('INSERT INTO %s (`%s`) VALUES(%s) ',
                        $this->tablePrefixed, $columns, $vals);
        $bind = array_values($values);

        try {
            $this->getDb()->query($sql, $bind);
        } catch (\Exception $e) {
            if (Db::get()->isErrNo($e, \Piwik\Updater\Migration\Db::ERROR_CODE_DUPLICATE_ENTRY)) {
                // race condition where two tried to insert at same time... we need to update instead
                // note: if both requests that had race conditions set different media title... there could be
                // incosistencies since we don't know which media title or resolution had most recent information
                $this->updateRecord($idVisit, $idView, $mappedSegments);
                return;
            }
            throw $e;
        }
    }

    private function updateRecord($idVisit, $idView, $mappedSegments)
    {
        $update = '';
        foreach ($mappedSegments as $segment) {
            $update .= self::makeSegmentColumn($segment) . ' = 1,';
        }

        $sql = sprintf('UPDATE %s SET %s WHERE idvisit = ? AND idview = ?',
                            $this->tablePrefixed, rtrim($update, ','));
        $bind = array($idVisit, $idView);

        $this->getDb()->query($sql, $bind);
    }

    public function getAllRecords()
    {
        $records = $this->getDb()->fetchAll('SELECT * FROM ' . $this->tablePrefixed);
        foreach ($records as &$record) {
            $record['idvisit'] = (int)$record['idvisit'];
        }
        return $records;
    }

    public static function moveMaxLengthIntoSegment($allSegments, $segment)
    {
        foreach ($allSegments as $allSegment) {
            if ($allSegment >= $segment){
                return $allSegment;
            }
        }
    }

    public static function getSmallGapsSegments()
    {
        return range(self::DEFAULT_SEGMENT_LENGTH_SMALL, self::USE_SMALL_SEGMENT_UP_TO_SECONDS, self::DEFAULT_SEGMENT_LENGTH_SMALL);
    }

    public static function getSmallGapsSegmentsMadeRegularSize()
    {
        return range(self::DEFAULT_SEGMENT_LENGTH, self::USE_SMALL_SEGMENT_UP_TO_SECONDS, self::DEFAULT_SEGMENT_LENGTH);
    }

    public static function getSmallGapsPerGroup()
    {
        $smallSegments = self::getSmallGapsSegments();
        $groupedSegments = self::getSmallGapsSegmentsMadeRegularSize();
        sort($groupedSegments); // important they are sorted from small to large

        $group = array_fill_keys($groupedSegments, array());
        foreach ($groupedSegments as $groupedSegment) {
            foreach ($smallSegments as &$smallSegment) {
                if ($smallSegment && $smallSegment <= $groupedSegment) {
                    $group[$groupedSegment][] = $smallSegment;
                    $smallSegment = null; // make sure it won't be added again
                }
            }
        }
        return $group;
    }

    public static function getSegments()
    {
        $segments = self::getSmallGapsSegments();
        for ($i = self::USE_SMALL_SEGMENT_UP_TO_SECONDS + self::DEFAULT_SEGMENT_LENGTH; $i <= self::MAX_SEGMENT_SECTONDS; $i = $i + self::DEFAULT_SEGMENT_LENGTH) {
            $segments[] = $i;
        }
        return $segments;
    }

    public static function makeSegmentColumn($segment)
    {
        return 'segment_' . (int) $segment;
    }

    public static function makeSegmentGroupColumn($segment)
    {
        return 'segment_' . (int) $segment . Archiver::GROUPED_MEDIA_SEGMENT_APPENDIX;
    }

    public static function isSegmentColumn($segmentColumn)
    {
        return strpos($segmentColumn, 'segment_') !== false;
    }

    public static function isSegmentGroupColumn($segmentColumn)
    {
        return strpos($segmentColumn, 'segment_') !== false && Common::stringEndsWith($segmentColumn,Archiver::GROUPED_MEDIA_SEGMENT_APPENDIX);
    }

    public static function makeSegmentPosition($segmentColumn)
    {
        return  (int) str_replace(array('segment_', '_group'), '', $segmentColumn);
    }

    public static function getSegmentColumns()
    {
        return array_map(function ($segment) {
            return self::makeSegmentColumn($segment);
        }, self::getSegments());
    }

    private function putMediaSegmentsIntoBuckets($playedSegments, $mediaLength)
    {
        if (empty($playedSegments) || empty($mediaLength)) {
            return array();
        }

        $allSegments = self::getSegments();
        $maxSegment = max($allSegments);

        $mediaLengthSegment = LogMediaPlays::moveMaxLengthIntoSegment($allSegments, $mediaLength);
        if (!$mediaLengthSegment) {
            $mediaLengthSegment = $mediaLength;
        }

        $playedSegments = array_filter($playedSegments, function ($segment) use ($maxSegment, $mediaLengthSegment) {
            return $segment >= 0 && $segment <= $maxSegment && $segment <= $mediaLengthSegment;
        });

        $mappedSegments = array_map(function ($segment) use ($allSegments) {
            // map to the next segment bucket
            return self::moveMaxLengthIntoSegment($allSegments, $segment);
        }, $playedSegments);

        $mappedSegments = array_unique($mappedSegments);

        return $mappedSegments;
    }

}

