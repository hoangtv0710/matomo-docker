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

namespace Piwik\Plugins\MultiChannelConversionAttribution;

use Piwik\Config;

class Configuration
{
    const DEFAULT_AVAILABLE_DAYS_PRIOR_CONVERSION = '7,30,60,90';
    const DEFAULT_DAY_PRIOR_CONVERSION = 30;
    const KEY_AVAILABLE_DAYS_PRIOR_CONVERSION = 'available_days_prior_to_conversion';
    const KEY_DAY_PRIOR_CONVERSION = 'default_day_prior_to_conversion';

    public function install()
    {
        $config = $this->getConfig();
        $config->MultiChannelConversionAttribution = array(
            self::KEY_DAY_PRIOR_CONVERSION => self::DEFAULT_DAY_PRIOR_CONVERSION,
            self::KEY_AVAILABLE_DAYS_PRIOR_CONVERSION => self::DEFAULT_AVAILABLE_DAYS_PRIOR_CONVERSION,
        );
        $config->forceSave();
    }

    public function uninstall()
    {
        $config = $this->getConfig();
        $config->MultiChannelConversionAttribution = array();
        $config->forceSave();
    }

    public function getDayPriorToConversion()
    {
        $value = $this->getConfigValue(self::KEY_DAY_PRIOR_CONVERSION, self::DEFAULT_DAY_PRIOR_CONVERSION);

        $available = $this->getDaysPriorToConversion();

        if (!in_array($value, $available)) {
            if (!empty($available)) {
                return (int) reset($available);
            }

            // in this case it would return empty result as this won't be archived. but when daysPriorToConversion
            // is empty it wouldn't archive anything anyway.
            return self::DEFAULT_DAY_PRIOR_CONVERSION;
        }

        return (int) $value;
    }

    /**
     * @return array
     */
    public function getDaysPriorToConversion()
    {
        $value = $this->getConfigValue(self::KEY_AVAILABLE_DAYS_PRIOR_CONVERSION, self::DEFAULT_AVAILABLE_DAYS_PRIOR_CONVERSION);

        if (!empty($value)) {
            $days = explode(',', $value);
            return array_map('intval', $days);
        }

        return array();
    }

    private function getConfig()
    {
        return Config::getInstance();
    }

    private function getConfigValue($name, $default)
    {
        $config = $this->getConfig();
        $attribution = $config->MultiChannelConversionAttribution;
        if (isset($attribution[$name])) {
            return $attribution[$name];
        }
        return $default;
    }
}
