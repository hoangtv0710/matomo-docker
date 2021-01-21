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
namespace Piwik\Plugins\AbTesting\Tracker;

use Piwik\Common;
use Piwik\Date;
use Piwik\Plugins\AbTesting\Dao\LogTable;
use Piwik\Plugins\AbTesting\Actions\ActionAbTesting;
use Piwik\Plugins\AbTesting\Model\Experiments;
use Piwik\Plugins\AbTesting\Tracker\RequestProcessor\Utils;
use Piwik\Tracker\Request;
use Piwik\Tracker;
use Piwik\Tracker\Visit\VisitProperties;

class RequestProcessor extends Tracker\RequestProcessor
{
    const VARIATION_ORIGINAL_ID = '0';
    const VARIATION_NAME_ORIGINAL = 'original';
    const METADATA_EXPERIMENT = 'experiment';
    const METADATA_VARIATION_ID = 'variationName';
    const EVENT_CATEGORY_NAME_ABTESTING = 'abtesting';
    const METADATA_PLUGIN_NAME = 'AbTesting';
    const METADATA_NEW_VISIT = 'NEW_VISIT';
    const METADATA_ABORT_REQUEST = 'ABORT_REQUEST';

    /**
     * @var LogTable
     */
    private $logTable;

    /**
     * @var Utils
     */
    private $utils;

    public function __construct(LogTable $logTable, Utils $utils)
    {
        $this->logTable = $logTable;
        $this->utils = $utils;
    }

    public function manipulateRequest(Request $request)
    {
        list($experimentName, $variationName) = $this->utils->getExperimentAndVarationName($request);

        if (!empty($experimentName)) {

            // TODO if someone does not provide a valid experiment name, we will still log it as event action instead as it
            // was maybe not meant for abtesting. Also this allows to "correct" values if needed if eg experiment name
            // was logged with wrong name as it will be visible in event report. Or should we simply do nothing in such
            // a case?

            $experiment = $this->utils->getExperiment($experimentName, $request->getIdSite());
            $variationId = $this->utils->getMatchingVariationId($experiment, $variationName);

            if (!empty($experiment) && isset($variationId)
                && $this->utils->isRunningExperiment($request, $experiment)) {

                $this->setIsExperimentRequest($request, $experiment, $variationId);

                // the values were sanitized before, if later a value will be accessed via getParams() it would be
                // sanitized aga    in. This way we make sure to not sanitize the value over and over again
                $experimentName = Common::unsanitizeInputValue($experimentName);
                $variationName = Common::unsanitizeInputValue($variationName);

                // we rewrite event tracking call to abtesting call, this way we make sure to not record an event
                $request->setParam(ActionAbTesting::PARAM_ABTESTING_EXPERIMENT_NAME, $experimentName);
                $request->setParam(ActionAbTesting::PARAM_ABTESTING_VARIATION_NAME, $variationName);
            } else {
                // if experiment is not started/running or url is not allowed, should we record an event? --> NO
                // we want to abort the whole tracking request
                $this->setAbortRequest($request);
            }

            // even if experiment is not running or valid we still unset event data to make sure it won't be tracked
            $request->setParam('e_c', '');
            $request->setParam('e_n', '');
            $request->setParam('e_a', '');
        }
    }

    private function setAbortRequest(Request $request)
    {
        $request->setMetadata(static::METADATA_PLUGIN_NAME, static::METADATA_ABORT_REQUEST, true);
    }

    public function processRequestParams(VisitProperties $visitProperties, Request $request)
    {
        if ($request->getMetadata(static::METADATA_PLUGIN_NAME, static::METADATA_ABORT_REQUEST)) {
            return true;
        }
    }

    public function afterRequestProcessed(VisitProperties $visitProperties, Request $request)
    {
        if ($this->isExperimentRequest($request)) {
            $request->setMetadata('Actions', 'action', null);
            $request->setMetadata('Goals', 'goalsConverted', array());
        }
    }

    // Actions and Goals metadata might be set after this plugin's afterRequestProcessed was called, make sure to unset it
    public function onNewVisit(VisitProperties $visitProperties, Request $request)
    {
        $this->afterRequestProcessed($visitProperties, $request);

        if (!$this->isExperimentRequest($request)) {
            // we cannot record logs already here as the idvisit is not present yet. therefore we need to remember
            // it is a new visit for "recordLogs"
            $this->setIsNewVisit($request);
        }
    }

    public function onExistingVisit(&$valuesToUpdate, VisitProperties $visitProperties, Request $request)
    {
        if ($this->isExperimentRequest($request)) {
            $valuesToUpdate = array(); // we do not want to update any visitor info for such requests
        }

        $this->afterRequestProcessed($visitProperties, $request);
    }

    public function recordLogs(VisitProperties $visitProperties, Request $request)
    {
        $experiment = $request->getMetadata(static::METADATA_PLUGIN_NAME, static::METADATA_EXPERIMENT);
        $variationId = $request->getMetadata(static::METADATA_PLUGIN_NAME, static::METADATA_VARIATION_ID);

        if (!empty($experiment) && isset($variationId)) {
            
            $idVisit = $visitProperties->getProperty('idvisit');
            $idVisitor = $visitProperties->getProperty('idvisitor');
            $idSite = $request->getIdSite();
            $idExperiment = $experiment['idexperiment'];
            $serverTime = Date::factory($request->getCurrentTimestamp())->getDatetime();
            $this->logTable->record($idVisitor, $idVisit, $idSite, $idExperiment, $variationId, $entered = 1, $serverTime);

        } elseif ($this->isNewVisit($request)) {

            // here we credit the current visit to previously seen experiment variations. Later we might want to make
            // this behaviour optional or limit it to the experiments activated in the last 24 hours only
            $idSite = $request->getIdSite();
            $experiments = $this->utils->getCachedExperiments($idSite);

            $ids = array();
            foreach ($experiments as $experiment) {
                if ($experiment['status'] === Experiments::STATUS_RUNNING) {
                    $ids[] = $experiment['idexperiment'];
                }
            }

            if (empty($ids)) {
                return;
            }

            $idVisit = $visitProperties->getProperty('idvisit');
            $idVisitor = $visitProperties->getProperty('idvisitor');
            $serverTime = Date::factory($request->getCurrentTimestamp())->getDatetime();
            $experiments = $this->logTable->getRunningExperimentsForVisitor($idVisitor, $ids, $serverTime);
            $this->logTable->enterVisitorAutomatically($experiments, $idVisitor, $idVisit, $idSite, $serverTime);
        }
    }

    private function setIsExperimentRequest(Request $request, $experiment, $variationId)
    {
        $request->setMetadata(static::METADATA_PLUGIN_NAME, static::METADATA_EXPERIMENT, $experiment);
        $request->setMetadata(static::METADATA_PLUGIN_NAME, static::METADATA_VARIATION_ID, $variationId);
    }

    private function isExperimentRequest(Request $request)
    {
        return $request->getMetadata(static::METADATA_PLUGIN_NAME, static::METADATA_EXPERIMENT);
    }

    private function setIsNewVisit(Request $request)
    {
        $request->setMetadata(static::METADATA_PLUGIN_NAME, static::METADATA_NEW_VISIT, 1);
    }
    
    private function isNewVisit(Request $request)
    {
        return $request->getMetadata(static::METADATA_PLUGIN_NAME, static::METADATA_NEW_VISIT);
    }

}
