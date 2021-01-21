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

class MinimumDetectableEffect
{
    private $mdeRelative;

    public function __construct($mdeRelative)
    {
        $this->mdeRelative = $mdeRelative;
    }

    public function check()
    {
        $title = 'AbTesting_MinimumDetectableEffectMDE';

        if (empty($this->mdeRelative) && $this->mdeRelative !== '0' && $this->mdeRelative !== 0) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXNotProvided', $title));
        }

        if (!is_numeric($this->mdeRelative)) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXNotANumber', array($title)));
        }

        if ($this->mdeRelative < 1) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXTooLow', array($title, 1)));
        }

        if ($this->mdeRelative > 10000) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXTooHigh', array($title, 1000)));
        }
    }

}