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

namespace Piwik\Plugins\AbTesting\Input;

use \Exception;
use Piwik\Piwik;
use Piwik\UrlHelper;

class Variation
{
    const NAME_MAX_LENGTH = 50;
    const URL_MAX_LENGTH = 2000;

    /**
     * @var array
     */
    private $variation;

    /**
     * @var int
     */
    private $index;
    
    public function __construct($variation, $index)
    {
        $this->variation = $variation;
        $this->index = $index;
    }

    public function check()
    {
        if (!is_array($this->variation)) {
            $titleSingular = Piwik::translate('AbTesting_Variation');
            $titlePlural = Piwik::translate('AbTesting_Variations');

            throw new Exception(Piwik::translate('AbTesting_ErrorInnerIsNotAnArray', array($titleSingular, $titlePlural)));
        }

        $this->checkName();
        $this->checkPercentage();
        $this->checkRedirectUrl();
    }

    private function checkPercentage()
    {
        if (empty($this->variation['percentage'])) {
            return;
        }

        $title = Piwik::translate('AbTesting_VariationPercentage') . ' (' . $this->variation['name'] . ')';

        $percentage = $this->variation['percentage'];

        if (!is_numeric($percentage)) {
            throw new Exception(Piwik::translate('AbTesting_ErrorXNotANumber', array($title)));
        }

        if ($percentage > 100) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXTooHigh', array($title, 100)));
        }

        if ($percentage < 0) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXTooLow', array($title, 0)));
        }
    }

    private function checkName()
    {
        $title = 'AbTesting_VariationName';

        if (!array_key_exists('name', $this->variation)) {
            $title = Piwik::translate('AbTesting_Variations');
            throw new Exception(Piwik::translate('AbTesting_ErrorArrayMissingKey', array('name', $title, $this->index)));
        }

        if (empty($this->variation['name'])) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXNotProvided', $title));
        }

        $name = $this->variation['name'];

        if (strlen($name) > static::NAME_MAX_LENGTH) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXTooLong', array($title, static::NAME_MAX_LENGTH)));
        }

        if (preg_match('/\s/', $name)) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXContainsWhitespace', $title));
        }

        if (preg_match('/^\d+$/', $name)) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXContainsOnlyNumbers', $title));
        }

        $blockedCharacters = array(
            '/', '\\', '&', '.', '<', '>', "'", '"', '`', '´', '!', '$', ':'
        );

        if (strip_tags($name) !== $name || str_replace($blockedCharacters, '', $name) !== $name) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXOnlyAlNumDash', $title));
        }

        if ($name === 'Original' || $name === Piwik::translate('AbTesting_NameOriginalVariation')) {
            throw new Exception(Piwik::translate('AbTesting_ErrorVariationNameOriginalNotAllowed'));
        }
    }

    private function checkRedirectUrl()
    {
        if (empty($this->variation['redirect_url'])) {
            return;
        }

        $title = Piwik::translate('AbTesting_VariationRedirectUrl');

        $url = $this->variation['redirect_url'];

        if (strlen($url) > static::URL_MAX_LENGTH) {
            throw new Exception(Piwik::translate('AbTesting_ErrorXTooLong', array($title, static::URL_MAX_LENGTH)));
        }

        if (!UrlHelper::isLookLikeUrl($url)) {
            throw new Exception(Piwik::translate('AbTesting_ErrorNotValidUrl', $title));
        }

    }
}