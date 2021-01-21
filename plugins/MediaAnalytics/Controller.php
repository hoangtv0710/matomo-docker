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

namespace Piwik\Plugins\MediaAnalytics;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\DataTable\Renderer\Json;
use Piwik\FrontController;
use Piwik\Http;
use Piwik\Piwik;
use Piwik\Plugin\ReportsProvider;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Bar;
use Piwik\Plugins\MediaAnalytics\Dao\LogMediaPlays;
use Piwik\Plugins\MediaAnalytics\Visualizations\MediaEvolution;
use Piwik\Plugins\MediaAnalytics\Dao\LogTable;
use Piwik\Plugins\MediaAnalytics\Reports\Base;
use Piwik\Plugins\MediaAnalytics\Widgets\BaseWidget;
use Piwik\SettingsPiwik;
use Piwik\ViewDataTable;
use Piwik\Translation\Translator;

class Controller extends \Piwik\Plugin\Controller
{
    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var LogTable
     */
    private $logTable;

    public function __construct(Translator $translator, LogTable $logTable)
    {
        parent::__construct();

        $this->translator = $translator;
        $this->logTable = $logTable;
    }

    public function detail()
    {
        $this->checkSitePermission();

        Common::getRequestVar('idSubtable', null, 'int');
        $reportAction = Common::getRequestVar('reportAction', null, 'string');

        /** @var Base $report */
        $report = ReportsProvider::factory('MediaAnalytics', $reportAction);

        if (empty($report)) {
            throw new \Exception('This report does not exist');
        }

        $dimensionName = $report->getDimension()->getName();
        $isAudioReport = $report->isAudioReport();

        return $this->renderTemplate('detail', array(
            'title' => $dimensionName,
            'watchedSegmentsTitle' => 'MediaAnalytics_PlayedSegments',
            'watchedTimeTitle' => $isAudioReport ? 'MediaAnalytics_TimeSpentListening' : 'MediaAnalytics_TimeSpentWatching',
            'watchedProgressTitle' => $isAudioReport ? 'MediaAnalytics_MediaProgressTitleAudio' : 'MediaAnalytics_MediaProgressTitleVideo',
            'mediaSegments' => $this->renderMediaSegmentEvolutionGraph($reportAction, Archiver::SECONDARY_DIMENSION_MEDIA_SEGMENTS),
            'watchedTime' => $this->renderBarGraphSecondaryDimensionReport($reportAction, Archiver::SECONDARY_DIMENSION_SPENT_TIME),
            'watchedProgress' => $this->renderBarGraphSecondaryDimensionReport($reportAction, Archiver::SECONDARY_DIMENSION_MEDIA_PROGRESS),
            'hoursTitle' => $isAudioReport ? 'MediaAnalytics_AudioHours' : 'MediaAnalytics_VideoHours',
            'hours' => $this->renderTableSecondaryDimensionReport($reportAction, Archiver::SECONDARY_DIMENSION_HOURS, $isAudioReport),
            'resolutions' => $this->renderTableSecondaryDimensionReport($reportAction, Archiver::SECONDARY_DIMENSION_RESOLUTION, $isAudioReport),
        ));
    }

    public function getAudienceLog($fetch = false)
    {
        $this->checkSitePermission();
        // it is not a widget (yet) because we need to forward $fetch

        $queryBackup = $_SERVER['QUERY_STRING'];

        $saveGET = $_GET;
        $_GET['segment'] = urldecode(BaseWidget::getMediaSegment());
        $_GET['widget'] = 1;
        $_SERVER['QUERY_STRING'] = Http::buildQuery($_GET); // used eg in https://github.com/piwik/piwik/blob/3.0.4/core/API/Request.php#L408-L415

        $output = FrontController::getInstance()->dispatch('Live', 'getVisitorLog', array($fetch));

        $_GET = $saveGET;
        $_SERVER['QUERY_STRING'] = $queryBackup;

        return $output;
    }

    private function renderMediaSegmentEvolutionGraph($reportAction, $secondarydimension)
    {
        $this->checkSitePermission();

        $idSubtable = Common::getRequestVar('idSubtable', null, 'int');

        $selectableColumns = array(Metrics::METRIC_PLAY_RATE);

        $view = ViewDataTable\Factory::build(MediaEvolution::ID, 'MediaAnalytics.' . $reportAction, '', true);
        $view->config->columns_to_display = $selectableColumns;
        $view->requestConfig->filter_sort_column = 'label';
        $view->requestConfig->filter_sort_order = 'asc';
        $view->config->y_axis_unit = '';
        $view->config->hide_annotations_view = true;
        $view->config->show_footer = true;
        $view->config->show_footer_icons = true;
        $view->config->show_search = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_export_as_image_icon = false; // disabled cause it opens a popover over the other popover and doesn't look too nice
        $view->config->show_bar_chart = false;
        $view->config->show_pie_chart = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_limit_control = false;
        $view->config->show_related_reports = false;
        $view->config->show_pagination_control = false;
        $view->config->show_offset_information = false;
        $view->config->show_export = true;
        $view->requestConfig->request_parameters_to_modify['idSubtable'] = $idSubtable;
        $view->config->show_series_picker = false;
        $view->config->allow_multi_select_series_picker = false;
        $view->config->translations[Metrics::METRIC_NB_PLAYS] = $this->translator->translate('MediaAnalytics_ColumnPlays');
        $view->config->translations[Metrics::METRIC_PLAY_RATE] = $this->translator->translate('MediaAnalytics_ColumnPlayRate');
        $view->config->selectable_columns = $selectableColumns;
        $view->requestConfig->request_parameters_to_modify['secondaryDimension'] = $secondarydimension;

        return $this->renderView($view);
    }

    private function renderBarGraphSecondaryDimensionReport($reportAction, $secondarydimension)
    {
        $maxGraphElements = 500;
        if ($secondarydimension === Archiver::SECONDARY_DIMENSION_MEDIA_PROGRESS) {
            $maxGraphElements = 101; // we show up to one entry per percentage from 0...100
        } elseif ($secondarydimension === Archiver::SECONDARY_DIMENSION_MEDIA_SEGMENTS) {
            $segments = LogMediaPlays::getSegments();
            $maxGraphElements = count($segments);
        }

        $view = ViewDataTable\Factory::build(Bar::ID, 'MediaAnalytics.' . $reportAction, '', true);
        $view->requestConfig->filter_sort_column = 'label';
        $view->requestConfig->filter_sort_order = 'asc';
        $view->config->y_axis_unit = '';
        $view->config->show_footer = true;
        $view->config->show_footer_icons = true;
        $view->config->show_search = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_export_as_image_icon = false; // disabled cause it opens a popover over the other popover and doesn't look too nice
        $view->config->show_bar_chart = false;
        $view->config->show_pie_chart = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_limit_control = false;
        $view->config->show_related_reports = false;
        $view->config->show_pagination_control = false;
        $view->config->show_offset_information = false;
        $view->config->show_export = true;
        $view->requestConfig->request_parameters_to_modify['idSubtable'] = Common::getRequestVar('idSubtable', null, 'int');
        $view->config->show_all_ticks = true;
        $view->config->show_series_picker = false;
        $view->config->allow_multi_select_series_picker = false;
        $view->config->translations[Metrics::METRIC_NB_PLAYS] = $this->translator->translate('MediaAnalytics_ColumnPlays');
        $view->config->translations[Metrics::METRIC_PLAY_RATE] = $this->translator->translate('MediaAnalytics_ColumnPlayRate');
        $view->config->selectable_columns = array(Metrics::METRIC_NB_PLAYS);
        $view->config->max_graph_elements = $maxGraphElements;
        $view->requestConfig->request_parameters_to_modify['secondaryDimension'] = $secondarydimension;

        return $view->render();
    }

    private function renderTableSecondaryDimensionReport($reportAction, $secondarydimension, $isAudioReport)
    {
        if (Archiver::SECONDARY_DIMENSION_RESOLUTION === $secondarydimension
            && $isAudioReport) {
            // we do not render resolutions table for audio...
            return;
        }

        $view = ViewDataTable\Factory::build(HtmlTable::ID, 'MediaAnalytics.' . $reportAction, '', true);
        $view->config->show_footer = true;
        $view->config->show_footer_icons = true;
        $view->config->show_search = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_bar_chart = false;
        $view->config->show_pie_chart = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_limit_control = false;
        $view->config->show_related_reports = false;
        $view->config->show_pagination_control = false;
        $view->config->show_offset_information = false;
        $view->config->show_export = true;
        $view->requestConfig->request_parameters_to_modify['idSubtable'] = Common::getRequestVar('idSubtable', null, 'int');
        $view->requestConfig->request_parameters_to_modify['secondaryDimension'] = $secondarydimension;

        return $view->render();
    }

    public function hasRecords()
    {
        return $this->sendHasRecords(false);
    }

    public function hasNoRecords()
    {
        return $this->sendHasRecords(true);
    }

    private function sendHasRecords($invert)
    {
        $this->checkSitePermission();

        $idSite = Common::getRequestVar('idSite', null, 'int');

        Piwik::checkUserHasViewAccess($idSite);

        Json::sendHeaderJSON();

        $hasRecords = $this->hasRecordsApi();

        if ($invert) {
            $hasRecords = !$hasRecords;
        }

        return json_encode($hasRecords);
    }

    public function getEvolutionGraph()
    {
        $this->checkSitePermission();

        if (empty($columns)) {
            $columns = Common::getRequestVar('columns', false);
            if (false !== $columns) {
                $columns = Piwik::getArrayFromApiParameter($columns);
            }
        }

        $report = ReportsProvider::factory('MediaAnalytics', 'get');
        $documentation = $report->getDocumentation();

        $selectableColumns = $report->getMetricsRequiredForReport(null, null);
        $selectableColumns[] = Metrics::METRIC_PLAY_RATE;
        $selectableColumns[] = Metrics::METRIC_FINISH_RATE;

        $period = Common::getRequestVar('period', '', 'string');
        if (SettingsPiwik::isUniqueVisitorsEnabled($period) && Archiver::isUniqueVisitorsEnabled($period)) {
            $selectableColumns[] = Metrics::METRIC_IMPRESSION_RATE;
        }

        $key = array_search(Metrics::METRIC_NB_UNIQUE_VISITORS, $selectableColumns);
        if ($key !== false) {
            array_splice($selectableColumns, $key, 1);
        }

        // $callingAction may be specified to distinguish between
        // "VisitsSummary_WidgetLastVisits" and "VisitsSummary_WidgetOverviewGraph"
        $view = $this->getLastUnitGraphAcrossPlugins($this->pluginName, __FUNCTION__, $columns,
            $selectableColumns, $documentation);

        if (empty($view->config->columns_to_display) && !empty($defaultColumns)) {
            $view->config->columns_to_display = $defaultColumns;
        }

        return $this->renderView($view);
    }

    private function hasRecordsApi()
    {
        return Request::processRequest('MediaAnalytics.hasRecords', [
            'idSite' => $this->idSite,
        ], $default = []);
    }
}
