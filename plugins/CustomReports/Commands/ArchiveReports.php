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

namespace Piwik\Plugins\CustomReports\Commands;

use Piwik\Common;
use Piwik\Date;
use Piwik\Period;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\CustomReports\Archiver;
use Piwik\Version;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Parameters;
use Piwik\DataAccess\ArchiveSelector;
use Piwik\DataAccess\ArchiveWriter;
use Piwik\DataAccess\LogAggregator;
use Piwik\DataTable\Manager AS DataTableManager;
use Piwik\Period\Day;
use Piwik\Segment;
use Piwik\Site;

class ArchiveReports extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('customreports:archive');
        $this->setDescription('Let\'s you trigger custom reports archiving for given site and date range');
        $this->addOption('idsites', null, InputOption::VALUE_REQUIRED, 'The ids of the sites you want to archive custom reports for', 'all');
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'The date or date range you want to archive custom reports for');
        $this->addOption('idreport', null, InputOption::VALUE_REQUIRED, 'If set, only a specific report will be archived');
        $this->addOption('disable-segments', null, InputOption::VALUE_NONE, 'Disables archiving of pre-archived segments');
        $this->addOption('periods', null, InputOption::VALUE_REQUIRED, 'Specify which periods should be archived. A comma separated list will archive multiple periods', 'all');
    }

    public function isEnabled()
    {
        // Archives can't be overwritten in Matomo before 3.0.3
        return version_compare(Version::VERSION, '3.0.3', '>=');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $idSites = Site::getIdSitesFromIdSitesString($input->getOption('idsites'));
        $date = $input->getOption('date');
        $idReport = $input->getOption('idreport');

        Period::checkDateFormat($date);

        if (Period::isMultiplePeriod($date, 'day')) {
            $period = Period\Factory::build('range', $date);
            $datesToComplete = $this->getDaysFromPeriod($period);
        } else {
            $datesToComplete = [$date];
        }

        $periods = $this->getPeriodsToArchive($datesToComplete, $input->getOption('periods'));

        foreach ($idSites as $idSite) {
            $output->writeln('Starting to archive custom reports for Site ' . Site::getNameFor($idSite));

            if ($input->getOption('disable-segments')) {
                $segments = [];
            } else {
                $segments = ArchiveProcessor\Rules::getSegmentsToProcess([$idSite]);
            }

            array_unshift($segments, ''); // always archive data without segment

            foreach ($segments as $segment) {
                if ('' !== $segment) {
                    $output->writeln('Archiving segment ' . $segment);
                }

                $progress = new ProgressBar($output, count($periods));
                $progress->start();

                $periodsNotArchived = [];
                foreach ($periods as $period) {
                    if (!$this->archiveCustomReports($idSite, $period, $segment, $idReport)) {
                        $periodsNotArchived[] = $period instanceof Day ? $period->toString() : $period->getRangeString();
                    }
                    $progress->advance();
                }

                $progress->finish();
                $output->writeln('');

                if (!empty($periodsNotArchived)) {
                    $output->writeln('Archiving has been skipped for following periods, as a full archiving has not yet been done: "' . implode('", "', $periodsNotArchived) . '"');
                }
            }
        }
    }

    /**
     * @param Period $period
     * @return array
     */
    protected function getDaysFromPeriod(Period $period)
    {
        $dates = [];

        if ($period instanceof Day) {
            return [$period->getDateStart()->toString()];
        }

        $subperiods = $period->getSubperiods();

        foreach ($subperiods as $subperiod) {
            if ($subperiod instanceof Day) {
                if ($subperiod->getDateStart()->isLater(Date::today())) {
                    continue; // discard days in the future
                }
                $dates[] = $subperiod->getDateStart()->toString();
            } else {
                $dates = array_merge($dates, $this->getDaysFromPeriod($subperiod));
            }
        }

        return $dates;
    }

    /**
     * @param array $dates
     * @return Period[]
     */
    protected function getPeriodsToArchive($dates, $periods)
    {
        $days = $weeks = $months = $years = [];

        sort($dates);

        if (empty($periods) || $periods === 'all') {
            $periods = array('day', 'week', 'month', 'year');
        } else {
            $periods = Common::mb_strtolower($periods);
            $periods = explode(',' , $periods);
        }

        foreach ($dates as $date) {
            $date = Date::factory($date);
            if (in_array('day', $periods)) {
                $day = new Day($date);
                $days[$day->toString()] = $day;
            }
            if (in_array('week', $periods)) {
                $week                             = new Period\Week($date);
                $weeks[$week->getRangeString()]   = $week;
            }
            if (in_array('month', $periods)) {
                $month                            = new Period\Month($date);
                $months[$month->getRangeString()] = $month;
            }
            if (in_array('year', $periods)) {
                $year                             = new Period\Year($date);
                $years[$year->getRangeString()]   = $year;
            }
        }

        return $days + $weeks + $months + $years;
    }

    /**
     * Runs the Archiving for CustomReports plugin if an archive for the given period already exists
     *
     * @param int           $idSite
     * @param \Piwik\Period $period
     * @return bool
     * @throws \Piwik\Exception\UnexpectedWebsiteFoundException
     */
    protected function archiveCustomReports($idSite, $period, $segmentCondition = '', $idReport = null)
    {
        $_GET['idSite'] = $idSite;

        $parameters = new Parameters(new Site($idSite), $period, new Segment($segmentCondition, []));
        $parameters->setRequestedPlugin('CustomReports');

        $result    = ArchiveSelector::getArchiveIdAndVisits($parameters, $period->getDateStart()->getDateStartUTC(), false);
        $idArchive = $result ? array_shift($result) : null;

        if (empty($idArchive)) {
            return false; // ignore periods if full archiving hadn't run before
        }

        $archiveWriter            = new ArchiveWriter($parameters, !!$idArchive);
        $archiveWriter->idArchive = $idArchive;

        $logAggregator = new LogAggregator($parameters);
        if (method_exists($logAggregator, 'allowUsageSegmentCache')) {
            $logAggregator->allowUsageSegmentCache();
        }
        $archiveProcessor = new ArchiveProcessor($parameters, $archiveWriter, $logAggregator);

        $archiveProcessor->setNumberOfVisits(1, 1);

        $archiver = new Archiver($archiveProcessor);

        if ($idReport) {
            $archiver->setArchiveOnlyReport($idReport);
        }

        if ($period instanceof Day) {
            $archiver->aggregateDayReport();
        } else {
            $archiver->aggregateMultipleReports();
        }

        $archiver->finalize();

        DataTableManager::getInstance()->deleteAll();

        return true;
    }
}
