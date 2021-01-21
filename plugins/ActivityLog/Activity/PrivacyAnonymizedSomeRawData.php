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
namespace Piwik\Plugins\ActivityLog\Activity;

use Piwik\Piwik;
use Piwik\Url;

class PrivacyAnonymizedSomeRawData extends Activity
{
    protected $eventName = 'API.PrivacyManager.anonymizeSomeRawData.end';

    /**
     * Returns data to be used for logging the event
     *
     * @param array $eventData Array of data passed to postEvent method
     * @return array
     */
    public function extractParams($eventData)
    {
        list($empty, $finalAPIParameters) = $eventData;

        // $finalAPIParameters = [ className, module, action, parameters ]
        // $finalAPIParameters[parameters] = [ idSites, date, anonymizeIp, anonymizeLocation, anonymizeUserId, unsetVisitColumns, unsetLinkVisitActionColumns ]

        return [
            'items' => [
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'idSites',
                        'value' => $finalAPIParameters['parameters']['idSites']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'date',
                        'value' => $finalAPIParameters['parameters']['date']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'anonymizeIp',
                        'value' => !!$finalAPIParameters['parameters']['anonymizeIp']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'anonymizeLocation',
                        'value' => !!$finalAPIParameters['parameters']['anonymizeLocation']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'anonymizeUserId',
                        'value' => !!$finalAPIParameters['parameters']['anonymizeUserId']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'unsetVisitColumns',
                        'value' => $finalAPIParameters['parameters']['unsetVisitColumns']
                    ]
                ],
                [
                    'type' => 'setting',
                    'data' => [
                        'name'  => 'unsetLinkVisitActionColumns',
                        'value' => $finalAPIParameters['parameters']['unsetLinkVisitActionColumns']
                    ]
                ],
            ]
        ];
    }

    /**
     * Returns the translated description of the logged event
     *
     * @param array $activityData
     * @param string $performingUser
     * @return string
     */
    public function getTranslatedDescription($activityData, $performingUser)
    {
        return Piwik::translate('ActivityLog_PrivacyAnonymizedSomeRawData');
    }
}
