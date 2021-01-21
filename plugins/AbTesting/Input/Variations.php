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

class Variations
{
    /**
     * @var array
     */
    private $variations;

    public function __construct($variations)
    {
        $this->variations = $variations;
    }

    public function check()
    {
        $title = 'AbTesting_Variations';

        if (!is_array($this->variations)) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorNotAnArray', $title));
        }

        if (empty($this->variations)) {
            $title = Piwik::translate($title);
            throw new Exception(Piwik::translate('AbTesting_ErrorXNotProvided', $title));
        }

        foreach ($this->variations as $index => $variation) {
            $variation = new Variation($variation, $index);
            $variation->check();
        }
    }

}