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
 * @link    https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

namespace Piwik\Plugins\SearchEngineKeywordsPerformance;

use Piwik\API\Request;
use Piwik\Cache;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\Referrers;
use Piwik\Plugins\Referrers\SearchEngine;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Google as GoogleModel;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Bing as BingModel;
use Piwik\Plugins\WebsiteMeasurable\Type as WebsiteMeasurableType;
use Piwik\Site;
use Piwik\Version;
use Piwik\View;

class SearchEngineKeywordsPerformance extends \Piwik\Plugin
{
    const REQUEST_PARAM_ORIGINAL_REPORT = '__preventReplace';

    /**
     * @see \Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        $viewConfigureEvent = 'ViewDataTable.configure';
        if (version_compare(Version::VERSION, '3.10.0-b1', '>=')) {
            $viewConfigureEvent = 'ViewDataTable.configure.end';
        }

        return [
            'AssetManager.getStylesheetFiles'                   => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles'                   => 'getJSFiles',
            'Metrics.getDefaultMetricDocumentationTranslations' => 'addMetricDocumentationTranslations',
            'Metrics.getDefaultMetricTranslations'              => 'addMetricTranslations',
            'Metrics.isLowerValueBetter'                        => 'checkIsLowerMetricValueBetter',
            $viewConfigureEvent                                 => 'configureViewDataTable',
            'Translate.getClientSideTranslationKeys'            => 'getClientSideTranslationKeys',
            'Report.filterReports'                              => 'manipulateReports',
            'API.Request.intercept'                             => 'manipulateApiRequests',
            'API.Referrers.getAll'                              => 'manipulateAllReferrersReport',
            'API.Referrers.getSearchEngines'                    => 'manipulateSearchEnginesReportParameters',
            'API.Referrers.getSearchEngines.end'                => 'manipulateSearchEnginesReport',
            'API.Referrers.getKeywordsFromSearchEngineId.end'   => 'manipulateSearchEnginesKeywordsReport',
            'Request.dispatch'                                  => 'manipulateRequests'
        ];
    }

    /**
     * Remove `GetKeywords` report of `Referrers` plugin, as it will be replaced with an inherited one
     *
     * @see \Piwik\Plugins\SearchEngineKeywordsPerformance\Reports\GetKeywordsReferrers
     *
     * @param $reports
     */
    public function manipulateReports(&$reports)
    {
        $reportsToUnset = [
            '\Piwik\Plugins\Referrers\Reports\GetKeywords',
        ];

        $report = new \Piwik\Plugins\SearchEngineKeywordsPerformance\Reports\GetKeywords();
        if (!$report->isBingEnabled() && !$report->isAnyGoogleTypeEnabled()) {
            $reportsToUnset = [
                '\Piwik\Plugins\SearchEngineKeywordsPerformance\Reports\GetKeywords',
                '\Piwik\Plugins\SearchEngineKeywordsPerformance\Reports\GetKeywordsReferrers',
            ];
        }

        foreach ($reportsToUnset as $unset) {
            foreach ($reports as $key => $report) {
                if ($report instanceof $unset) {
                    unset($reports[$key]);
                    break;
                }
            }
        }
    }

    /**
     * Manipulate some request so replaced reports look nice
     *
     * @param $module
     * @param $action
     * @param $parameters
     */
    public function manipulateRequests(&$module, &$action, &$parameters)
    {
        # Search Engines subtable of Channel Type report is replaced with combined keywords report
        # as combined keywords report only has visits column, ensure to always use simple table view
        if ($module === 'Referrers' && $action === 'getReferrerType'
            && Common::REFERRER_TYPE_SEARCH_ENGINE == Common::getRequestVar('idSubtable', '')
            && 'tableAllColumns' == Common::getRequestVar('viewDataTable', '')
            && !$this->shouldShowOriginalReports()) {

            $_GET['viewDataTable'] = 'table';
        }

        # Keywords subtable of Search Engines report for configured providers are replaced
        # as those reports only has visits column, ensure to always use simple table view
        # also disable row evolution as it can't work
        if ('Referrers' === $module && 'getKeywordsFromSearchEngineId' === $action && !$this->shouldShowOriginalReports()) {
            /** @var DataTable $dataTable */
            $dataTable = Request::processRequest('Referrers.getSearchEngines', ['idSubtable' => null, 'filter_limit' => -1, 'filter_offset' => 0]);
            $row       = $dataTable->getRowFromIdSubDataTable(Common::getRequestVar('idSubtable', ''));
            if ($row) {
                $label  = $row->getColumn('label');
                $report = new \Piwik\Plugins\SearchEngineKeywordsPerformance\Reports\GetKeywords();
                if (strpos($label, 'Google') !== false && $report->isAnyGoogleTypeEnabled()) {
                    $_GET['viewDataTable']         = 'table';
                    $_GET['disable_row_evolution'] = 1;
                } elseif ((strpos($label, 'Bing') !== false || strpos($label,
                            'Yahoo') !== false) && $report->isBingEnabled()) {
                    $_GET['viewDataTable']         = 'table';
                    $_GET['disable_row_evolution'] = 1;
                }
            }
        }
    }

    /**
     * Manipulate some api requests to replace the result
     *
     * @param $returnedValue
     * @param $finalParameters
     * @param $pluginName
     * @param $methodName
     * @param $parametersRequest
     */
    public function manipulateApiRequests(
        &$returnedValue,
        $finalParameters,
        $pluginName,
        $methodName,
        $parametersRequest = []
    ) {
        $report = new \Piwik\Plugins\SearchEngineKeywordsPerformance\Reports\GetKeywords();

        # Replace Search Engines subtable of Channel Type report combined keywords report if any import is configured
        if ('Referrers' === $pluginName && 'getReferrerType' === $methodName
            && !empty($finalParameters['idSubtable']) && Common::REFERRER_TYPE_SEARCH_ENGINE == $finalParameters['idSubtable']) {

            if (!$report->isAnyGoogleTypeEnabled() && !$report->isBingEnabled()) {
                return;
            }

            # leave report untouched if original report should be shown
            if ($this->shouldShowOriginalReports()) {
                return;
            }

            $returnedValue = Request::processRequest(
                'SearchEngineKeywordsPerformance.getKeywords',
                ['idSubtable' => null, 'segment' => null],
                $finalParameters
            );
            $returnedValue->filter('ColumnCallbackAddMetadata', ['label', 'imported', function(){return true;}]);
            return;
        }

        #
        # If a import is configured manipulate subtable requests for aggregated engine rows to show imported keywords
        # (@see manipulateSearchEnginesReport())
        #
        if ('Referrers' === $pluginName && 'getKeywordsFromSearchEngineId' === $methodName) {
            /** @var DataTable $dataTable */
            $dataTable = Request::processRequest(
                'Referrers.getSearchEngines',
                [
                    'idSubtable'                        => null,
                    self::REQUEST_PARAM_ORIGINAL_REPORT => 1,
                    // needs to be loaded unexpanded as otherwise the row can't be found using the subtableid
                    'expanded'                          => 0
                ],
                $finalParameters
            );

            $row = $dataTable->getRowFromIdSubDataTable($finalParameters['idSubtable']);

            if ($row) {
                $label = $row->getColumn('label');

                if ($this->shouldShowOriginalReports()) {
                    // load report with subtables
                    $dataTable = Request::processRequest(
                        'Referrers.getSearchEngines',
                        [
                            'idSubtable'                        => null,
                            self::REQUEST_PARAM_ORIGINAL_REPORT => 1,
                            // needs to be loaded expanded so we can merge the subtables to get them aggregated
                            'expanded'                          => 1
                        ],
                        $finalParameters
                    );
                }

                # If requesting a Google subtable and import is configured
                if (strpos($label, 'Google') !== false && $report->isAnyGoogleTypeEnabled()) {

                    # To show proper 'original' data for the aggregated row we need to combine all subtables of the aggregated rows
                    if ($this->shouldShowOriginalReports()) {

                        # remove all rows where label does not contain Google
                        $dataTable->disableRecursiveFilters();
                        $dataTable->filter('ColumnCallbackDeleteRow', [
                            'label',
                            function ($label) {
                                return false === strpos($label, 'Google');
                            }
                        ]);

                        # combine subtables and group labels to get correct result
                        $returnedValue = $dataTable->mergeSubtables();
                        $returnedValue->filter('GroupBy', ['label']);
                        $returnedValue->filter('Piwik\Plugins\Referrers\DataTable\Filter\KeywordNotDefined');
                        return;
                    }

                    # Return combined Google keywords
                    $returnedValue = Request::processRequest('SearchEngineKeywordsPerformance.getKeywordsGoogle',
                        ['idSubtable' => null, 'segment' => null, 'filter_sort_column' => 'nb_clicks',  'filter_limit' => null]);

                }
                # If requesting a Bing/Yahoo subtable and import is configured
                elseif (
                    (strpos($label, 'Bing') !== false || strpos($label, 'Yahoo') !== false)
                    && $report->isBingEnabled()
                ) {

                    # To show proper 'original' data for the aggregated row we need to combine all subtables of the aggregated rows
                    if ($this->shouldShowOriginalReports()) {

                        # remove all rows where label does not contain Bing or Yahoo
                        $dataTable->disableRecursiveFilters();
                        $dataTable->filter('ColumnCallbackDeleteRow', [
                            'label',
                            function ($label) {
                                return false === strpos($label, 'Bing') && false === strpos($label, 'Yahoo');
                            }
                        ]);

                        # combine subtables and group labels to get correct result
                        $returnedValue = $dataTable->mergeSubtables();
                        $returnedValue->filter('GroupBy', ['label']);
                        $returnedValue->filter('Piwik\Plugins\Referrers\DataTable\Filter\KeywordNotDefined');
                        return;
                    }

                    # Return combined Bing & Yahoo! keywords
                    $returnedValue = Request::processRequest('SearchEngineKeywordsPerformance.getKeywordsBing',
                        ['idSubtable' => null, 'segment' => null, 'filter_sort_column' => 'nb_clicks', 'filter_limit' => null]);
                }
            }

            # Adjust table so it only shows visits
            $this->convertDataTableColumns($returnedValue, $dataTable);
        }
    }

    public function manipulateAllReferrersReport(&$finalParameters)
    {
        # unset segment if default report is shown, as default `Referrers` report does not support segmentation
        # as the imported keywords are hooked into the search engines subtable of `getReferrerType` report
        if (!empty($finalParameters['segment']) && !$this->shouldShowOriginalReports()) {
            $finalParameters['segment'] = false;
        }
    }

    public function manipulateSearchEnginesReportParameters(&$finalParameters)
    {
        # unset segment if default report is shown flattened, as `Search Engines` subtable reports do not support segmentation
        # as the imported keywords are hooked into the search engines subtable of `getReferrerType` report
        if (!empty($finalParameters['segment']) && !empty($finalParameters['flat']) && !$this->shouldShowOriginalReports()) {
            $finalParameters['segment'] = false;
        }
    }

    public function manipulateSearchEnginesReport(&$returnedValue, $finalParameters)
    {
        # prevent replace for internal proposes
        if ($this->shouldShowOriginalReports()) {
            return;
        }

        $report        = new \Piwik\Plugins\SearchEngineKeywordsPerformance\Reports\GetKeywords();
        $bingEnabled   = $report->isBingEnabled();
        $googleEnabled = $report->isAnyGoogleTypeEnabled();

        if (!($returnedValue instanceof DataTable\DataTableInterface) || (!$bingEnabled && !$googleEnabled)) {
            return;
        }

        #
        # If any import is configured, rows of the `getSearchEngines` report will be aggregated into one row per imported engine
        # e.g. `Google`, `Google Images`, and so on will be aggregated into a `Google` row. Same for Bing & Yahoo
        #

        $returnedValue->filter(
            'GroupBy',
            [
                'label',
                function ($label) use ($bingEnabled, $googleEnabled) {
                    if ($bingEnabled && (strpos($label, 'Yahoo') !== false || strpos($label, 'Bing') !== false)) {
                        return 'Bing & Yahoo!';
                    } else {
                        if ($googleEnabled && strpos($label, 'Google') !== false) {
                            return 'Google';
                        }
                    }
                    return $label;
                }
            ]
        );

        if ($returnedValue instanceof DataTable\Map) {
            $tablesToFilter = $returnedValue->getDataTables();
        } else {
            $tablesToFilter = [$finalParameters['parameters']['date'] => $returnedValue];
        }

        // replace numbers for aggregated rows if no segment was applied
        if (empty($finalParameters['parameters']['segment']) && Common::getRequestVar('viewDataTable', '') !== 'tableGoals') {
            foreach ($tablesToFilter as $label => $table) {
                $table->filter(function (DataTable $dataTable) use ($googleEnabled, $bingEnabled, $label, $finalParameters) {
                    // label should hold the period representation
                    if ($finalParameters['parameters']['period'] != 'range') {

                        try {
                            // date representation might be year or year-month only, so fill it up to a full date and
                            // cut after 10 chars, as it might be too log now, or was a period representation before
                            $date = substr($label . '-01-01', 0, 10);

                            // if date is valid use it as label, otherwise original label will be used
                            // which is the case for e.g. `yesterday`
                            if (Date::factory($date)->toString() == $date) {
                                $label = $date;
                            }
                        } catch (\Exception $e) {
                        }

                    }

                    if ($googleEnabled) {
                        $row = $dataTable->getRowFromLabel('Google');

                        /** @var DataTable $subTable */
                        $subTable = Request::processRequest(
                            'SearchEngineKeywordsPerformance.getKeywordsGoogle',
                            [
                                'idSite'             => $finalParameters['parameters']['idSite'],
                                'date'               => $label,
                                'period'             => $finalParameters['parameters']['period'],
                            ],
                            []
                        );

                        $totalVisits = 0;

                        foreach ($subTable->getRowsWithoutSummaryRow() as $tableRow) {
                            $totalVisits += $tableRow->getColumn(Metrics::NB_CLICKS);
                        }

                        // replace subtable with processed data
                        if ($row && $row->getIdSubDataTable()) {
                            if ($row->isSubtableLoaded()) {
                                $row->setSubtable($this->convertDataTableColumns($subTable, $dataTable));
                            }
                        }

                        // add a new row if non exists yet and some data was imported
                        if ($totalVisits && !$row) {
                            $columns = [
                                'label'     => 'Google',
                                \Piwik\Metrics::INDEX_NB_VISITS => $totalVisits
                            ];

                            $row = new DataTable\Row([DataTable\Row::COLUMNS => $columns]);
                            $row->setMetadata('imported', true);
                            $dataTable->addRow($row);
                        }

                        $dataTable->deleteColumn(\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS);
                    }

                    if ($bingEnabled) {
                        $row = $dataTable->getRowFromLabel('Bing & Yahoo!');

                        /** @var DataTable $subTable */
                        $subTable = Request::processRequest('SearchEngineKeywordsPerformance.getKeywordsBing',
                            [
                                'idSite'             => $finalParameters['parameters']['idSite'],
                                'date'               => $label,
                                'period'             => $finalParameters['parameters']['period'],
                            ],
                            []
                        );

                        $totalVisits = 0;

                        foreach ($subTable->getRowsWithoutSummaryRow() as $tableRow) {
                            $totalVisits += $tableRow->getColumn(Metrics::NB_CLICKS);
                        }

                        // replace subtable with processed data
                        if ($row && $row->getIdSubDataTable()) {
                            if ($row->isSubtableLoaded()) {
                                $row->setSubtable($this->convertDataTableColumns($subTable, $dataTable));
                            }
                        }

                        // add a new row if non exists yet and some data was imported
                        if ($totalVisits && !$row) {
                            $columns = [
                                'label'     => 'Bing & Yahoo!',
                                \Piwik\Metrics::INDEX_NB_VISITS => $totalVisits
                            ];

                            $row = new DataTable\Row([DataTable\Row::COLUMNS => $columns]);
                            $row->setMetadata('imported', true);
                            $dataTable->addRow($row);
                        }

                        $dataTable->deleteColumn(\Piwik\Metrics::INDEX_NB_UNIQ_VISITORS);
                    }
                });
            }
        }

        if ($bingEnabled || $googleEnabled) {
            $returnedValue->queueFilter('Sort', [(reset($tablesToFilter)->getSortedByColumnName() ?: 'nb_visits')]);
        }

        // needs to be done as queued filter as metadata will fail otherwise
        if ($bingEnabled) {
            $returnedValue->queueFilter(function (DataTable $datatable) {

                $row = $datatable->getRowFromLabel('Bing & Yahoo!');
                if ($row) {
                    // rename column and fix metadata
                    $url = SearchEngine::getInstance()->getUrlFromName('Bing');
                    $row->setMetadata('url', $url);
                    $row->setMetadata('logo', SearchEngine::getInstance()->getLogoFromUrl($url));
                    $row->setMetadata('segment', 'referrerType==search;referrerName=@Bing,referrerName=@Yahoo');
                }
            });
        }

        if ($googleEnabled) {
            $returnedValue->queueFilter(function (DataTable $datatable) {

                $row = $datatable->getRowFromLabel('Google');
                if ($row) {
                    // rename column and fix metadata
                    $url = SearchEngine::getInstance()->getUrlFromName('Google');
                    $row->setMetadata('url', $url);
                    $row->setMetadata('logo', SearchEngine::getInstance()->getLogoFromUrl($url));
                    $row->setMetadata('segment', 'referrerType==search;referrerName=@Google');
                }
            });
        }
    }

    /**
     * Manipulates the segment metadata of the aggregated columns in search engines report
     * This needs to be done in `API.Referrers.getKeywordsFromSearchEngineId.end` event as the queued filters would
     * otherwise be applied to early and segment metadata would be overwritten again
     *
     * @param mixed $returnedValue
     * @param array $finalParameters
     */
    public function manipulateSearchEnginesKeywordsReport(&$returnedValue, $finalParameters)
    {
        # only manipulate the original report with aggregated columns
        if (!$this->shouldShowOriginalReports()) {
            return;
        }

        $report = new \Piwik\Plugins\SearchEngineKeywordsPerformance\Reports\GetKeywords();

        /** @var DataTable $dataTable */
        $dataTable = Request::processRequest(
            'Referrers.getSearchEngines',
            [
                'idSubtable'                        => null,
                self::REQUEST_PARAM_ORIGINAL_REPORT => 1,
                // needs to be loaded unexpanded as otherwise the row can't be found using the subtableid
                'expanded'                          => 0
            ]
        );

        $row = $dataTable->getRowFromIdSubDataTable($finalParameters['parameters']['idSubtable']);

        if ($row) {
            $label = $row->getColumn('label');

            # If requesting a Google subtable and import is configured
            if (strpos($label, 'Google') !== false && $report->isAnyGoogleTypeEnabled()) {
                $returnedValue->queueFilter('ColumnCallbackDeleteMetadata', ['segment']);
                $returnedValue->queueFilter('ColumnCallbackAddMetadata', [
                    'label',
                    'segment',
                    function ($label) {
                        if ($label == Referrers\API::getKeywordNotDefinedString()) {
                            return 'referrerKeyword==;referrerType==search;referrerName=@Google';
                        }
                        return 'referrerKeyword==' . urlencode($label) . ';referrerType==search;referrerName=@Google';
                    }
                ]);
            } elseif (
                (strpos($label, 'Bing') !== false || strpos($label, 'Yahoo') !== false)
                && $report->isBingEnabled()
            ) {
                $returnedValue->queueFilter('ColumnCallbackDeleteMetadata', ['segment']);
                $returnedValue->queueFilter('ColumnCallbackAddMetadata', [
                    'label',
                    'segment',
                    function ($label) {
                        if ($label == Referrers\API::getKeywordNotDefinedString()) {
                            return 'referrerKeyword==;referrerType==search;referrerName=@Bing,referrerName=@Yahoo';
                        }
                        return 'referrerKeyword==' . urlencode($label) . ';referrerType==search;referrerName=@Bing,referrerName=@Yahoo';
                    }
                ]);
            }
        }
    }

    public function configureViewDataTable(ViewDataTable $viewDataTable)
    {
        $report        = new \Piwik\Plugins\SearchEngineKeywordsPerformance\Reports\GetKeywords();
        $bingEnabled   = $report->isBingEnabled();
        $googleEnabled = $report->isAnyGoogleTypeEnabled();

        #
        # Add a header message to original referrer keywords reports if plugin is active but no import configured
        #
        if ($viewDataTable->requestConfig->apiMethodToRequestDataTable == 'Referrers.getKeywords') {
            if (Common::getRequestVar('widget', 0, 'int')) {
                return;
            }

            if ($bingEnabled || $googleEnabled) {
                return;
            }

            $idSite = Common::getRequestVar('idSite', 0, 'int');
            if (WebsiteMeasurableType::ID !== Site::getTypeFor($idSite)) {
                return;
            }


            $view                     = new View('@SearchEngineKeywordsPerformance/messageReferrerKeywordsReport');
            $view->hasAdminPriviliges = Piwik::isUserHasSomeAdminAccess();

            $message = $view->render();

            if (property_exists($viewDataTable->config, 'show_header_message')) {
                $viewDataTable->config->show_header_message = $message;
            } else {
                $viewDataTable->config->show_footer_message .= $message;
            }
        }

        #
        # Add related reports and segment info to search engines subtable in `All Channels` report
        #
        if ('Referrers.getReferrerType' === $viewDataTable->requestConfig->apiMethodToRequestDataTable
            && !empty($viewDataTable->requestConfig->idSubtable)
            && Common::REFERRER_TYPE_SEARCH_ENGINE == $viewDataTable->requestConfig->idSubtable) {

            if (!$bingEnabled && !$googleEnabled) {
                return;
            }

            if ($this->shouldShowOriginalReports()) {
                $viewDataTable->config->addRelatedReport(
                    'Referrers.getReferrerType',
                    Piwik::translate('SearchEngineKeywordsPerformance_KeywordsSubtableImported'),
                    [self::REQUEST_PARAM_ORIGINAL_REPORT => 0]
                );

            } else {
                $viewDataTable->config->addRelatedReport(
                    'Referrers.getReferrerType',
                    Piwik::translate('SearchEngineKeywordsPerformance_KeywordsSubtableOriginal'),
                    [self::REQUEST_PARAM_ORIGINAL_REPORT => 1]
                );

                if ('' != Common::getRequestVar('segment', '')) {
                    $this->addSegmentNotSupportedMessage($viewDataTable);
                }
            }
        }

        #
        # Add related reports and segment info to `Referrers` report
        #
        if ('Referrers.getAll' === $viewDataTable->requestConfig->apiMethodToRequestDataTable) {

            if (!$bingEnabled && !$googleEnabled) {
                return;
            }

            if ($this->shouldShowOriginalReports()) {
                $viewDataTable->config->addRelatedReport(
                    'Referrers.getAll',
                    Piwik::translate('SearchEngineKeywordsPerformance_AllReferrersImported'),
                    [self::REQUEST_PARAM_ORIGINAL_REPORT => 0]
                );

            } else {
                $viewDataTable->config->addRelatedReport(
                    'Referrers.getAll',
                    Piwik::translate('SearchEngineKeywordsPerformance_AllReferrersOriginal'),
                    [self::REQUEST_PARAM_ORIGINAL_REPORT => 1]
                );

                if ('' != Common::getRequestVar('segment', '')) {
                    $this->addSegmentNotSupportedMessage($viewDataTable);
                }
            }
        }

        #
        # Add segment info to `Search Engines` report if it is flattened
        #
        if ('Referrers.getSearchEngines' === $viewDataTable->requestConfig->apiMethodToRequestDataTable) {
            if ($viewDataTable->requestConfig->flat) {
                if ($this->shouldShowOriginalReports()) {
                    $viewDataTable->config->addRelatedReport(
                        'Referrers.getSearchEngines',
                        Piwik::translate('SearchEngineKeywordsPerformance_SearchEnginesImported'),
                        [self::REQUEST_PARAM_ORIGINAL_REPORT => 0]
                    );

                } else {
                    $viewDataTable->config->addRelatedReport(
                        'Referrers.getSearchEngines',
                        Piwik::translate('SearchEngineKeywordsPerformance_SearchEnginesOriginal'),
                        [self::REQUEST_PARAM_ORIGINAL_REPORT => 1]
                    );

                    if ('' != Common::getRequestVar('segment', '')) {
                        $this->addSegmentNotSupportedMessage($viewDataTable);
                    }
                }
            }
        }

        #
        # Add related reports and segment info to keywords subtable in `Search Engines` report
        #
        if ('Referrers.getKeywordsFromSearchEngineId' === $viewDataTable->requestConfig->apiMethodToRequestDataTable) {

            /** @var DataTable $dataTable */
            $dataTable = Request::processRequest('Referrers.getSearchEngines',
                ['idSubtable' => null, self::REQUEST_PARAM_ORIGINAL_REPORT => 1]);
            $row       = $dataTable->getRowFromIdSubDataTable($viewDataTable->requestConfig->idSubtable);
            if ($row) {
                $label = $row->getColumn('label');
                if (
                    (strpos($label, 'Google') !== false && $report->isAnyGoogleTypeEnabled())
                    || ((strpos($label, 'Bing') !== false || strpos($label,
                                'Yahoo') !== false) && $report->isBingEnabled())
                ) {

                    if ($this->shouldShowOriginalReports()) {
                        $viewDataTable->config->addRelatedReport(
                            'Referrers.getKeywordsFromSearchEngineId',
                            Piwik::translate('SearchEngineKeywordsPerformance_KeywordsSubtableImported'),
                            [self::REQUEST_PARAM_ORIGINAL_REPORT => 0]
                        );

                    } else {
                        $viewDataTable->config->addRelatedReport(
                            'Referrers.getKeywordsFromSearchEngineId',
                            Piwik::translate('SearchEngineKeywordsPerformance_KeywordsSubtableOriginal'),
                            [self::REQUEST_PARAM_ORIGINAL_REPORT => 1]
                        );

                        if ('' != Common::getRequestVar('segment', '')) {
                            $this->addSegmentNotSupportedMessage($viewDataTable);
                        }
                    }
                }
            }
        }
    }

    /**
     * Removes all rows that does not have a single click, removes all other metrics than clicks, and renames clicks to visits
     *
     * @param $dataTable
     * @param $parentTable
     * @return mixed
     */
    protected function convertDataTableColumns($dataTable, $parentTable = null)
    {
        if ($dataTable instanceof DataTable\DataTableInterface) {
            $dataTable->deleteColumns([Metrics::POSITION, Metrics::NB_IMPRESSIONS, Metrics::CTR]);
            $dataTable->filter('ColumnCallbackDeleteRow', [
                Metrics::NB_CLICKS,
                function ($clicks) {
                    return $clicks === 0;
                }
            ]);
            $dataTable->filter('ReplaceColumnNames', [[Metrics::NB_CLICKS => 'nb_visits']]);

            if ($dataTable instanceof DataTable && $parentTable instanceof DataTable) {
                $totals = $dataTable->getMetadata('totals');
                $parentTotals = $parentTable->getMetadata('totals');

                if (!empty($parentTotals['nb_visits']) && !empty($totals[Metrics::NB_CLICKS])) {
                    $totals['nb_visits'] = $parentTotals['nb_visits'];
                    unset($totals[Metrics::NB_CLICKS]);
                    $dataTable->setMetadata('totals', $totals);
                }
            }

        }

        return $dataTable;
    }

    /**
     * Returns whether the internal request parameter to prevent api manipulations was set
     *
     * @return bool
     */
    protected function shouldShowOriginalReports()
    {
        return !!Common::getRequestVar(self::REQUEST_PARAM_ORIGINAL_REPORT, false);
    }

    /**
     * Adds a header (or footer) note to the view, that report does not support segmentation
     *
     * @param ViewDataTable $view
     */
    protected function addSegmentNotSupportedMessage(ViewDataTable $view)
    {
        $message = '<p style="margin-top:2em;margin-bottom:2em" class=" alert-info alert">' .
            Piwik::translate('SearchEngineKeywordsPerformance_NoSegmentation') .
            '</p>';

        if (property_exists($view->config, 'show_header_message')) {
            $view->config->show_header_message = $message;
        } else {
            $view->config->show_footer_message .= $message;
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/SearchEngineKeywordsPerformance/stylesheets/styles.less";
    }

    public function getJSFiles(&$javascripts)
    {
        $javascripts[] = "plugins/SearchEngineKeywordsPerformance/javascripts/GoogleCrawlIssuesDataTable.js";
    }

    public function addMetricTranslations(&$translations)
    {
        $translations = array_merge($translations, Metrics::getMetricsTranslations());
    }

    public function addMetricDocumentationTranslations(&$translations)
    {
        $translations = array_merge($translations, Metrics::getMetricsDocumentation());
    }

    public function checkIsLowerMetricValueBetter(&$isLowerBetter, $metric)
    {
        if ($metric === Metrics::POSITION) {
            $isLowerBetter = true;
        }
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = "SearchEngineKeywordsPerformance_LinksToUrl";
        $translationKeys[] = "SearchEngineKeywordsPerformance_SitemapsContainingUrl";
    }

    /**
     * Installation
     */
    public function install()
    {
        GoogleModel::install();
        BingModel::install();
    }

    public static function isGoogleForceEnabled($idSite)
    {
        return self::isSearchEngineForceEnabled('google', $idSite);
    }

    public static function isBingForceEnabled($idSite)
    {
        return self::isSearchEngineForceEnabled('bing', $idSite);
    }

    public static function isSearchEngineForceEnabled($searchEngine, $idSite)
    {
        if (!is_numeric($idSite) || $idSite <= 0) {
            return false;
        }

        $cache    = Cache::getTransientCache();
        $cacheKey = 'SearchEngineKeywordsPerformance.isSearchEngineForceEnabled.' . $searchEngine . '.' . $idSite;

        if ($cache->contains($cacheKey)) {
            return $cache->fetch($cacheKey);
        }

        $result = false;

        /**
         * @ignore
         */
        Piwik::postEvent('SearchEngineKeywordsPerformance.isSearchEngineForceEnabled',
            [&$result, $searchEngine, $idSite]);

        // force enable reports for rollups where child pages are configured
        if (class_exists('\Piwik\Plugins\RollUpReporting\Type') && \Piwik\Plugins\RollUpReporting\Type::ID === Site::getTypeFor($idSite)) {
            $rollUpModel = new \Piwik\Plugins\RollUpReporting\Model();
            $childIdSites = $rollUpModel->getChildIdSites($idSite);

            foreach ($childIdSites as $childIdSite) {
                $measurableSettings = new MeasurableSettings($childIdSite);
                if ($searchEngine === 'google' && $measurableSettings->googleSearchConsoleUrl && $measurableSettings->googleSearchConsoleUrl->getValue()) {
                    $result = true;
                    break;
                }
                if ($searchEngine === 'bing' && $measurableSettings->bingSiteUrl && $measurableSettings->bingSiteUrl->getValue()) {
                    $result = true;
                    break;
                }
            }
        }

        $cache->save($cacheKey, $result);

        return $result;
    }
}
