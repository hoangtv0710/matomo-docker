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

namespace Piwik\Plugins\MediaAnalytics;

use Piwik\Plugins\MediaAnalytics\Dao\LogMediaPlays;
use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;
use Piwik\Updater\Migration\Factory as MigrationFactory;

class Updates_3_4_0 extends PiwikUpdates
{
    /**
     * @var MigrationFactory
     */
    private $migration;

    public function __construct(MigrationFactory $factory)
    {
        $this->migration = $factory;
    }

    public function getMigrations(Updater $updater)
    {
        $columns = array(
            'idview' => 'VARCHAR(6) NOT NULL',
            'idvisit' => 'BIGINT UNSIGNED NOT NULL'
        );
        foreach (LogMediaPlays::getSegmentColumns() as $segmentColumn) {
            $columns[$segmentColumn] = 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0';
        }
        $migration = $this->migration->db->createTable('log_media_plays', $columns, array('idvisit','idview'));
        return array($migration);
    }

    public function doUpdate(Updater $updater)
    {
        $updater->executeMigrations(__FILE__, $this->getMigrations($updater));
    }
}

