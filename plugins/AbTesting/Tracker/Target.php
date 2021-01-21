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

namespace Piwik\Plugins\AbTesting\Tracker;

use Piwik\Piwik;
use Piwik\Tracker\Request;
use Piwik\UrlHelper;

class Target
{
    const ATTRIBUTE_URL = 'url';
    const ATTRIBUTE_PATH = 'path';
    const ATTRIBUTE_URLPARAM = 'urlparam';

    const TYPE_ANY = 'any';
    const TYPE_EXISTS = 'exists';
    const TYPE_EQUALS_SIMPLE = 'equals_simple';
    const TYPE_EQUALS_EXACTLY = 'equals_exactly';
    const TYPE_CONTAINS = 'contains';
    const TYPE_STARTS_WITH = 'starts_with';
    const TYPE_REGEXP = 'regexp';

    /**
     * @var array
     */
    private $target;

    public function __construct($target)
    {
        $this->target = $target;
    }

    public static function doesTargetTypeRequireValue($type)
    {
        return $type !== self::TYPE_ANY;
    }
    
    public static function getAvailableTargetTypes()
    {
        $targetTypes = array();

        $urlOptions = array(
            Target::TYPE_EQUALS_EXACTLY => Piwik::translate('AbTesting_TargetTypeEqualsExactly'),
            Target::TYPE_EQUALS_SIMPLE => Piwik::translate('AbTesting_TargetTypeEqualsSimple'),
            Target::TYPE_CONTAINS => Piwik::translate('AbTesting_TargetTypeContains'),
            Target::TYPE_STARTS_WITH => Piwik::translate('AbTesting_TargetTypeStartsWith'),
            Target::TYPE_REGEXP => Piwik::translate('AbTesting_TargetTypeRegExp'),
        );

        $urlAttribute = array(
            'value' => Target::ATTRIBUTE_URL,
            'name' => Piwik::translate('AbTesting_TargetAttributeUrl'),
            'types' => array(),
            'example' => 'http://www.example.com/' . Piwik::translate('AbTesting_FilesystemDirectory')
        );
        foreach ($urlOptions as $key => $value) {
            $urlAttribute['types'][] = array('value' => $key, 'name' => $value);
        }
        $targetTypes[] = $urlAttribute;


        $urlAttribute = array(
            'value' => Target::ATTRIBUTE_PATH,
            'name' => Piwik::translate('AbTesting_TargetAttributePath'),
            'types' => array(),
            'example' => '/' . Piwik::translate('AbTesting_FilesystemDirectory')
        );
        foreach ($urlOptions as $key => $value) {
            $urlAttribute['types'][] = array('value' => $key, 'name' => $value);
        }
        $targetTypes[] = $urlAttribute;


        $urlAttribute = array(
            'value' => Target::ATTRIBUTE_URLPARAM,
            'name' => Piwik::translate('AbTesting_TargetAttributeUrlParameter'),
            'types' => array(),
            'example' => Piwik::translate('AbTesting_TargetAttributeUrlParameterExample')
        );
        
        $parameterOptions = array(
            Target::TYPE_EXISTS => Piwik::translate('AbTesting_TargetTypeExists'),
            Target::TYPE_EQUALS_EXACTLY => Piwik::translate('AbTesting_TargetTypeEqualsExactly'),
            Target::TYPE_CONTAINS => Piwik::translate('AbTesting_TargetTypeContains'),
            Target::TYPE_REGEXP => Piwik::translate('AbTesting_TargetTypeRegExp'),
        );

        foreach ($parameterOptions as $key => $value) {
            $urlAttribute['types'][] = array('value' => $key, 'name' => $value);
        }

        $targetTypes[] = $urlAttribute;

        return $targetTypes;
    }

}
