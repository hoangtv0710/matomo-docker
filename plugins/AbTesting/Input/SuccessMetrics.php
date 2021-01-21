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

class SuccessMetrics
{
    /**
     * @var array
     */
    private $successMetrics;

    /**
     * @var array
     */
    private $availableMetrics;

    public function __construct($availableMetrics, $successMetrics)
    {
        $this->availableMetrics = $availableMetrics;
        $this->successMetrics = $successMetrics;
    }

    public function check()
    {
        $titlePlural = 'AbTesting_SuccessMetrics';
        $titleSingular = 'AbTesting_SuccessMetric';

        if (!is_array($this->successMetrics)) {
            $titlePlural = Piwik::translate($titlePlural);
            throw new Exception(Piwik::translate('AbTesting_ErrorNotAnArray', $titlePlural));
        }

        if (empty($this->successMetrics)) {
            $titlePlural = Piwik::translate($titlePlural);
            throw new Exception(Piwik::translate('AbTesting_ErrorXNotProvided', $titlePlural));
        }

        foreach ($this->successMetrics as $index => $successMetric) {
            if (!is_array($successMetric)) {
                $titlePlural = Piwik::translate($titlePlural);
                $titleSingular = Piwik::translate($titleSingular);
                throw new Exception(Piwik::translate('AbTesting_ErrorInnerIsNotAnArray', array($titleSingular, $titlePlural)));
            }

            if (!array_key_exists('metric', $successMetric)) {
                $titleSingular = Piwik::translate($titleSingular);
                throw new Exception(Piwik::translate('AbTesting_ErrorArrayMissingKey', array('metric', $titleSingular, $index)));
            }

            $this->checkSuccessMetric($successMetric['metric']);
        }
    }

    private function checkSuccessMetric($successMetric)
    {
        foreach ($this->availableMetrics as $metric) {
            if ($metric['value'] == $successMetric) {
                return true;
            }
        }

        $titleSingular = 'AbTesting_SuccessMetric';
        $titleSingular = Piwik::translate($titleSingular);

        throw new Exception(Piwik::translate('AbTesting_ErrorInvalidValue', array($titleSingular, $successMetric)));
    }

}