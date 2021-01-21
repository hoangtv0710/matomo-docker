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

namespace Piwik\Plugins\AbTesting\Actions;

use Piwik\Common;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;

class ActionAbTesting extends Action
{
    const TYPE_ABTESTING = 93;
    const PARAM_ABTESTING_EXPERIMENT_NAME = 'ab_e';
    const PARAM_ABTESTING_VARIATION_NAME = 'ab_n';

    public function __construct(Request $request)
    {
        parent::__construct(static::TYPE_ABTESTING, $request);

        $url = $request->getParam('url');

        $this->setActionUrl($url);
    }

    public static function shouldHandle(Request $request)
    {
        $params = $request->getParams();
        $experimentName = Common::getRequestVar(static::PARAM_ABTESTING_EXPERIMENT_NAME, '', 'string', $params);

        return $experimentName && strlen($experimentName) > 0 && $request->getMetadata('AbTesting', 'experiment');
    }

    protected function getActionsToLookup()
    {
        return array();
    }

    // Do not track this Event URL as Entry/Exit Page URL (leave the existing entry/exit)
    public function getIdActionUrlForEntryAndExitIds()
    {
        return false;
    }

    // Do not track this Event Name as Entry/Exit Page Title (leave the existing entry/exit)
    public function getIdActionNameForEntryAndExitIds()
    {
        return false;
    }
}
