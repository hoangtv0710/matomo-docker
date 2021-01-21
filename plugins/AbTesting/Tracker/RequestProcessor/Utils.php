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
namespace Piwik\Plugins\AbTesting\Tracker\RequestProcessor;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\AbTesting\Actions\ActionAbTesting;
use Piwik\Plugins\AbTesting\Model\Experiments;
use Piwik\Plugins\AbTesting\Tracker\RequestProcessor;
use Piwik\Plugins\AbTesting\Tracker\Schedule;
use Piwik\Plugins\AbTesting\Tracker\Target;
use Piwik\Tracker\Request;
use Piwik\Tracker;

class Utils extends Tracker\RequestProcessor
{
    public function getExperimentAndVarationName(Request $request)
    {
        if ($request->getParam('e_c') == RequestProcessor::EVENT_CATEGORY_NAME_ABTESTING) {
            $experimentName = $request->getParam('e_a');
            $variation = $request->getParam('e_n');
        } else {
            $experimentName = $this->getExperimentName($request);
            $variation = $this->getVariationName($request);
        }

        if ((empty($variation) || strtolower($variation) === RequestProcessor::VARIATION_NAME_ORIGINAL) && !empty($experimentName)) {
            // we accept both an empty string / no variation name or the word "original"
            $variation = RequestProcessor::VARIATION_ORIGINAL_ID;
        }

        return array($experimentName, $variation);
    }

    public function getExperiment($experimentName, $idSite)
    {
        if (empty($experimentName)) {
            return;
        }

        $experimentName = strtolower($experimentName);

        $experiments = $this->getCachedExperiments($idSite);

        foreach ($experiments as $experiment) {
            if (strtolower($experiment['name']) === $experimentName) {

                return $experiment;
            }
        }

        // when nothing matches per experiment name we try to find a match by idexperiment. We do not test at the same
        // time for experiment name or idexperiment above as there could be an experiment having eg a name "5". In this
        // case we match by name first. We might need to change this behaviour but it will be important to consistently
        // match in such a case by name or by id and not randomly
        $isNumericExperimentName = is_numeric($experimentName) && strpos($experimentName, '.') === false;

        if ($isNumericExperimentName) {
            $experimentName = (int) $experimentName;

            foreach ($experiments as $experiment) {
                if ((int)$experiment['idexperiment'] === $experimentName) {

                    return $experiment;
                }
            }

        }
    }

    public function getMatchingVariationId($experiment, $variationName)
    {
        if (empty($experiment['variations']) || false === $variationName || null === $variationName) {
            return null;
        }

        if ($variationName === RequestProcessor::VARIATION_ORIGINAL_ID) {
            return RequestProcessor::VARIATION_ORIGINAL_ID;
        }

        $variationName = strtolower($variationName);

        foreach ($experiment['variations'] as $variation) {
            if (strtolower($variation['name']) === $variationName) {

                return $variation['idvariation'];
            }
        }

        $isNumericVariationName = is_numeric($variationName) && strpos($variationName, '.') === false;

        if ($isNumericVariationName) {
            $variationName = (int) $variationName;

            foreach ($experiment['variations'] as $variation) {
                if ($variationName === (int) $variation['idvariation']) {

                    return $variation['idvariation'];
                }
            }
        }

        return null;
    }

    public function getExperimentName(Request $request)
    {
        // we cannot use getParam() as it is a custom parameter and it would throw an exception
        $params = $request->getParams();

        return Common::getRequestVar(ActionAbTesting::PARAM_ABTESTING_EXPERIMENT_NAME, '', 'string', $params);
    }

    public function getVariationName(Request $request)
    {
        // we cannot use getParam() as it is a custom parameter and it would throw an exception
        $params = $request->getParams();

        return Common::getRequestVar(ActionAbTesting::PARAM_ABTESTING_VARIATION_NAME, '', 'string', $params);
    }

    public function isRunningExperiment(Request $request, $experiment)
    {
        $schedule = new Schedule($experiment['start_date'], $experiment['end_date']);

        $matchesTimestamp = $schedule->matchesTimestamp($request->getCurrentTimestamp());

        if ($matchesTimestamp) {
            return true;
        }

        $isNotScheduled = empty($experiment['start_date']);
        $canBeStarted = $experiment['status'] === Experiments::STATUS_CREATED;

        if ($isNotScheduled && $canBeStarted) {
            // start experiment
            $experiments = StaticContainer::get('Piwik\Plugins\AbTesting\Model\Experiments');
            $experiments->startExperiment($experiment['idexperiment'], $experiment['idsite']);

            return true;
        }

        return false;
    }

    /**
     * @param int $idSite
     * @return array
     * @throws \Piwik\Exception\UnexpectedWebsiteFoundException
     */
    public function getCachedExperiments($idSite)
    {
        $cache = Tracker\Cache::getCacheWebsiteAttributes($idSite);

        if (empty($cache['experiments'])) {
            return array();
        }

        return $cache['experiments'];
    }
}
