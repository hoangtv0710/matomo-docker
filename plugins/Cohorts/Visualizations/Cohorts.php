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

namespace Piwik\Plugins\Cohorts\Visualizations;


use Piwik\Common;
use Piwik\DataTable\Row;
use Piwik\Date;
use Piwik\Period;
use Piwik\Piwik;
use Piwik\Plugins\Cohorts\Columns\Metrics\CohortTableColumn;
use Piwik\Plugins\Cohorts\Columns\Metrics\VisitorRetentionPercent;
use Piwik\Plugins\Cohorts\Configuration;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\SettingsPiwik;

/**
 * @property Cohorts\Config $config
 * @property Cohorts\RequestConfig $requestConfig
 */
class Cohorts extends HtmlTable
{
    const ID = 'cohorts';
    const FOOTER_ICON_TITLE = '';
    const FOOTER_ICON = '';

    public static function getDefaultConfig()
    {
        return new Cohorts\Config();
    }

    public static function getDefaultRequestConfig()
    {
        return new Cohorts\RequestConfig();
    }

    public function supportsComparison()
    {
        return false;
    }

    public function beforeLoadDataTable()
    {
        parent::beforeLoadDataTable();

        $metric = Common::getRequestVar('metric', false);

        $period = Common::getRequestVar('period', false);
        $isUniqueVisitorsEnabled = SettingsPiwik::isUniqueVisitorsEnabled($period);
        if (!$isUniqueVisitorsEnabled
            && ($metric == 'nb_uniq_visitors'
                || $metric == 'nb_users')
        ) {
            $this->requestConfig->metric = 'nb_visits';
        }
    }

    public function beforeRender()
    {
        parent::beforeRender();

        // filter_limit isn't always available when GetCohorts::init() is called, so we have to add the column translations manually here as well
        $period = Common::getRequestVar('period', false);
        $date = Common::getRequestVar('date', false);
        $filter_limit = $this->requestConfig->filter_limit;
        if (!empty($period)
            && !empty($date)
            && !empty(Piwik::$idPeriods[$period])
        ) {
            $configuration = new Configuration();
            $periodsFromStart = $filter_limit > 0 ? $filter_limit : $configuration->getPeriodsFromStartToShow();

            if ($period == 'range') {
                $period = 'day';
                $periodObject = Period\Factory::build($period, $date);
                $periodsFromStart = $periodObject->getNumberOfSubperiods();
            }

            for ($i = 0; $i <= $periodsFromStart; ++$i) {
                $metric = new CohortTableColumn($period, $i);
                $this->config->addTranslation($metric->getName(), $metric->getTranslatedName());
            }
        }
    }

    public function afterAllFiltersAreApplied()
    {
        parent::afterAllFiltersAreApplied();

        $this->config->addTranslation('label', Piwik::translate('Cohorts_Cohort'));
    }

    public function getCellHtmlAttributes(Row $row, $column)
    {
        if ($column === 'label') {
            return null;
        }

        if (preg_match('/[a-z]0$/', $column)) { // if the first column, grey it out since it will always be 100%
            return 'background-color: #eff0f1; color: #37474f;'; // TODO: should get this from theme if possible.
        }

        $columns = $row->getColumns();
        $cohort = reset($columns);

        $total = next($columns);
        $total = $this->getNumericValue($total);

        $formattedColumnValue = $row->getColumn($column) ?: 0;
        $columnValue = $this->getNumericValue($formattedColumnValue);

        $columnValueRatio = $total == 0 ? 0 : min($columnValue / $total, 1.0);

        $backgroundColor = $this->getColorInRange($this->config->cell_background_color_min, $this->config->cell_background_color_max, $columnValueRatio);
        $textColor = $columnValueRatio > 0.5 ? $this->config->cell_text_color_contrast : $this->config->cell_text_color;

        return [
            'style' => 'background-color: ' . $backgroundColor . '; color: ' . $textColor . ';',
            'title' => $this->getCellTooltip($formattedColumnValue, $cohort, $row->getMetadata('date'), $column),
        ];
    }

    private function getCellTooltip($columnValue, $cohort, $dateStr, $column)
    {
        $metric = $this->requestConfig->metric;
        $metricTranslationPlural = isset($this->config->translations[$metric]) ? lcfirst($this->config->translations[$metric]) : $metric;

        $periodStr = Common::getRequestVar('period');
        if ($periodStr == 'range') {
            $periodStr = 'day';
        }

        preg_match('/[a-z]([0-9]+)$/', $column, $matches);
        if (empty($matches[1])) {
            return '';
        }

        $n = (int)$matches[1];

        $period = Period\Factory::build($periodStr, Date::factory($dateStr)->addPeriod($n, $periodStr))->getLocalizedLongString();

        if ($metric == VisitorRetentionPercent::NAME) {
            return Piwik::translate('Cohorts_VisitorRetentionTooltip', [$period, $columnValue, $cohort]);
        } else {
            return Piwik::translate('Cohorts_GenericMetricTooltip', [$period, $columnValue . ' ' . $metricTranslationPlural, $cohort]);
        }
    }

    private function getColorInRange($minColor, $maxColor, $ratio)
    {
        list($minR, $minG, $minB) = self::parseColor($minColor);
        list($maxR, $maxG, $maxB) = self::parseColor($maxColor);

        $r = $minR + (($maxR - $minR) * $ratio);
        $b = $minB + (($maxB - $minB) * $ratio);
        $g = $minG + (($maxG - $minG) * $ratio);

        return 'rgb(' . $r . ',' . $g . ',' . $b . ')';
    }

    public static function parseColor($color)
    {
        if (strlen($color) == 4) {
            $color = '#' . str_repeat(substr($color, 1, 1), 2) . str_repeat(substr($color, 2, 1), 2) . str_repeat(substr($color, 3, 1), 2);
        } else if (strlen($color) != 7) {
            throw new \Exception("Unknown HTML color format '$color'.");
        }

        return [
            (int) hexdec(substr($color, 1, 2)),
            (int) hexdec(substr($color, 3, 2)),
            (int) hexdec(substr($color, 5, 2)),
        ];
    }

    private function getNumericValue($columnValue)
    {
        if (is_numeric($columnValue)) {
            return $columnValue;
        }

        $columnValue = preg_replace('/[^0-9,.]/', '', $columnValue);
        $columnValue = str_replace(',', '.', $columnValue);
        $columnValue = trim($columnValue);
        return (float)$columnValue;
    }
}
