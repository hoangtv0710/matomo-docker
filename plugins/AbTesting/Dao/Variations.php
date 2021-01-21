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

class Variations
{
    private $table = 'experiments_variations';
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
                  `idvariation` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `idexperiment` int(11) UNSIGNED NOT NULL,
                  `name` VARCHAR(60) NOT NULL,
                  `percentage` TINYINT(3) UNSIGNED NULL,
                  `redirect_url` TEXT NULL,
                  `deleted` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
                  PRIMARY KEY(`idvariation`),
                  KEY(`idexperiment`)");
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
     * @return array
     */
    public function getActiveVariationsForExperiment($idExperiment)
    {
        $table = $this->tablePrefixed;
        $variations = $this->getDb()->fetchAll("SELECT * FROM $table where idexperiment = ? and deleted = 0", array($idExperiment));

        foreach ($variations as &$variation) {
            unset($variation['idexperiment']);
            unset($variation['deleted']);
        }

        return $variations;
    }

    /**
     * @param int $idExperiment
     * @return array
     */
    public function getAllVariationsForExperiment($idExperiment)
    {
        $table = $this->tablePrefixed;
        return $this->getDb()->fetchAll("SELECT * FROM $table where idexperiment = ?", array($idExperiment));
    }

    /**
     * @param int $idExperiment
     * @param array $variations
     * @return array
     */
    public function setVariations($idExperiment, $variations)
    {
        $activeVariations = $this->getAllVariationsForExperiment($idExperiment);

        foreach ($activeVariations as $activeVariation) {

            $found = false;

            foreach ($variations as $index => $variation) {
                if (strtolower($variation['name']) === strtolower($activeVariation['name'])) {
                    $found = true;
                    // we need to make sure to reuse existing variationId.
                    // If variation was previously deleted, we "enable" it again. We might want to change it later but for now lets reuse
                    // existing variationId and possibly already collected data under this variationID
                    $percentage = isset($variation['percentage']) ? $variation['percentage'] : null;
                    $url = !empty($variation['redirect_url']) ? $variation['redirect_url'] : null;

                    $this->updateVariation($idExperiment, $activeVariation['idvariation'], $variation['name'], $percentage, $url, $deleted = 0);

                    $variations[$index]['updated'] = true;
                    // we do not break here as there could be many variations having the same name and we want to update them all
                }
            }

            if (!$found) {
                // no longer existing variations
                $this->updateVariation($idExperiment, $activeVariation['idvariation'], $activeVariation['name'], $activeVariation['percentage'], $activeVariation['redirect_url'], $deleted = 1);
            }
        }

        // newly added variations
        foreach ($variations as $variation) {
            if (!empty($variation['updated'])) {
                continue;
            }

            $name = $variation['name'];
            $nameLower = strtolower($variation['name']);
            $percentage = isset($variation['percentage']) ? $variation['percentage'] : null;
            $url = !empty($variation['redirect_url']) ? $variation['redirect_url'] : null;

            if (isset($namesInserted[$nameLower])) {
                $this->updateVariation($idExperiment, $namesInserted[$nameLower], $name, $percentage, $url, $deleted = 0);
            } else {
                $namesInserted[$nameLower] = $this->insertVariation($idExperiment, $name, $percentage, $url, $deleted = 0);
            }
        }
    }

    public function updateVariation($idExperiment, $idVariation, $variationName, $percentage, $redirectUrl, $deleted)
    {
        if ($percentage === false || $percentage === '') {
            $percentage = null;
        }

        if ($redirectUrl === false || $redirectUrl === '') {
            $redirectUrl = null;
        }

        $idExperiment = (int) $idExperiment;
        $idVariation = (int) $idVariation;

        $values = array(
            'name' => $variationName,
            'percentage' => $percentage,
            'deleted' => $deleted,
            'redirect_url' => $redirectUrl
        );

        $db = $this->getDb();
        $db->update($this->tablePrefixed, $values, "idvariation = $idVariation AND idexperiment = $idExperiment");
    }

    public function insertVariation($idExperiment, $variationName, $percentage, $redirectUrl, $deleted)
    {
        if ($percentage === false || $percentage === '') {
            $percentage = null;
        }

        if ($redirectUrl === false || $redirectUrl === '') {
            $redirectUrl = null;
        }

        $values = array(
            'idexperiment' => $idExperiment,
            'name' => $variationName,
            'percentage' => $percentage,
            'deleted' => $deleted,
            'redirect_url' => $redirectUrl
        );

        $db = $this->getDb();
        $db->insert($this->tablePrefixed, $values);
        return $db->lastInsertId();
    }

}

