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

namespace Piwik\Plugins\AbTesting\Reports;

use Piwik\Access;
use Piwik\API\Request;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\AbTesting\Columns\Variation;
use Piwik\Plugins\AbTesting\Dao\Experiment;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;


/**
 * Shows details for a specific success metric such as the number of remaining needed visitors,
 * statistical significance, detected effect etc.
 */
class GetMetricDetails extends Base
{
    protected function init()
    {
        parent::init();

        $this->name          = Piwik::translate('AbTesting_SuccessMetricDetails');
        $this->dimension     = new Variation();
        $this->documentation = Piwik::translate('');

        // This defines in which order your report appears in the mobile app, in the menu and in the list of widgets
        $this->order = 1;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (empty($idSite)) {
            return;
        }

        $experiments = $this->getExperimentsWithReports($idSite);

        $metrics = StaticContainer::get('Piwik\Plugins\AbTesting\Metrics');
        $availableMetrics = $metrics->getMetricOverviewTranslations($idSite);

        foreach ($experiments as $experiment) {
            $order = 15;
            foreach ($experiment['success_metrics'] as $successMetrics) {
                if (empty($successMetrics['metric'])) {
                    continue;
                }
                $metric = $successMetrics['metric'];
                $reportTitle = !empty($availableMetrics[$metric]) ? $availableMetrics[$metric] : $metric;

                $widgetsList->addWidgetConfig(
                    $factory->createWidget()
                        ->setName($reportTitle)
                        ->setIsNotWidgetizable()
                        ->setIsWide()
                        ->setSubcategoryId($experiment['idexperiment'])
                        ->forceViewDataTable(HtmlTable::ID)
                        ->setParameters(array('idExperiment' => $experiment['idexperiment'], 'successMetric' => $metric))
                        ->setOrder($order)
                );

                $order += 2;
            }
        }
    }

    /**
     * Here you can configure how your report should be displayed. For instance whether your report supports a search
     * etc. You can also change the default request config. For instance change how many rows are displayed by default.
     *
     * @param ViewDataTable $view
     */
    public function configureView(ViewDataTable $view)
    {
        if (!empty($this->dimension)) {
            $view->config->addTranslations(array('label' => $this->dimension->getName()));
        }

        $successMetric = Common::getRequestVar('successMetric', null, 'string');
        $idExperiment = Common::getRequestVar('idExperiment', null, 'int');
        $idSite = Common::getRequestVar('idSite', null, 'int');

        $view->requestConfig->request_parameters_to_modify['idExperiment'] = $idExperiment;
        $view->requestConfig->request_parameters_to_modify['successMetric'] = $successMetric;

        $view->config->datatable_js_type = 'AbTestDataTable';
        $view->config->show_search = false;
        $view->config->enable_sort = false;
        $view->config->show_pagination_control = false;
        $view->config->show_offset_information = false;
        $view->config->show_limit_control = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_pie_chart = false;
        $view->config->show_bar_chart = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_goals = false;
        $view->config->show_ecommerce = false;
        $view->config->show_all_views_icons = false;

        if (property_exists($view->config, 'show_totals_row')) {
            // since Matomo 3.7 for htmltables
            $view->config->show_totals_row = false;
        }

        $view->requestConfig->filter_sort_column = 'label';
        $view->config->footer_icons = array();

        if ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->disable_row_evolution = true;
        }

        $dao = new Experiment();
        $experiment = $dao->getExperiment($idExperiment, $idSite);

        $params = array($view, $experiment);
        $view->config->filters[] = array('Piwik\Plugins\AbTesting\DataTable\Filter\Conclusion', $params, $priority = false);

        $metrics = StaticContainer::get('Piwik\Plugins\AbTesting\Metrics');

        $view->config->columns_to_display = $metrics->getMetricDetailNames($successMetric);
        $view->config->addTranslations($metrics->getMetricDetailTranslations($idSite, $successMetric));
        $view->config->metrics_documentation = array_merge($view->config->metrics_documentation, $metrics->getMetricDocumentations());
    }

    protected function buildReportMetadata()
    {
        return;
    }

    /**
     * Here you can define related reports that will be shown below the reports. Just return an array of related
     * report instances if there are any.
     *
     * @return \Piwik\Plugin\Report[]
     */
    public function getRelatedReports()
    {
        return array(); // eg return array(new XyzReport());
    }

    public function getDefaultTypeViewDataTable()
    {
        return HtmlTable::ID;
    }

    private function getExperimentsWithReports($idSite)
    {
        return Request::processRequest('AbTesting.getExperimentsWithReports', ['idSite' => $idSite, 'filter_limit' => -1], $default = []);
    }

}
