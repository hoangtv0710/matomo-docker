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
use Piwik\Plugins\AbTesting\Model\Experiments;

class Experiment
{
    private $table = 'experiments';
    private $tablePrefixed = '';

    /**
     * @var Variations
     */
    private $variations;

    public function __construct()
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
        $this->variations = new Variations();
    }

    private function getDb()
    {
        return Db::get();
    }

    public function install()
    {
        DbHelper::createTable($this->table, "
                  `idexperiment` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `idsite` int(11) UNSIGNED NOT NULL,
                  `confidence_threshold` DECIMAL(3,1) NOT NULL DEFAULT 95,
                  `mde_relative` SMALLINT(5) NOT NULL DEFAULT 15,
                  `name` VARCHAR(60) NOT NULL, 
                  `description` VARCHAR(1000) NOT NULL,
                  `hypothesis` VARCHAR(1000) NOT NULL,
                  `included_targets` TEXT NOT NULL,
                  `excluded_targets` TEXT NOT NULL,
                  `success_metrics` TEXT NOT NULL,
                  `percentage_participants` TINYINT UNSIGNED NOT NULL DEFAULT 100,
                  `original_redirect_url` TEXT NULL,
                  `status` VARCHAR(30) NOT NULL,
                  `start_date` DATETIME DEFAULT NULL,
                  `modified_date` DATETIME NOT NULL,
                  `end_date` DATETIME DEFAULT NULL,
                  PRIMARY KEY(`idexperiment`),
                  UNIQUE KEY(`idsite`, `name`)");

        $this->variations->install();
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));

        $this->variations->uninstall();
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
     * @return array
     */
    public function getAllExperiments()
    {
        $table = $this->tablePrefixed;
        $experiments = $this->getDb()->fetchAll("SELECT * FROM $table");
        return $this->enrichExperiments($experiments);
    }

    /**
     * @param $idSite
     * @return bool
     */
    public function hasExperimentsForSite($idSite)
    {
        $table = $this->tablePrefixed;
        $numExperiments = $this->getDb()->fetchOne("SELECT count(idsite) FROM $table WHERE idsite = ? LIMIT 1", array($idSite));

        return !empty($numExperiments);
    }

    /**
     * @return int
     */
    public function getNumExperimentsTotal()
    {
        $table = $this->tablePrefixed;
        $numExperiments = $this->getDb()->fetchOne("SELECT count(*) FROM $table WHERE status != ?", array(Experiments::STATUS_ARCHIVED));

        return $numExperiments;
    }

    /**
     * @return array
     */
    public function getAllExperimentsForSite($idSite)
    {
        $table = $this->tablePrefixed;
        $experiments = $this->getDb()->fetchAll("SELECT * FROM $table WHERE idsite = ?", array($idSite));

        return $this->enrichExperiments($experiments);
    }

    /**
     * @param $idExperiment
     * @param $idSite
     * @return array|false
     * @throws \Exception
     */
    public function getExperiment($idExperiment, $idSite)
    {
        $table = $this->tablePrefixed;
        $experiment = $this->getDb()->fetchRow("SELECT * FROM $table WHERE idexperiment = ? and idsite = ?", array($idExperiment, $idSite));

        return $this->enrichExperiment($experiment);
    }

    /**
     * @param $idExperiment
     * @return array|false
     * @throws \Exception
     */
    public function getExperimentById($idExperiment)
    {
        $table = $this->tablePrefixed;
        $experiment = $this->getDb()->fetchRow("SELECT * FROM $table WHERE idexperiment = ?", array($idExperiment));

        return $this->enrichExperiment($experiment);
    }

    /**
     * Get idexperiment by name
     * @param $name
     * @param $idSite
     * @return int|false
     */
    public function getIdExperimentByName($name, $idSite)
    {
        $table = $this->tablePrefixed;
        $id = $this->getDb()->fetchOne("SELECT idexperiment FROM $table WHERE name = ? and idsite = ?", array($name, $idSite));

        return $id;
    }

    /**
     * @return array
     */
    public function getExperimentsByStatuses($idSite, $statuses)
    {
        $bind = $statuses;
        $bind[] = $idSite;

        $fields = Common::getSqlStringFieldsArray($statuses);

        $table = $this->tablePrefixed;
        $experiments = $this->getDb()->fetchAll("SELECT * FROM $table WHERE status IN($fields) AND idsite = ?", $bind);

        return $this->enrichExperiments($experiments);
    }

    /**
     * @return array
     */
    public function getAllExperimentsByStatuses($statuses)
    {
        $bind = $statuses;

        $fields = Common::getSqlStringFieldsArray((array) $statuses);

        $table = $this->tablePrefixed;
        $experiments = $this->getDb()->fetchAll("SELECT * FROM $table WHERE status IN($fields)", $bind);

        return $this->enrichExperiments($experiments);
    }

    public function createExperiment($columns)
    {
        $variations = array();
        if (isset($columns['variations'])) {
            $variations = $columns['variations'];
            unset($columns['variations']);
        }

        $columns = $this->encodeFieldsWhereNeeded($columns);

        $db = $this->getDb();
        $db->insert($this->tablePrefixed, $columns);

        $idExperiment = $db->lastInsertId();

        $this->variations->setVariations($idExperiment, $variations);

        return $idExperiment;
    }

    public function updateExperimentColumns($idExperiment, $idSite, $columns)
    {
        $variations = null;
        if (isset($columns['variations'])) {
            $variations = $columns['variations'];
            unset($columns['variations']);
        }

        $columns = $this->encodeFieldsWhereNeeded($columns);

        if (!empty($columns)) {
            $fields = array();
            $bind = array();
            foreach ($columns as $key => $value) {
                $fields[] = ' ' . $key . '= ?';
                $bind[] = $value;
            }
            $fields = implode(',', $fields);

            $query = sprintf('UPDATE %s SET %s WHERE idexperiment = %d AND idsite = %d', $this->tablePrefixed, $fields, $idExperiment, $idSite);

            // we do not use $db->update() here as this method is as well used in Tracker mode and the tracker DB does not
            // support "->update()". Therefore we use the query method where we know it works with tracker and regular DB
            $this->getDb()->query($query, $bind);
        }

        if (isset($variations)) {
            $this->variations->setVariations($idExperiment, $variations);
        }
    }

    /**
     * @param int $idSite
     */
    public function deleteExperimentsForSite($idSite)
    {
        $table = $this->tablePrefixed;

        $query = "DELETE FROM $table WHERE idsite = ?";
        $bind = array($idSite);

        // TODO delete variations as well

        $this->getDb()->query($query, $bind);
    }

    /**
     * @param int $idExperiment
     * @param int $idSite
     */
    public function deleteExperiment($idExperiment, $idSite)
    {
        $table = $this->tablePrefixed;

        $query = "DELETE FROM $table WHERE idsite = ? and idexperiment = ?";
        $bind = array($idSite, $idExperiment);

        // TODO delete variations as well

        $this->getDb()->query($query, $bind);
    }

    private function enrichExperiments($experiments)
    {
        if (empty($experiments)) {
            return array();
        }

        foreach ($experiments as $index => $experiment) {
            $experiments[$index] = $this->enrichExperiment($experiment);
        }

        return $experiments;
    }

    private function enrichExperiment($experiment)
    {
        if (empty($experiment)) {
            return $experiment;
        }

        // cast to string done
        $experiment['idexperiment'] = (string) $experiment['idexperiment'];
        $experiment['idsite'] = (string) $experiment['idsite'];
        $experiment['confidence_threshold'] = (string) $experiment['confidence_threshold'];
        $experiment['percentage_participants'] = (string) $experiment['percentage_participants'];
        $experiment['mde_relative'] = (string) $experiment['mde_relative'];

        if (strpos($experiment['start_date'], '0000-00-00') === 0) {
            $experiment['start_date'] = null;
        }
        if (strpos($experiment['end_date'], '0000-00-00') === 0) {
            $experiment['end_date'] = null;
        }

        $experiment['variations'] = $this->variations->getActiveVariationsForExperiment($experiment['idexperiment']);
        $experiment['excluded_targets'] = $this->decodeField($experiment['excluded_targets']);
        $experiment['included_targets'] = $this->decodeField($experiment['included_targets']);
        $experiment['success_metrics'] = $this->decodeField($experiment['success_metrics']);

        return $experiment;
    }

    private function encodeFieldsWhereNeeded($columns)
    {
        foreach ($columns as $column => $value) {
            if (in_array($column, array('included_targets', 'excluded_targets', 'success_metrics'))) {
                $columns[$column] = $this->encodeField($value);
            }
        }

        return $columns;
    }

    private function encodeField($field)
    {
        if (empty($field) || !is_array($field)) {
            $field = array();
        }

        return json_encode($field);
    }

    private function decodeField($field)
    {
        if (!empty($field)) {
            $field = @json_decode($field, true);
        }

        if (empty($field) || !is_array($field)) {
            $field = array();
        }

        return $field;
    }

}

