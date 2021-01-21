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

namespace Piwik\Plugins\MultiChannelConversionAttribution\Reports;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\MultiChannelConversionAttribution\Columns\Metrics\Conversion;
use Piwik\Plugins\MultiChannelConversionAttribution\Columns\ChannelType;
use Piwik\Plugins\MultiChannelConversionAttribution\Metrics;
use Piwik\View;
use Piwik\Plugins\MultiChannelConversionAttribution\Models;
use Piwik\Plugins\MultiChannelConversionAttribution\Columns\Metrics\AttributionComparison;
use Piwik\Plugins\MultiChannelConversionAttribution\Columns\Metrics\Revenue;
use Piwik\Plugin\ProcessedMetric;

class GetChannelAttribution extends Base
{
    /**
     * @var Models\Base
     */
    private $baseModel = null;
    /**
     * @var Models\Base[]
     */
    private $compareModels = array();

    protected function init()
    {
        $this->categoryId = 'Goals_Goals';

        $models = Common::getRequestVar('attributionModels', '', 'string');
        $models = explode(',', $models);
        $this->compareModels = Models\Base::getByIds($models);

        $this->processedMetrics = array();
        $this->metrics = array();

        if (!empty($this->compareModels)) {
            $this->baseModel = array_shift($this->compareModels);
        } else {
            $this->baseModel = null;
        }

        if (!empty($this->baseModel)) {
            $this->defaultSortColumn = Metrics::completeAttributionMetric(Metrics::SUM_CONVERSIONS, $this->baseModel);
        }

        foreach (Models\Base::getAll() as $model) {
            $conversion = Metrics::completeAttributionMetric(Metrics::SUM_CONVERSIONS, $model);
            $revenue = Metrics::completeAttributionMetric(Metrics::SUM_REVENUE, $model);
            $this->metrics[] = $conversion;
            $this->metrics[] = $revenue;
            $this->processedMetrics[] = new Conversion($conversion, $model);
            $this->processedMetrics[] = new Revenue($revenue, $model);
        }

        if (isset($this->baseModel) && !empty($this->compareModels)) {
            foreach ($this->compareModels as $model) {
                $this->processedMetrics[] = new AttributionComparison(Metrics::SUM_CONVERSIONS, $this->baseModel, $model);
                $this->processedMetrics[] = new AttributionComparison(Metrics::SUM_REVENUE, $this->baseModel, $model);
            }
        }

        $this->dimension = new ChannelType();
        $this->name = Piwik::translate('MultiChannelConversionAttribution_MultiChannelConversionAttribution');
        $this->actionToLoadSubTables = $this->action;
        $this->order = 100;
    }

    private function requestsSubtable()
    {
        return Common::getRequestVar('idSubtable', 0, 'int') > 0;
    }

    public function configureView(ViewDataTable $view)
    {
        // in case $_GET is set manually in widget, and the report instance was created before, we make sure it will be loaded
        $this->init();

        if (!empty($this->baseModel)) {
            $models = $this->compareModels;
            array_unshift($models, $this->baseModel);
        } else {
            $models = array();
        }

        $view->config->addTranslation('label', $this->dimension->getName());

        $view->config->columns_to_display = array('label');
        foreach ($models as $model) {
            $view->config->columns_to_display[] = Metrics::completeAttributionMetric(Metrics::SUM_CONVERSIONS, $model);
            $view->config->columns_to_display[] = Metrics::completeAttributionMetric(Metrics::SUM_REVENUE, $model);
        }

        if (empty($view->config->metrics_documentation)) {
            $view->config->metrics_documentation = array();
        }

        if (!empty($this->processedMetrics)) {
            foreach ($this->processedMetrics as $metric) {
                /** @var ProcessedMetric $metric */
                $name = $metric->getName();
                $view->config->addTranslation($name, $metric->getTranslatedName());
                $view->config->metrics_documentation[$name] = $metric->getDocumentation();
            }
        }

        $compareModels = $this->compareModels;
        $baseModel = $this->baseModel;

        $view->config->filters[] = function (DataTable $dataTable) use ($view, $compareModels, $baseModel) {
            if ($view->isViewDataTableId(HtmlTable::ID)) {
                $view->config->datatable_css_class = 'dataTableActions';
            }
            $metrics = array(Metrics::SUM_CONVERSIONS, Metrics::SUM_REVENUE);
            foreach ($dataTable->getRowsWithoutSummaryRow() as $row) {
                foreach ($compareModels as $compareModel) {
                    foreach ($metrics as $metric) {
                        $name = Metrics::completeAttributionMetric($metric, $compareModel);
                        $prefixName = 'html_column_' . $name .  '_suffix';
                        $value = $row->getColumn('comparison_' . $name);

                        if (!$value) {
                            $value = 0;
                        } else {
                            $value = $value * 100;
                            $value = round($value, 1);
                        }

                        if ($value < 0) {
                            $class = 'negativeEvolution';
                        } elseif ($value > 0) {
                            $class = 'positiveEvolution';
                        } else {
                            $class = 'positiveEvolution';
                            $value = 0;
                        }

                        if (isset($view->config->translations[$name])) {
                            $metricName = $view->config->translations[$name];
                        } else {
                            $metricName = $metric;
                        }

                        $title = Piwik::translate('MultiChannelConversionAttribution_XInComparisonToTooltip', array($metricName, $compareModel->getName(), $baseModel->getName()));
                        $value = '<span title="' . $title . '" class="' . $class . '" style="margin-left: 8px"> ' . $value . '%</span>';

                        $row->setMetadata($prefixName, $value);
                    }

                }
            }
        };
        $view->config->datatable_js_type = 'AttributionDataTable';
        $view->config->show_pagination_control = false;
        $view->config->show_offset_information = false;
        $view->config->show_insights = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_pie_chart = false;
        $view->config->show_bar_chart = false;
        $view->config->show_title = 0;
        $view->config->show_limit_control = true;
        $view->config->show_search = true;
        $view->config->search_recursive = true;
        $view->config->show_all_views_icons = false;

        $view->requestConfig->request_parameters_to_modify['idGoal'] = Common::getRequestVar('idGoal', 0, 'int');
        $view->requestConfig->request_parameters_to_modify['attributionModels'] = Common::getRequestVar('comparisonMetric', '', 'string');
        $view->config->custom_parameters['idGoal'] = $view->requestConfig->request_parameters_to_modify['idGoal'];
        $view->config->custom_parameters['attributionModels'] = $view->requestConfig->request_parameters_to_modify['attributionModels'];

        $view->config->subtable_controller_action = $this->actionToLoadSubTables;

        if ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->show_embedded_subtable = true;

            if ($this->requestsSubtable()) {
                $view->config->disable_row_evolution = true; // couldn't make it work..
            }

            if (Request::shouldLoadExpanded()) {
                $view->config->show_expanded = true;
            }
        }
    }

    public static function isRequestingRowEvolutionPopover()
    {
        return Common::getRequestVar('action', '', 'string') === 'getRowEvolutionPopover';
    }

    public static function isRequestingGlossary()
    {
        return Common::getRequestVar('action', '', 'string') === 'glossary'
            && Common::getRequestVar('module', '', 'string') === 'API';
    }

    public function getMetricNamesToProcessReportTotals()
    {
        $metrics = array();
        foreach ($this->metrics as $metric) {
            if (strpos($metric, Metrics::SUM_CONVERSIONS) !== false) {
                $metrics[] = $metric;
            }
        }
        return $metrics;
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        // we do only want to make it work for row evolution.
        if (self::isRequestingRowEvolutionPopover() || self::isRequestingGlossary()) {
            $idGoal = Common::getRequestVar('idGoal', -2, 'int');
            if ($idGoal !== -2) {
                $this->parameters = array('idGoal' => $idGoal);
            }

            parent::configureReportMetadata($availableReports, $infos);

            $this->parameters = array();
        }
    }

    public function render()
    {
        $rendered = parent::render();

        if (!empty($this->baseModel) && !$this->requestsSubtable()) {
            $headersToDisplay = array($this->baseModel->getName());
            $documentations = array($this->baseModel->getDocumentation());
            foreach ($this->compareModels as $model) {
                $headersToDisplay[] = $model->getName();
                $documentations[] = $model->getDocumentation();
            }

            $view = new View('@MultiChannelConversionAttribution/tableHeader');
            $view->headersToDisplay = $headersToDisplay;
            $view->documentations = $documentations;
            $row = $view->render();
            $rendered = str_replace('<thead>', '<thead>' . $row, $rendered);
        }

        return $rendered;
    }

}
