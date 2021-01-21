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
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Importer;

use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Parameters;
use Piwik\Container\StaticContainer;
use Piwik\Config;
use Piwik\DataAccess\ArchiveSelector;
use Piwik\DataAccess\ArchiveTableCreator;
use Piwik\DataAccess\ArchiveWriter;
use Piwik\DataAccess\LogAggregator;
use Piwik\DataTable;
use Piwik\DataTable\Manager AS DataTableManager;
use Piwik\Date;
use Piwik\Db;
use Piwik\Log;
use Piwik\Period\Day;
use Piwik\Period\Month;
use Piwik\Period\Range;
use Piwik\Period\Week;
use Piwik\Period\Year;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\InvalidClientConfigException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\InvalidCredentialsException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\MissingClientConfigException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\MissingOAuthConfigException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\UnknownAPIException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\MeasurableSettings;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Model\Google as GoogleModel;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Archiver\Google as GoogleArchiver;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Metrics;
use Piwik\Segment;
use Piwik\Site;
use Piwik\Version;

class Google
{
    /**
     * @var int site id
     */
    protected $idSite = null;

    /**
     * @var string url, eg http://matomo.org
     */
    protected $searchConsoleUrl = null;

    /**
     * Id if account, to use for querying data
     *
     * @var string
     */
    protected $accountId = null;

    /**
     * Force Data Import
     *
     * @var bool
     */
    protected $force = false;

    /**
     * Search types available for import
     *
     * @var array
     */
    protected static $typesToImport = ['web', 'video', 'image'];

    /**
     * Holding the dates data is available for
     * will be filled with a call of `getAvailableDates`
     */
    public static $availableDates = [];

    /**
     * @param int $idSite
     * @param bool $force  force reimport of all data
     */
    public function __construct($idSite, $force = false)
    {
        $this->idSite = $idSite;
        $this->force  = $force;

        $setting          = new MeasurableSettings($idSite);
        $searchConsoleUrl = $setting->googleSearchConsoleUrl;

        list($this->accountId,
            $this->searchConsoleUrl) = explode('##', $searchConsoleUrl->getValue());
    }

    protected static function getRowCountToImport()
    {
        return Config::getInstance()->General['datatable_archiving_maximum_rows_referrers'];
    }

    /**
     * Triggers keyword import and plugin archiving for all dates search console has data for
     *
     * @param string|int|null $limitKeywordDates if integer given: limits the amount of imported dates to the last available X
     *                                           if string given: only imports keywords for the given string date
     * @return null
     */
    public function importAllAvailableData($limitKeywordDates = null)
    {
        // if specific date given
        if (is_string($limitKeywordDates) && strlen($limitKeywordDates) == 10) {
            $availableDates = [$limitKeywordDates];
        } else {
            $availableDates = self::getAvailableDates($this->accountId, $this->searchConsoleUrl);

            sort($availableDates);

            if ($limitKeywordDates > 0) {
                $availableDates = array_slice($availableDates, -$limitKeywordDates, $limitKeywordDates);
            }
        }
        $this->importKeywordsForListOfDates($availableDates);

        $this->completeExistingArchivesForListOfDates($availableDates);
    }

    protected function importKeywordsForListOfDates($datesToImport)
    {
        foreach ($datesToImport as $date) {
            foreach (self::$typesToImport as $type) {
                self::importKeywordsIfNecessary(
                    $this->accountId,
                    $this->searchConsoleUrl,
                    $date,
                    $type,
                    $this->force
                );
            }
        }
    }

    protected function completeExistingArchivesForListOfDates($datesToComplete)
    {
        $days = $weeks = $months = $years = [];

        sort($datesToComplete);

        foreach ($datesToComplete as $date) {
            $date                             = Date::factory($date);
            $day                              = new Day($date);
            $days[$day->toString()]           = $day;
            $week                             = new Week($date);
            $weeks[$week->getRangeString()]   = $week;
            $month                            = new Month($date);
            $months[$month->getRangeString()] = $month;
            $year                             = new Year($date);
            $years[$year->getRangeString()]   = $year;
        }

        $periods = $days + $weeks + $months + $years;

        foreach ($periods as $period) {
            $this->completeExistingArchiveIfAny($period);
        }
    }

    /**
     * Imports keyword to model storage if not already done
     *
     * @param string $accountId google account id
     * @param string $url       url, eg http://matomo.org
     * @param string $date      date string, eg 2016-12-24
     * @param string $type      'web', 'image' or 'video'
     * @param bool   $force     force reimport
     * @return boolean
     */
    public static function importKeywordsIfNecessary($accountId, $url, $date, $type, $force = false)
    {
        $model = new GoogleModel();

        $keywordData = $model->getKeywordData($url, $date, $type);

        if ($keywordData && !$force) {
            return false; // skip if data already available and no reimport forced
        }

        $dataTable = self::getKeywordsFromConsoleAsDataTable($accountId, $url, $date, $type);

        if ($dataTable) {
            $keywordData = $dataTable->getSerialized(self::getRowCountToImport(), null, Metrics::NB_CLICKS);
            $model->archiveKeywordData($url, $date, $type, $keywordData[0]);
            return true;
        }

        return false;
    }

    protected static function getAvailableDates($accountId, $url)
    {
        try {
            if (!array_key_exists($accountId.$url, self::$availableDates)) {
                self::$availableDates[$accountId.$url] = StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Google')
                    ->getDatesWithSearchAnalyticsData($accountId, $url);
            }

        } catch (InvalidCredentialsException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return [];
        } catch (InvalidClientConfigException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return [];
        } catch (MissingOAuthConfigException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return [];
        } catch (MissingClientConfigException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return [];
        } catch (UnknownAPIException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            Log::error('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return [];
        }

        if (array_key_exists($accountId.$url, self::$availableDates)) {
            return self::$availableDates[$accountId . $url];
        }

        return [];
    }

    /**
     * Fetches data from google search console and migrates it to a Matomo Datatable
     *
     * @param string $accountId google account id
     * @param string $url       url, eg http://matomo.org
     * @param string $date      date string, eg 2016-12-24
     * @param string $type      'web', 'image' or 'video'
     * @return null|DataTable
     */
    protected static function getKeywordsFromConsoleAsDataTable($accountId, $url, $date, $type)
    {
        $dataTable = new DataTable();

        try {
            $availableDates = self::getAvailableDates($accountId, $url);

            if (!in_array($date, $availableDates)) {
                Log::debug("[SearchEngineKeywordsPerformance] No $type keywords available for $date and $url");
                return null;
            }

            Log::debug("[SearchEngineKeywordsPerformance] Fetching $type keywords for $date and $url");

            $keywordData = StaticContainer::get('Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Google')
                                          ->getSearchAnalyticsData($accountId, $url, $date, $type,
                                              self::getRowCountToImport());
        } catch (InvalidCredentialsException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return null;
        } catch (InvalidClientConfigException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return null;
        } catch (MissingOAuthConfigException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return null;
        } catch (MissingClientConfigException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return null;
        } catch (UnknownAPIException $e) {
            Log::info('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::error('[SearchEngineKeywordsPerformance] Error while importing Google keywords for ' . $url . ': ' . $e->getMessage());
            return null;
        }

        if (empty($keywordData) || !($rows = $keywordData->getRows())) {
            return $dataTable; // return empty table so it will be stored
        }

        foreach ($rows as $keywordDataSet) {
            /** @var \Google_Service_Webmasters_ApiDataRow $keywordDataSet */
            $keys    = $keywordDataSet->getKeys();
            $rowData = [
                DataTable\Row::COLUMNS => [
                    'label'                 => reset($keys),
                    Metrics::NB_CLICKS      => (int)$keywordDataSet->getClicks(),
                    Metrics::NB_IMPRESSIONS => (int)$keywordDataSet->getImpressions(),
                    Metrics::CTR            => (float)$keywordDataSet->getCtr(),
                    Metrics::POSITION       => (float)$keywordDataSet->getPosition(),
                ]
            ];
            $row     = new DataTable\Row($rowData);
            $dataTable->addRow($row);
        }

        unset($keywordData);

        return $dataTable;
    }

    /**
     * Runs the Archiving for SearchEngineKeywordsPerformance plugin if an archive for the given period already exists
     *
     * @param \Piwik\Period $period
     */
    protected function completeExistingArchiveIfAny($period)
    {
        $parameters = new Parameters(new Site($this->idSite), $period, new Segment('', ''));
        $parameters->setRequestedPlugin('SearchEngineKeywordsPerformance');
        if (method_exists($parameters, 'onlyArchiveRequestedPlugin')) {
            $parameters->onlyArchiveRequestedPlugin();
        }

        $result    = ArchiveSelector::getArchiveIdAndVisits($parameters, $period->getDateStart()->getDateStartUTC());
        $idArchive = $result ? array_shift($result) : null;

        if (empty($idArchive)) {
            return; // ignore periods that weren't archived before
        }

        $archiveWriter            = new ArchiveWriter($parameters, !!$idArchive);
        $archiveWriter->idArchive = $idArchive;

        $archiveProcessor = new ArchiveProcessor($parameters, $archiveWriter,
            new LogAggregator($parameters));

        $archiveProcessor->setNumberOfVisits(1, 1);

        $archiver = new GoogleArchiver($archiveProcessor);

        if ($period instanceof Day) {
            $archiver->aggregateDayReport();
        } else {
            $archiver->aggregateMultipleReports();
        }

        $archiver->finalize();

        DataTableManager::getInstance()->deleteAll();
    }
}