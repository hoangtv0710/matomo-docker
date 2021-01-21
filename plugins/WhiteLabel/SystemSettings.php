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

namespace Piwik\Plugins\WhiteLabel;

use Piwik\AssetManager;
use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Piwik\SettingsPiwik;

/**
 * Defines Settings for WhiteLabel.
 *
 * Usage like this:
 * $settings = new SystemSettings();
 * $settings->brandName->getValue();
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $brandName;

    /** @var Setting */
    public $marketplaceOnlySuperUser;

    /** @var Setting */
    public $whitelabelTrackingEndpoint;

    /** @var Setting */
    public $removeLinksToMatomo;

    /** @var Setting */
    public $headerBackgroundColor;

    /** @var Setting */
    public $headerFontColor;

    protected function init()
    {
        // System setting --> allows selection of a single value
        $this->brandName = $this->createBrandNameSetting();
        $this->headerBackgroundColor = $this->createHeaderBackgroundColor();
        $this->headerFontColor = $this->createHeaderFontColor();
        $this->marketplaceOnlySuperUser = $this->createMarketplaceOnlySuperUserSetting();
        $this->whitelabelTrackingEndpoint = $this->createWhitelabelTrackingEndpoint();
        $this->removeLinksToMatomo = $this->createRemoveLinksToMatomo();
    }

    private function createBrandNameSetting()
    {
        return $this->makeSetting('brandName', $default = '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('WhiteLabel_SettingBrandNameTitle');
            $field->description = Piwik::translate('WhiteLabel_SettingBrandNameDescription');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->validate = function ($value) {
                if (!empty($value) && strlen($value) > 50) {
                    $message = Piwik::translate('WhiteLabel_SettingBrandNameMaxLen', 50);
                    throw new \Exception($message);
                }
                $blockedChars = array('>', '<', "'", '"');
                foreach ($blockedChars as $blockedChar) {
                    if (!empty($value) && strpos($value, $blockedChar) !== false) {
                        $message = Piwik::translate('WhiteLabel_SettingBrandNameInvalidChar', $blockedChar);
                        throw new \Exception($message);
                    }
                }

            };
        });
    }

    private function createMarketplaceOnlySuperUserSetting()
    {
        return $this->makeSetting('marketplaceOnlySuperUser', $default = false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('WhiteLabel_SettingMarketplaceOnlySuperUserTitle');
            $field->description = Piwik::translate('WhiteLabel_SettingMarketplaceOnlySuperUserDescription');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    private function createWhitelabelTrackingEndpoint()
    {
        return $this->makeSetting('whitelabelTrackingEndpoint', $default = false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('WhiteLabel_SettingWhitelabelTrackingEndpoint');
            $url = '"'.SettingsPiwik::getPiwikUrl() . 'js/tracker.php' . '"';
            $field->description = Piwik::translate('WhiteLabel_SettingWhitelabelTrackingEndpointDescription', $url);
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    private function createRemoveLinksToMatomo()
    {
        return $this->makeSetting('removeLinksToMatomo', $default = false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('WhiteLabel_SettingRemoveLinksToMatomo');
            $field->description = Piwik::translate('WhiteLabel_SettingRemoveLinksToMatomoDescription');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    private function createHeaderBackgroundColor()
    {
        return $this->makeSetting('headerBackgroundColor', $default = '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('WhiteLabel_SettingHeaderBackgroundColor');
            $field->description = Piwik::translate('WhiteLabel_SettingColorHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->uiControlAttributes = array('maxlength' => 7);
            $field->validate = function ($value) {
                if ($value === false || $value === '' || $value === null) {
                    return;// vald
                }
                if (substr($value, 0, 1) === '#') {
                    $value = substr($value, 1);
                }
                if (ctype_xdigit($value) && in_array(strlen($value),array(3,6), true)) {
                    return;
                }
                throw new \Exception("The header background value '$value' is not valid. Expected value is for example 'ffffff' or 'fff'.");
            };
            $field->transform = function ($value) {
                if ($value && substr($value, 0, 1) === '#') {
                    $value = substr($value, 1);
                }

                return $value;
            };
        });
    }
    
    public function save()
    {
        parent::save();
        AssetManager::getInstance()->removeMergedAssets();
    }

    private function createHeaderFontColor()
    {
        return $this->makeSetting('headerFontColor', $default = '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('WhiteLabel_SettingHeaderFontColor');
            $field->description = Piwik::translate('WhiteLabel_SettingColorHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->validate = function ($value) {
                if ($value === false || $value === '' || $value === null) {
                    return;// vald
                }
                if (substr($value, 0, 1) === '#') {
                    $value = substr($value, 1);
                }
                if (ctype_xdigit($value) && in_array(strlen($value),array(3,6), true)) {
                    return;
                }
                throw new \Exception("The header background value '$value' is not valid. Expected value is for example 'ffffff' or 'fff'.");
            };
            $field->transform = function ($value) {
                if ($value && substr($value, 0, 1) === '#') {
                    $value = substr($value, 1);
                }

                return $value;
            };
        });
    }

}
