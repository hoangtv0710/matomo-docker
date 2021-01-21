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

namespace Piwik\Plugins\AbTesting;

use Piwik\Access;
use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Period;
use Piwik\Plugin\ReportsProvider;
use Piwik\Plugins\AbTesting\Input\AccessValidator;
use Piwik\Plugins\AbTesting\Model\Experiments;
use Exception;
use Piwik\Version;

class Controller extends \Piwik\Plugin\Controller
{
    /**
     * @var Metrics
     */
    private $metrics;

    /**
     * @var Metrics
     */
    private $experiments;

    /**
     * @var AccessValidator
     */
    private $access;

    public function __construct(Metrics $metrics, Experiments $experiments, AccessValidator $accessValidator)
    {
        parent::__construct();
        $this->metrics = $metrics;
        $this->experiments = $experiments;
        $this->access = $accessValidator;
    }

    public function manage()
    {
        $idSite = Common::getRequestVar('idSite');

        if (strtolower($idSite) === 'all') {
            // prevent fatal error... redirect to a specific site as it is not possible to manage for all sites
            $this->access->checkHasSomeWritePermission();
            $this->redirectToIndex('AbTesting', 'manage');
            exit;
        }

        $this->access->checkWritePermission($idSite);

        return $this->renderTemplate('manage', array('title' => Piwik::translate('AbTesting_Experiments')));
    }

    public function getMetricsOverview()
    {
        $this->checkSitePermission();
        $experiment = $this->initExperimentView();

        $report = ReportsProvider::factory($this->pluginName, 'getMetricsOverview');
        return $report->render();
    }

    public function getMetricDetails()
    {
        $this->checkSitePermission();
        $experiment = $this->initExperimentView();

        $report = ReportsProvider::factory($this->pluginName, 'getMetricDetails');
        return $report->render();
    }

    public function summary()
    {
        $this->access->checkReportViewPermission($this->idSite);

        $experiment = $this->initExperimentView();
        $isAdmin = $this->access->canWrite($this->idSite);

        $readable = Period\Factory::build($_GET['period'], $_GET['date']);
        $readablePeriod = $readable->getPrettyString();

        return $this->renderTemplate('summary', array(
            'experiment' => $experiment,
            'isAdmin' => $isAdmin,
            'readablePeriod' => $readablePeriod
        ));
    }

    public function getEvolutionGraph($variationName = false, array $columns = array(), array $defaultColumns = array())
    {
        $this->checkSitePermission();

        if (empty($columns)) {
            $columns = Common::getRequestVar('columns', false);
            if (false !== $columns) {
                $columns = Piwik::getArrayFromApiParameter($columns);
            }
        }

        $experiment = $this->initExperimentView();

        $view = $this->getLastUnitGraph($this->pluginName, __FUNCTION__, 'AbTesting.getMetricsOverview');

        if (false !== $columns) {
            $columns = !is_array($columns) ? array($columns) : $columns;
        }

        if (!empty($columns)) {
            $view->config->columns_to_display = $columns;
        } elseif (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
            $view->config->columns_to_display = $defaultColumns;
        }

        $selectable = $this->metrics->getMetricOverviewNames($experiment['success_metrics']);
        $index = array_search('label', $selectable);
        if (false !== $index) {
            unset($selectable[$index]);
        }

        $view->config->selectable_columns = $selectable;

        // configure displayed rows
        $visibleRows = Common::getRequestVar('rows', false);
        if ($visibleRows !== false) {
            // this happens when the row picker has been used
            $visibleRows = Piwik::getArrayFromApiParameter($visibleRows);
            if (version_compare(Version::VERSION, '3.9.0-b1', '>=')) {
                $visibleRows = array_map('urldecode', $visibleRows);
            }

            // typeReferrer is redundant if rows are defined, so make sure it's not used
            $view->config->custom_parameters['typeReferrer'] = false;
        } else {
            // use $typeReferrer as default
            if ($variationName === false) {
                $variationName = Common::getRequestVar('variationName', false, 'string');
            }
            $label = $variationName;
            $total = Piwik::translate('General_Total');

            if (!empty($view->config->rows_to_display)) {
                $visibleRows = $view->config->rows_to_display;
            } else {
                $visibleRows = array($label);
            }

            $view->requestConfig->request_parameters_to_modify['rows'] = $label . ',' . $total;
        }
        $view->config->row_picker_match_rows_by = 'label';
        $view->config->rows_to_display = $visibleRows;
        $view->config->documentation = Piwik::translate('General_EvolutionOverPeriod');

        return $this->renderView($view);
    }

    private function initExperimentView()
    {
        $idExperiment = Common::getRequestVar('idExperiment', null, 'int');

        $experiment = $this->getExperiment($idExperiment, $this->idSite);

        if (empty($experiment)) {
            throw new Exception(Piwik::translate('AbTesting_ErrorExperimentDoesNotExist'));
        }

        if (empty($_GET['useDateUrl']) && !empty($experiment['date_range_string'])) {
            $parts = explode(',', $experiment['date_range_string']);

            if (count($parts) === 2 && isset($parts[0]) && isset($parts[1]) && $parts[0] === $parts[1]) {
                $_GET['period'] = 'day';
                $_GET['date'] = $parts[0];
            } else {
                $_GET['period'] = 'range';
                $_GET['date'] = $experiment['date_range_string'];
            }
        }

        $_GET['disableLink'] = '1';

        return $experiment;
    }

    private function getExperiment($idExperiment, $idSite)
    {
        return Request::processRequest('AbTesting.getExperiment', [
            'idExperiment' => $idExperiment,
            'idSite' => $idSite,
        ], $default = []);
    }
}
