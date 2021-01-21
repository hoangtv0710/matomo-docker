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

namespace Piwik\Plugins\AbTesting\Input;

use \Exception;
use Piwik\Piwik;

class SuccessMetricInExperiment
{
    /**
     * @var array
     */
    private $selectedSuccessMetrics;

    /**
     * @var string
     */
    private $successMetric;

    public function __construct($selectedSuccessMetrics, $successMetric)
    {
        $this->selectedSuccessMetrics = $selectedSuccessMetrics;
        $this->successMetric = $successMetric;
    }

    public function check()
    {
        foreach ($this->selectedSuccessMetrics as $selectedSuccessMetric) {
            if (!empty($selectedSuccessMetric['metric']) && $selectedSuccessMetric['metric'] === $this->successMetric) {
                return;
            }
        }

        $title = Piwik::translate('AbTesting_SuccessMetric');
        throw new Exception(Piwik::translate('AbTesting_ErrorNotEnabledForExperiment', $title));
    }


}