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
use Piwik\Settings\FieldConfig;
use Piwik\Site;
use Piwik\Settings\Measurable\MeasurableSettings;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

class MeasurableSettingsUpdated extends Activity
{
    protected $eventName = 'MeasurableSettings.updated';

    /**
     * Returns data to be used for logging the event
     *
     * @param array $eventData Array of data passed to postEvent method
     * @return array
     */
    public function extractParams($eventData)
    {
        /** @var MeasurableSettings $measurableSettings */
        list($measurableSettings, $idSite) = $eventData;

        $settings = $measurableSettings->getSettingsWritableByCurrentUser();

        if (empty($settings)) {
            return false; // no settings had been changed
        }

        $result = [
            'items'     => [
                [
                    'type' => 'measurable',
                    'data' => [
                        'id'   => $idSite,
                        'type' => Site::getTypeFor($idSite),
                        'name' => Site::getNameFor($idSite),
                        'urls' => SitesManagerAPI::getInstance()->getSiteUrlsFromId($idSite)
                    ]
                ]
            ]
        ];

        foreach ($settings as $setting) {
            $value = $setting->getValue();

            if (is_array($value)) {
                $value = $this->arrayFilterRecursive($value);
            }

            $fieldConfig = $setting->configureField();

            if ($fieldConfig && $fieldConfig->uiControl == FieldConfig::UI_CONTROL_PASSWORD) {
                $value = '******';
            }

            $result['items'][] = [
                'type' => 'setting',
                'data' => [
                    'name'  => $setting->getName(),
                    'value' => is_array($value) && !count($value) ? null : $value
                ]
            ];
        }

        return $result;
    }

    private function arrayFilterRecursive($input)
    {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->arrayFilterRecursive($value);
            }
            if (empty($value) && $value !== 0 && $value !== '0') {
                unset($input[$key]);
            }
        }
        return $input;
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
        return Piwik::translate('ActivityLog_UserUpdatedMeasurableSettings');
    }
}