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

namespace Piwik\Plugins\Cohorts;


use Piwik\Config;

// TODO: UI Tests
class Configuration
{
    const DEFAULT_PERIODS_FROM_START_TO_SHOW = 10;
    const KEY_PERIODS_FROM_START_TO_SHOW = 'periods_from_start_to_show';
    const KEY_NUM_COHORTS_TO_SHOW = 'num_cohorts_to_show';
    const DEFAULT_NUM_COHORTS_TO_SHOW = 10;

    public function install()
    {
        $config = $this->getConfig();

        $section = $config->Cohorts;
        if (empty($section)) {
            $config->Cohorts = array();
        }
        $section = $config->Cohorts;

        // we make sure to set a value only if none has been configured yet, eg in common config.
        if (empty($section[self::KEY_PERIODS_FROM_START_TO_SHOW])) {
            $section[self::KEY_PERIODS_FROM_START_TO_SHOW] = self::DEFAULT_PERIODS_FROM_START_TO_SHOW;
        }
        if (empty($section[self::KEY_NUM_COHORTS_TO_SHOW])) {
            $section[self::KEY_NUM_COHORTS_TO_SHOW] = self::DEFAULT_NUM_COHORTS_TO_SHOW;
        }
        $config->Cohorts = $section;

        $config->forceSave();
    }

    public function uninstall()
    {
        $config = $this->getConfig();
        $config->Cohorts = array();
        $config->forceSave();
    }

    /**
     * @return int
     */
    public function getPeriodsFromStartToShow()
    {
        $value = $this->getConfigValue(self::KEY_PERIODS_FROM_START_TO_SHOW, self::DEFAULT_PERIODS_FROM_START_TO_SHOW);

        if (empty($value)) {
            $value = self::DEFAULT_PERIODS_FROM_START_TO_SHOW;
        }

        return (int) $value;
    }

    public function getNumberOfCohortsToDisplay()
    {
        $value = $this->getConfigValue(self::KEY_NUM_COHORTS_TO_SHOW, self::DEFAULT_NUM_COHORTS_TO_SHOW);

        if (empty($value)) {
            $value = self::DEFAULT_NUM_COHORTS_TO_SHOW;
        }

        return (int) $value;
    }

    private function getConfig()
    {
        return Config::getInstance();
    }

    private function getConfigValue($name, $default)
    {
        $config = $this->getConfig();
        $section = $config->Cohorts;
        if (isset($section[$name])) {
            return $section[$name];
        }
        return $default;
    }
}