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
namespace Piwik\Plugins\MultiChannelConversionAttribution\Models;

use Piwik\Piwik;

abstract class Base
{
    protected $id = '';

    public function __construct()
    {
        $classname = get_class($this);
        $parts = explode('\\', $classname);

        if (5 === count($parts)) {
            $this->id = lcfirst($parts[4]);
        }
    }

    public function getId()
    {
        return lcfirst($this->id);
    }

    abstract public function getName();
    abstract public function getDocumentation();

    abstract public function getAttributionQuery($posColumn, $totalColumn);

    /**
     * @return Base[]
     */
    public static function getAll()
    {
        $models = array();

        /**
         * Triggered to add new attribution models.
         *
         * **Example**
         *
         *     public function addModel(&$models)
         *     {
         *         $reports[] = new MyCustomModel();
         *     }
         *
         * @param Base[] $models An array of attribution models
         */
        Piwik::postEvent('MultiChannelConversionAttribution.addModels', $models);

        $models[] = new LastInteraction();
        $models[] = new LastNonDirect();
        $models[] = new FirstInteraction();
        $models[] = new Linear();
        $models[] = new PositionBased();
        $models[] = new TimeDecay();

        /**
         * Triggered to filter attribution models.
         *
         * **Example**
         *
         *     public function filterModels(&$models)
         *     {
         *         foreach ($models as $index => $model) {
         *              if ($model->getName() === Piwik::translate('MultiChannelConversionAttribution_FirstInteraction')) {}
         *                  unset($models[$index]); // remove the model having this name.
         *              }
         *         }
         *     }
         *
         * @param Base[] $models An array of attribution models
         */
        Piwik::postEvent('MultiChannelConversionAttribution.filterModels', $models);
        return $models;
    }

    /**
     * @return Base[]
     */
    public static function getByIds($attributionIds)
    {
        if (empty($attributionIds)) {
            return array();
        }

        $validIds = array();
        foreach (self::getAll() as $attribution) {
            $validIds[$attribution->getId()] = $attribution;
        }

        $attributions = array();
        foreach ($attributionIds as $attributionId) {
            if (isset($validIds[$attributionId])) {
                $attributions[] = $validIds[$attributionId];
            }
        }

        return $attributions;
    }
}
