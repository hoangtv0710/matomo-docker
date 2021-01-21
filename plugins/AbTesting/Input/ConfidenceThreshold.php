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

class ConfidenceThreshold
{
    private $confidenceThreshold;

    public function __construct($confidenceThreshold)
    {
        $this->confidenceThreshold = $confidenceThreshold;
    }

    public function check()
    {
        $title = 'AbTesting_ConfidenceThreshold';

        if (empty($this->confidenceThreshold)) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXNotProvided', $title));
        }

        if (!is_numeric($this->confidenceThreshold)) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXNotANumber', array($title)));
        }

        $allowed = array(
            90, 95, 98, 99, 99.5
        );

        if (!in_array($this->confidenceThreshold, $allowed)) {
            $title = Piwik::translate($title);
            $whitelisted = implode(', ', $allowed);
            throw new Exception(Piwik::translate('AbTesting_ErrorXNotWhitelisted', array($title, $whitelisted)));
        }
    }

}