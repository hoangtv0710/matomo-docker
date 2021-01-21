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
namespace Piwik\Plugins\Funnels;

use Piwik\Common;
use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;
use Piwik\Updater\Migration;
use Piwik\Updater\Migration\Factory as MigrationFactory;

/**
 * Update for version 3.1.11.
 */
class Updates_3_1_11 extends PiwikUpdates
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
        $table = Common::prefixTable('log_funnel');
        $migration1 = $this->migration->db->sql("DELETE t1 FROM $table t1 INNER JOIN $table t2 WHERE t1.idlink_va > t2.idlink_va AND t1.idfunnel = t2.idfunnel AND t1.idvisit = t2.idvisit AND t1.step_position = t2.step_position");
        $migration2 = $this->migration->db->dropIndex('log_funnel', 'index_id_step_idvisit');
        $migration3 = $this->migration->db->addPrimaryKey('log_funnel', array('idfunnel', 'step_position', 'idvisit'));

        return array(
            $migration1,
            $migration2,
            $migration3,
        );
    }

    public function doUpdate(Updater $updater)
    {
        $updater->executeMigrations(__FILE__, $this->getMigrations($updater));
    }
}
