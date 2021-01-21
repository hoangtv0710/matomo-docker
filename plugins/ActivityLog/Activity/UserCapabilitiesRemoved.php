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

use Piwik\Access\CapabilitiesProvider;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Site;
use Piwik\Plugins\SitesManager\API AS SitesManagerAPI;
use Piwik\Plugins\UsersManager\Model AS UsersModel;

class UserCapabilitiesRemoved extends Activity
{
    protected $eventName = 'API.UsersManager.removeCapabilities.end';

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
        // $finalAPIParameters[parameters] = [ userLogin, capabilities, idSites ]

        if ($finalAPIParameters['parameters']['idSites'] === 'all') {
            $idSites = SitesManagerAPI::getInstance()->getSitesIdWithAdminAccess();
        } // in case the idSites is an integer we build an array
        else {
            $idSites = Site::getIdSitesFromIdSitesString($finalAPIParameters['parameters']['idSites']);
        }

        $userModel = new UsersModel();
        $user = $userModel->getUser($finalAPIParameters['parameters']['userLogin']);

        $return = [
            'items'      => [
                [
                    'type' => 'user',
                    'data' => [
                        'login' => $user['login'],
                        'email' => $user['email'],
                        'alias' => $user['alias']
                    ]
                ]
            ]
        ];

        foreach ($idSites as $idSite) {
            $return['items'][] = [
                'type' => 'measurable',
                'data' => [
                    'id'   => $idSite,
                    'name' => Site::getNameFor($idSite),
                    'type' => Site::getTypeFor($idSite),
                    'urls' => SitesManagerAPI::getInstance()->getSiteUrlsFromId($idSite)
                ]
            ];
        }

        $capabilityProvider = StaticContainer::get(CapabilitiesProvider::class);
        $capabilities       = $finalAPIParameters['parameters']['capabilities'];

        if (!is_array($capabilities)){
            $capabilities = array($capabilities);
        }

        foreach ($capabilities as $entry) {
            $cap = $capabilityProvider->getCapability($entry);

            $return['items'][] = [
                'type' => 'capability',
                'data' => [
                    'id'          => $cap->getId(),
                    'category'    => $cap->getCategory(),
                    'name'        => $cap->getName(),
                    'description' => $cap->getDescription()
                ]
            ];
        }

        return $return;
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
        return Piwik::translate('ActivityLog_UserCapabilitiesRemoved');
    }

    /**
     * Returns the parameters stored in the given activity data
     *
     * Before returning the data try to get translated values for category, name and description
     *
     * @param array $activityData
     * @return array
     */
    public function getParameters($activityData)
    {
        $parameters = parent::getParameters($activityData);

        $capabilityProvider = StaticContainer::get(CapabilitiesProvider::class);

        foreach ($parameters['items'] as &$item) {
            if ('capability' !== $item['type']) {
                continue;
            }

            $cap = $capabilityProvider->getCapability($item['data']['id']);

            if (!empty($cap)) {
                $item['data'] = [
                    'id'          => $cap->getId(),
                    'category'    => $cap->getCategory(),
                    'name'        => $cap->getName(),
                    'description' => $cap->getDescription()
                ];
            }
        }

        return $parameters;
    }
}