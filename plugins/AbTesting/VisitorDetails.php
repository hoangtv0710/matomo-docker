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

use Piwik\Piwik;
use Piwik\Plugins\AbTesting\Dao\Experiment;
use Piwik\Plugins\AbTesting\Dao\LogTable;
use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\View;

class VisitorDetails extends VisitorDetailsAbstract
{
    protected static $experimentsCache = [];

    protected static $visitorExperimentsCache = [];

    public function provideActionsForVisitIds(&$actions, $visitIds)
    {
        // prefetch all experiments for $visitIds
        $this->loadExperimentsForVisitIds($visitIds);
    }

    public function extendVisitorDetails(&$visitor)
    {
        if (!array_key_exists($visitor['idVisit'], self::$visitorExperimentsCache)) {
            $this->loadExperimentsForVisitIds([$visitor['idVisit']]);
        }

        $visitor['experiments'] = self::$visitorExperimentsCache[$visitor['idVisit']];
    }

    protected function loadExperimentsForVisitIds($visitIds)
    {
        $logTableDao = new LogTable();

        $visitIds = array_map('intval', $visitIds);

        $experiments = $logTableDao->getRecordsForIdVisits($visitIds);

        foreach ($visitIds as $visitId) {
            self::$visitorExperimentsCache[$visitId] = [];
        }

        while (is_array($experiments) && count($experiments)) {
            $experiment = array_shift($experiments);

            $experimentData = $this->getExperiment($experiment['idexperiment'], $experiment['idsite']);

            $variation = false;

            if (!$experiment['idvariation']) {
                $variation = [
                    'idvariation' => 0,
                    'name' => Piwik::translate('AbTesting_NameOriginalVariation')
                ];
            } else {
                foreach ($experimentData['variations'] as $expVariation) {
                    if ($expVariation['idvariation'] == $experiment['idvariation']) {
                        $variation = [
                            'idvariation' => $expVariation['idvariation'],
                            'name' => $expVariation['name']
                        ];
                        break;
                    }
                }
            }

            self::$visitorExperimentsCache[$experiment['idvisit']][] = [
                'idexperiment' => $experimentData['idexperiment'],
                'name'         => $experimentData['name'],
                'variation'    => $variation
            ];
        }
    }

    protected function getExperiment($id, $idSite)
    {
        $key = $id.'-'.$idSite;
        if (!array_key_exists($key, self::$experimentsCache)) {
            $experimentDao = new Experiment();
            self::$experimentsCache[$key] = $experimentDao->getExperiment($id, $idSite);
        }

        return self::$experimentsCache[$key];
    }

    public function renderIcons($visitorDetails)
    {
        if (empty($visitorDetails['experiments'])) {
            return '';
        }

        $view         = new View('@AbTesting/_visitorLogIcons');
        $view->experiments = $visitorDetails['experiments'];
        return $view->render();
    }
}
