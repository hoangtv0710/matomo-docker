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
namespace Piwik\Plugins\AbTesting\Model;

use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugins\AbTesting\Dao\Experiment;
use Piwik\Plugins\AbTesting\Input\Description;
use Piwik\Plugins\AbTesting\Input\Hypothesis;
use Piwik\Plugins\AbTesting\Input\MinimumDetectableEffect;
use Piwik\Plugins\AbTesting\Input\PercentageParticipants;
use Piwik\Plugins\AbTesting\Input\ScheduledDate;
use Piwik\Plugins\AbTesting\Metrics;
use Piwik\Plugins\AbTesting\Tracker\RequestProcessor;
use Piwik\Plugins\AbTesting\Tracker\Schedule;
use Piwik\Plugins\AbTesting\Tracker\Target;
use Piwik\Site;
use Piwik\Tracker\Cache;
use Exception;
use Piwik\Plugins\AbTesting\Input\ConfidenceThreshold;
use Piwik\Plugins\AbTesting\Input\Name;
use Piwik\Plugins\AbTesting\Input\SuccessMetrics;
use Piwik\Plugins\AbTesting\Input\Targets;
use Piwik\Plugins\AbTesting\Input\Variations;

class Experiments
{
    const STATUS_CREATED = 'created';
    const STATUS_RUNNING = 'running';
    const STATUS_FINISHED = 'finished';
    const STATUS_ARCHIVED = 'archived';

    /**
     * @var Experiment
     */
    private $dao;

    /**
     * @var Metrics
     */
    private $metrics;

    public function __construct(Experiment $experimentDao, Metrics $metrics)
    {
        $this->dao = $experimentDao;
        $this->metrics = $metrics;
    }

    public function getValidStatuses()
    {
        return array(
            array('value' => self::STATUS_CREATED, 'name' => Piwik::translate('AbTesting_StatusCreated')),
            array('value' => self::STATUS_RUNNING, 'name' => Piwik::translate('AbTesting_StatusRunning')),
            array('value' => self::STATUS_FINISHED, 'name' => Piwik::translate('AbTesting_StatusFinished')),
            array('value' => self::STATUS_ARCHIVED, 'name' => Piwik::translate('AbTesting_StatusArchived')),
        );
    }
    
    /**
     * @return array
     */
    public function getActiveExperiments($idSite)
    {
        return $this->getExperimentsByStatuses($idSite, array(static::STATUS_CREATED, static::STATUS_RUNNING));
    }

    /**
     * @return array
     */
    public function getExperimentsWithReports($idSite)
    {
        return $this->getExperimentsByStatuses($idSite, array(static::STATUS_FINISHED, static::STATUS_RUNNING));
    }

    /**
     * @return array
     */
    public function getAllActiveExperiments()
    {
        $experiments = $this->dao->getAllExperimentsByStatuses(array(static::STATUS_CREATED, static::STATUS_RUNNING));
        $experiments = $this->enrichExperiments($experiments);

        return $experiments;
    }

    /**
     * @return array|false
     */
    public function getExperiment($idExperiment, $idSite)
    {
        $experiment = $this->dao->getExperiment($idExperiment, $idSite);
        $experiment = $this->enrichExperiment($experiment);

        return $experiment;
    }

    /**
     * @return array
     */
    public function getExperimentsByStatuses($idSite, $statuses)
    {
        if (is_string($statuses)) {
            $statuses = array($statuses);
        }

        $experiments = $this->dao->getExperimentsByStatuses($idSite, $statuses);
        $experiments = $this->enrichExperiments($experiments);

        return $experiments;
    }

    /**
     * @return array
     */
    public function getAllExperimentsForSite($idSite)
    {
        $experiments = $this->dao->getAllExperimentsForSite($idSite);
        $experiments = $this->enrichExperiments($experiments);

        return $experiments;
    }

    public function checkExperimentExists($idExperiment, $idSite)
    {
        $experiment = $this->dao->getExperiment($idExperiment, $idSite);

        if (empty($experiment)) {
            throw new Exception(Piwik::translate('AbTesting_ErrorExperimentDoesNotExist'));
        }
    }

    public function checkExperimentCanBeUpdated($idExperiment, $idSite)
    {
        $this->checkExperimentExists($idExperiment, $idSite);

        $experiment = $this->dao->getExperiment($idExperiment, $idSite);

        if ($experiment['status'] == static::STATUS_ARCHIVED) {
            throw new Exception(Piwik::translate('AbTesting_ErrorExperimentCannotBeUpdatedBecauseArchived'));
        }
    }

    public function hasSiteExperiments($idSite)
    {
        return $this->dao->hasExperimentsForSite($idSite);
    }

    public function finishExperiment($idExperiment, $idSite)
    {
        $experiment = $this->dao->getExperiment($idExperiment, $idSite);

        if ($experiment['status'] === static::STATUS_FINISHED
            || $experiment['status'] === static::STATUS_ARCHIVED) {
            throw new Exception(Piwik::translate('AbTesting_ErrorExperimentCannotBeFinished'));
        }

        $columns = array('end_date' => $this->getCurrentDateTime(), 'status' => static::STATUS_FINISHED);
        $this->dao->updateExperimentColumns($idExperiment, $idSite, $columns);
        $this->updateExperimentModifedDate($idExperiment, $idSite);

        Piwik::postEvent('AbTesting.finishExperiment', array($idExperiment, $idSite));
    }
    
    public function startExperiment($idExperiment, $idSite)
    {
        $experiment = $this->dao->getExperiment($idExperiment, $idSite);

        if (!empty($experiment['status']) && $experiment['status'] !== static::STATUS_CREATED) {
            throw new Exception(Piwik::translate('AbTesting_ErrorExperimentAlreadyStarted'));
        }

        $columns = array('start_date' => $this->getCurrentDateTime(), 'status' => static::STATUS_RUNNING);
        $this->dao->updateExperimentColumns($idExperiment, $idSite, $columns);
        $this->updateExperimentModifedDate($idExperiment, $idSite);

        Piwik::postEvent('AbTesting.startExperiment', array($idExperiment, $idSite));
    }

    public function setStartDateToCurrentTime($idExperiment, $idSite)
    {
        $columns = array('start_date' => $this->getCurrentDateTime());
        $this->dao->updateExperimentColumns($idExperiment, $idSite, $columns);
    }

    protected function getCurrentDateTime()
    {
        return Date::now()->getDatetime();
    }

    private function getCurrentTimestamp()
    {
        return Date::factory($this->getCurrentDateTime())->getTimestampUTC();
    }

    public function setStatus($idExperiment, $idSite, $status)
    {
        $this->dao->updateExperimentColumns($idExperiment, $idSite, array('status' => $status));
        $this->updateExperimentModifedDate($idExperiment, $idSite);
        $this->clearCache($idSite);

        if ($status === self::STATUS_RUNNING) {
            // self::startExperiment() would trigger this event but we do not use this method to not overwrite
            // the scheduled start date
            Piwik::postEvent('AbTesting.startExperiment', array($idExperiment, $idSite));
        } else if ($status === self::STATUS_FINISHED) {
            // self::finishExperiment() would trigger this event but we do not use this method to not overwrite
            // the scheduled finish date
            Piwik::postEvent('AbTesting.finishExperiment', array($idExperiment, $idSite));
        }

        return $idExperiment;
    }

    public function deleteExperiment($idExperiment, $idSite)
    {
        $this->dao->deleteExperiment($idExperiment, $idSite);
        $this->clearCache($idSite);
    }

    public function deleteExperimentsForSite($idSite)
    {
        $this->dao->deleteExperimentsForSite($idSite);
        $this->clearCache($idSite);
    }

    private function enrichExperiments($experiments)
    {
        if (empty($experiments)) {
            return array();
        }

        foreach ($experiments as $index => $experiment) {
            $experiments[$index] = $this->enrichExperiment($experiment);
        }

        return $experiments;
    }

    protected function enrichExperiment($experiment)
    {
        if (empty($experiment)) {
            return $experiment;
        }

        /** @var Date $start */         /** @var Date $end */
        list($start, $end) = $this->getStartEndDate($experiment);

        $experiment['start_date_site_timezone'] = !empty($start) ? $start->getDatetime() : null;
        $experiment['end_date_site_timezone'] = !empty($experiment['end_date']) && $end ? $end->getDatetime() : null;
        $experiment['date_range_string'] = $this->getDateRangeStringForExperiment($start, $end);
        $experiment['duration'] = $this->getExperimentDuration($start, $end);
        $experiment['included_targets'] = $this->getOnlyValidTargets($experiment, 'included_targets');
        $experiment['excluded_targets'] = $this->getOnlyValidTargets($experiment, 'excluded_targets');

        if (!empty($experiment['start_date']) && $experiment['status'] == self::STATUS_CREATED) {
            // we may need to fix the status of this experiment dynamically
            $schedule = new Schedule($experiment['start_date'], $experiment['end_date']);

            if ($schedule->matchesTimestamp($this->getCurrentTimestamp())) {
                $this->setStatus($experiment['idexperiment'], $experiment['idsite'], Experiments::STATUS_RUNNING);
                $experiment['status'] = Experiments::STATUS_RUNNING;
            }
        }

        if ($experiment['status'] == Experiments::STATUS_RUNNING && empty($experiment['start_date'])) {
            // in case for some reason the start date was not set
            $this->setStartDateToCurrentTime($experiment['idexperiment'], $experiment['idsite']);
            $experiment['start_date'] = $this->getCurrentDateTime();
        }

        return $experiment;
    }

    private function getOnlyValidTargets($experiment, $key)
    {
        $targets = array();

        if (!empty($experiment[$key])) {
            foreach ($experiment[$key] as $target) {
                if (!empty($target['attribute']) && !empty($target['type']) &&
                    (!empty($target['value']) || !Target::doesTargetTypeRequireValue($target['type'])) ) {
                    // type ANY is the only target that may have no value set
                    $targets[] = $target;
                }
            }
        }

        return $targets;
    }

    private function getStartEndDate($experiment)
    {
        if (empty($experiment['start_date'])) {
            return array(null, null);
        }

        $timezone = Site::getTimezoneFor($experiment['idsite']);
        $start = Date::factory($experiment['start_date'], $timezone);

        if (!empty($experiment['end_date'])) {
            $end = Date::factory($experiment['end_date'], $timezone);
        } else {
            $end = Date::factory($this->getCurrentDateTime(), $timezone);
        }

        if ($end->isEarlier($start)) {
            $end = $start;
        }

        return array($start, $end);
    }

    /**
     * @param Date|null $start
     * @param Date|null $end
     * @return string|null
     */
    private function getDateRangeStringForExperiment($start, $end)
    {
        if (empty($start)) {
            return null;
        }

        /** @var Date $start */         /** @var Date $end */
        return $start->toString('Y-m-d') . ',' . $end->toString('Y-m-d');
    }

    /**
     * @param Date|null $start
     * @param Date|null $end
     * @return int|null
     */
    private function getExperimentDuration($start, $end)
    {
        if (empty($start)) {
            return null;
        }

        $durationInSeconds = $end->getTimestamp() - $start->getTimestamp();

        if ($durationInSeconds > 0) {
            $secondsPerHour = 60 * 60;
            $hours = 24;
            $secoundsPerDay = $secondsPerHour * $hours;
            $days = (int) floor(($durationInSeconds / $secoundsPerDay));
            $remainingSeconds = $durationInSeconds - ($days * $secoundsPerDay);
            $remainingHours = (int) floor($remainingSeconds / $secondsPerHour);

            if ($days === 1) {
                if ($remainingHours === 1) {
                    return Piwik::translate('AbTesting_1DayAnd1Hour');
                } else {
                    return Piwik::translate('AbTesting_1DayAndYHours', array($remainingHours));
                }
            } elseif ($days > 1) {
                if ($remainingHours === 1) {
                    return Piwik::translate('AbTesting_XDaysAnd1Hour', $days);
                } else {
                    return Piwik::translate('AbTesting_XDaysAndYHours', array($days, $remainingHours));
                }
            } else {
                if ($remainingHours === 1) {
                    return Piwik::translate('AbTesting_1Hour');
                } else {
                    return Piwik::translate('AbTesting_Xhours', $remainingHours);
                }
            }
        }

        return Piwik::translate('AbTesting_Xhours', 0);
    }

    public function createExperiment($idSite, $name, $description, $hypothesis, $variations, $includedTargets, $successMetrics, $confidenceThreshold)
    {
        $variations = $this->trimValuesInArray($variations, 'redirect_url');

        $this->checkBase($idSite, $name, $description, $hypothesis, $variations, $includedTargets, array(), $successMetrics, $confidenceThreshold);

        if ($this->dao->getIdExperimentByName($name, $idSite)) {
            throw new Exception(Piwik::translate('AbTesting_ErrorExperimentNameIsAlreadyInUse'));
        }

        $successMetrics = $this->removeDuplicateSuccessMetrics($successMetrics);

        $columns = array(
            'idsite' => $idSite,
            'name' => $name,
            'description' => $description,
            'hypothesis' => $hypothesis,
            'variations' => $variations,
            'included_targets' => $includedTargets,
            'excluded_targets' => array(),
            'success_metrics' => $successMetrics,
            'start_date' => null,
            'end_date' => null,
            'status' => self::STATUS_CREATED,
            'modified_date' => $this->getCurrentDateTime(),
            'confidence_threshold' => $confidenceThreshold
        );

        $idExperiment = $this->dao->createExperiment($columns);
        $this->clearCache($idSite);
        
        return $idExperiment;
    }

    private function removeDuplicateSuccessMetrics($successMetrics)
    {
        $existingNames = array(); // successMetric => lastIndex
        $indexesToDelete = array();
        foreach ($successMetrics as $index => $successMetric) {
            $name = $successMetric['metric'];

            if (isset($existingNames[$name])) {
                $indexesToDelete[] = $existingNames[$name];
            }

            $existingNames[$name] = $index;
        }

        foreach ($indexesToDelete as $index) {
            unset($successMetrics[$index]);
        }

        return array_values($successMetrics);
    }

    private function trimValuesInArray($entries, $field)
    {
        if (empty($entries) || !is_array($entries)) {
            return $entries;
        }

        foreach ($entries as &$entry) {
            if (isset($entry[$field]) && is_string($entry[$field])) {
                $entry[$field] = trim($entry[$field]);
            }
        }
        return $entries;
    }
    
    public function updateExperiment($idExperiment, $idSite, $name, $description, $hypothesis, $variations, $confidenceThreshold, $mdeRelative, $percentageParticipants, $includedTargets, $excludedTargets, $successMetrics, $startDate, $endDate)
    {
        $variations = $this->trimValuesInArray($variations, 'redirect_url');
        $this->checkBase($idSite, $name, $description, $hypothesis, $variations, $includedTargets, $excludedTargets, $successMetrics, $confidenceThreshold);

        $otherIdExperiment = $this->dao->getIdExperimentByName($name, $idSite);
        if ($otherIdExperiment && $otherIdExperiment != $idExperiment) {
            throw new Exception(Piwik::translate('AbTesting_ErrorExperimentNameIsAlreadyInUse'));
        }

        if (empty($startDate)) {
            $experiment = $this->getExperiment($idExperiment, $idSite);
            if ($experiment['status'] !== Experiments::STATUS_CREATED &&
                !empty($experiment['start_date'])) {
                // if we experiment is running, we do not allow to clear the start date. This may happen eg if user
                // is editing an experiment in the UI, then a tracking request comes in that starts the experiment,
                // then the user updates the experiment without reloading. It would still have the old empty start date
                // even though we have started it in the background meanwhile
                $startDate = $experiment['start_date'];
            }
        }

        $participants = new PercentageParticipants($percentageParticipants);
        $participants->check();

        $scheduledDate = new ScheduledDate($startDate, $endDate);
        $scheduledDate->check();

        $mde = new MinimumDetectableEffect($mdeRelative);
        $mde->check();

        $successMetrics = $this->removeDuplicateSuccessMetrics($successMetrics);

        // we allow optionally users to specify a variation with the name 'original' via the API to set a URL
        // for the original variation
        $originalUrl = null;
        foreach ($variations as $index => $variation) {
            if ($variation['name'] === RequestProcessor::VARIATION_NAME_ORIGINAL) {
                $original = $variation;
                unset($variations[$index]);

                $originalUrl = '';
                if (!empty($original['redirect_url'])) {
                    $originalUrl = $original['redirect_url'];
                }

                $variations = array_values($variations);
                break;
            }
        }

        $columns = array(
            'name' => $name,
            'description' => $description,
            'hypothesis' => $hypothesis,
            'original_redirect_url' => $originalUrl,
            'variations' => $variations,
            'confidence_threshold' => $confidenceThreshold,
            'mde_relative' => $mdeRelative,
            'start_date' => empty($startDate) ? null : $startDate,
            'end_date' => empty($endDate) ? null : $endDate,
            'percentage_participants' => (int) $percentageParticipants,
            'included_targets' => $includedTargets,
            'excluded_targets' => $excludedTargets,
            'success_metrics' => $successMetrics
        );
        
        $this->dao->updateExperimentColumns($idExperiment, $idSite, $columns);
        $this->updateExperimentModifedDate($idExperiment, $idSite);
        $this->clearCache($idSite);
    }

    private function updateExperimentModifedDate($idExperiment, $idSite)
    {
        $columns = array('modified_date' => $this->getCurrentDateTime());

        $this->dao->updateExperimentColumns($idExperiment, $idSite, $columns);
    }

    private function clearCache($idSite)
    {
        Cache::deleteCacheWebsiteAttributes($idSite);
        Cache::clearCacheGeneral();
    }

    private function checkBase($idSite, $name, $description, $hypothesis, $variations, $includedTargets, $excludedTargets, $successMetrics, $confidenceThreshold)
    {
        $name = new Name($name);
        $name->check();

        $hypothesis = new Hypothesis($hypothesis);
        $hypothesis->check();

        $description = new Description($description);
        $description->check();

        $variations = new Variations($variations);
        $variations->check();

        $targets = new Targets($includedTargets, Piwik::translate('AbTesting_IncludedTargets'), $needsAtLeastOne = true);
        $targets->check();

        $targets = new Targets($excludedTargets, Piwik::translate('AbTesting_ExcludedTargets'), $needsAtLeastOne = false);
        $targets->check();

        $availableMetrics = $this->metrics->getSuccessMetrics($idSite);

        $successMetrics = new SuccessMetrics($availableMetrics, $successMetrics);
        $successMetrics->check();

        $confidenceThreshold = new ConfidenceThreshold($confidenceThreshold);
        $confidenceThreshold->check();
    }

}

