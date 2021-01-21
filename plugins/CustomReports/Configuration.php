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

namespace Piwik\Plugins\CustomReports;

use Piwik\Config;

class Configuration
{
    const DEFAULT_ARCHIVE_MAX_ROWS = 500;
    const DEFAULT_ARCHIVE_MAX_ROWS_SUBTABLE = 500;
    const DEFAULT_VALIDATE_REPORT_CONTENT_ALL_WEBSITES = 1;
    const DEFAULT_ALWAYS_SHOW_UNIQUE_VISITORS = 0;
    const DEFAULT_MAX_EXECUTION_TIME = 0;
    const DEFAULT_DISABLED_DIMENSIONS = '';

    const KEY_ARCHIVE_MAX_ROWS = 'datatable_archiving_maximum_rows_custom_reports';
    const KEY_ARCHIVE_MAX_ROWS_SUBTABLE = 'datatable_archiving_maximum_rows_subtable_custom_reports';
    const KEY_VALIDATE_REPORT_CONTENT_ALL_WEBSITES = 'custom_reports_validate_report_content_all_websites';
    const KEY_ALWAYS_SHOW_UNIQUE_VISITORS = 'custom_reports_always_show_unique_visitors';
    const KEY_MAX_EXECUTION_TIME = 'custom_reports_max_execution_time';
    const KEY_DISABLED_DIMENSIONS = 'custom_reports_disabled_dimensions';

    public function install()
    {
        $config = $this->getConfig();

        $reports = $config->CustomReports;
        if (empty($reports)) {
            $reports = array();
        }

        // we make sure to set a value only if none has been configured yet, eg in common config.
        if (empty($reports[self::KEY_ARCHIVE_MAX_ROWS])) {
            $reports[self::KEY_ARCHIVE_MAX_ROWS] = self::DEFAULT_ARCHIVE_MAX_ROWS;
        }
        if (empty($reports[self::KEY_ARCHIVE_MAX_ROWS_SUBTABLE])) {
            $reports[self::KEY_ARCHIVE_MAX_ROWS_SUBTABLE] = self::DEFAULT_ARCHIVE_MAX_ROWS_SUBTABLE;
        }
        if (empty($reports[self::KEY_VALIDATE_REPORT_CONTENT_ALL_WEBSITES])) {
            $reports[self::KEY_VALIDATE_REPORT_CONTENT_ALL_WEBSITES] = self::DEFAULT_VALIDATE_REPORT_CONTENT_ALL_WEBSITES;
        }
        if (empty($reports[self::KEY_ALWAYS_SHOW_UNIQUE_VISITORS])) {
            $reports[self::KEY_ALWAYS_SHOW_UNIQUE_VISITORS] = self::DEFAULT_ALWAYS_SHOW_UNIQUE_VISITORS;
        }
        if (empty($reports[self::KEY_MAX_EXECUTION_TIME])) {
            $reports[self::KEY_MAX_EXECUTION_TIME] = self::DEFAULT_MAX_EXECUTION_TIME;
        }
        if (empty($reports[self::KEY_DISABLED_DIMENSIONS])) {
            $reports[self::KEY_DISABLED_DIMENSIONS] = self::DEFAULT_DISABLED_DIMENSIONS;
        }

        $config->CustomReports = $reports;

        $config->forceSave();
    }

    public function uninstall()
    {
        $config = $this->getConfig();
        $config->CustomReports = array();
        $config->forceSave();
    }

    /**
     * @return array
     */
    public function getDisabledDimensions()
    {
        $value = $this->getConfigValue(self::KEY_DISABLED_DIMENSIONS, self::KEY_DISABLED_DIMENSIONS);
        if (is_string($value)) {
            $value = trim($value);
        }

        if (empty($value) || !is_string($value)) {
            return array();
        }
        $values = explode(',', $value);
        $values = array_map('trim', $values);

        return $values;
    }

    /**
     * @return int
     */
    public function getMaxExecutionTime()
    {
        $value = $this->getConfigValue(self::KEY_MAX_EXECUTION_TIME, self::DEFAULT_MAX_EXECUTION_TIME);

        if ($value === false || $value === '' || $value === null || !is_numeric($value)) {
            $value = self::DEFAULT_MAX_EXECUTION_TIME;
        }

        return (int) $value;
    }

    /**
     * @return int
     */
    public function getArchiveMaxRowsSubtable()
    {
        $value = $this->getConfigValue(self::KEY_ARCHIVE_MAX_ROWS_SUBTABLE, self::DEFAULT_ARCHIVE_MAX_ROWS_SUBTABLE);

        if ($value === false || $value === '' || $value === null) {
            $value = self::DEFAULT_ARCHIVE_MAX_ROWS_SUBTABLE;
        }

        return (int) $value;
    }

    /**
     * @return int
     */
    public function getArchiveMaxRows()
    {
        $value = $this->getConfigValue(self::KEY_ARCHIVE_MAX_ROWS, self::DEFAULT_ARCHIVE_MAX_ROWS);

        if ($value === false || $value === '' || $value === null) {
            $value = self::DEFAULT_ARCHIVE_MAX_ROWS;
        }

        return (int) $value;
    }

    /**
     * @return int
     */
    public function shouldAlwaysShowUniqueVisitors()
    {
        $value = $this->getConfigValue(self::KEY_ALWAYS_SHOW_UNIQUE_VISITORS, self::DEFAULT_ALWAYS_SHOW_UNIQUE_VISITORS);

        if ($value === false || $value === '' || $value === null) {
            $value = self::DEFAULT_ALWAYS_SHOW_UNIQUE_VISITORS;
        }

        return !empty($value);
    }

    /**
     * @return int
     */
    public function shouldValidateReportContentWhenAllSites()
    {
        $value = $this->getConfigValue(self::KEY_VALIDATE_REPORT_CONTENT_ALL_WEBSITES, self::DEFAULT_VALIDATE_REPORT_CONTENT_ALL_WEBSITES);

        if ($value === false || $value === '' || $value === null) {
            $value = self::DEFAULT_VALIDATE_REPORT_CONTENT_ALL_WEBSITES;
        }

        return (bool) $value;
    }

    private function getConfig()
    {
        return Config::getInstance();
    }

    private function getConfigValue($name, $default)
    {
        $config = $this->getConfig();
        $attribution = $config->CustomReports;
        if (isset($attribution[$name])) {
            return $attribution[$name];
        }
        return $default;
    }
}
