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
 * @link    https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

namespace Piwik\Plugins\SearchEngineKeywordsPerformance;

use Piwik\Common;
use Piwik\Date;
use Piwik\Nonce;
use Piwik\Notification;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\MissingClientConfigException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\MissingOAuthConfigException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Provider\Google as ProviderGoogle;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Provider\Bing as ProviderBing;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Provider\ProviderAbstract;
use Piwik\Plugins\WebsiteMeasurable\Type as WebsiteMeasurableType;
use Piwik\Site;
use Piwik\Url;

class Controller extends \Piwik\Plugin\ControllerAdmin
{
    public function index()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $viewVariables              = [];
        $viewVariables['providers'] = [
            ProviderGoogle::getInstance(),
            ProviderBing::getInstance()
        ];

        foreach ($viewVariables['providers'] as $provider) {
            $this->showNotificationIfNoWebsiteConfigured($provider);
        }

        return $this->renderTemplate('index', $viewVariables);
    }

    private function showNotificationIfNoWebsiteConfigured(ProviderAbstract $provider)
    {
        if (!$provider->isConfigured()) {
            return;
        }

        if (count($provider->getConfiguredSiteIds()) == 0) {
            $notification          = new Notification(Piwik::translate('SearchEngineKeywordsPerformance_NoWebsiteConfiguredWarning',
                $provider->getName()));
            $notification->context = Notification::CONTEXT_WARNING;
            Notification\Manager::notify($provider->getId() . 'nowebsites', $notification);
        }

        $errors = $provider->getConfigurationProblems();

        if (count($errors['sites'])) {
            $notification          = new Notification(Piwik::translate('SearchEngineKeywordsPerformance_ProviderXSitesWarning',
                [$provider->getName()]));
            $notification->context = Notification::CONTEXT_WARNING;
            $notification->raw     = true;
            Notification\Manager::notify($provider->getId() . 'siteswarning', $notification);
        }

        if (count($errors['accounts'])) {
            $notification          = new Notification(Piwik::translate('SearchEngineKeywordsPerformance_ProviderXAccountWarning',
                [$provider->getName()]));
            $notification->context = Notification::CONTEXT_WARNING;
            $notification->raw     = true;
            Notification\Manager::notify($provider->getId() . 'accountwarning', $notification);
        }
    }

    /*****************************************************************************************
     * Configuration actions for Google provider
     */

    /**
     * Show Google configuration page
     *
     * @param bool $hasOAuthError indicates if a oAuth access error occurred
     * @return string
     */
    public function configureGoogle($hasOAuthError = false)
    {
        Piwik::checkUserHasSomeAdminAccess();

        $configSaved = $this->configureGoogleClientIfProvided();

        if (true === $configSaved) {
            $notification          = new Notification(Piwik::translate('SearchEngineKeywordsPerformance_ClientConfigImported'));
            $notification->context = Notification::CONTEXT_SUCCESS;
            Notification\Manager::notify('clientConfigSaved', $notification);
        } else if (false === $configSaved) {
            $notification          = new Notification(Piwik::translate('SearchEngineKeywordsPerformance_ClientConfigSaveError'));
            $notification->context = Notification::CONTEXT_ERROR;
            Notification\Manager::notify('clientConfigSaved', $notification);
        }

        $googleClient = ProviderGoogle::getInstance()->getClient();

        // try to configure client (which imports provided client configs)
        try {
            $googleClient->getConfiguredClient('');
        } catch (\Exception $e) {
            // ignore errors
        }

        $clientConfigured = true;

        try {
            ProviderGoogle::getInstance()->getClient()->getConfiguredClient('');
        } catch (MissingClientConfigException $e) {
            $clientConfigured = false;
        } catch (MissingOAuthConfigException $e) {
            // ignore missing accounts
        }

        $this->addGoogleSiteConfigIfProvided();
        $this->removeGoogleSiteConfigIfProvided();
        $this->removeGoogleAccountIfProvided();

        $urlOptions = [];
        $accounts   = $googleClient->getAccounts();

        foreach ($accounts as $id => &$account) {
            $userInfo                     = $googleClient->getUserInfo($id);
            $urls                         = $googleClient->getAvailableUrls($id, false);
            $account['picture']           = $userInfo['picture'];
            $account['name']              = $userInfo['name'];
            $account['urls']              = $urls;
            $account['created_formatted'] = Date::factory(date('Y-m-d',
                $account['created']))->getLocalized(Date::DATE_FORMAT_LONG);
            try {
                $googleClient->testConfiguration($id);
            } catch (\Exception $e) {
                $account['hasError'] = $e->getMessage();
            }

            foreach ($googleClient->getAvailableUrls($id) as $url => $status) {
                // do not allow to add new property sets or android apps   @todo remove in april
                if (strpos($url, 'sc-set:') !== 0 && strpos($url, 'android-app:') !== 0) {
                    $property = strpos($url, 'sc-domain:') === 0 ? Piwik::translate('SearchEngineKeywordsPerformance_Domain') : (strpos($url, 'http') === 0 ? Piwik::translate('SearchEngineKeywordsPerformance_URLPrefix') : '');
                    $property = $property ? (' ['.$property.'] ') : '';
                    $urlOptions[$id . '##' . $url] = str_replace('sc-domain:', '', $url) .$property.' (' . $account['name'] . ')';
                }
            }
        }

        $viewVariables                          = [];
        $viewVariables['isConfigured']          = $googleClient->isConfigured();
        $viewVariables['authUrl']               = $clientConfigured ? ProviderGoogle::getInstance()->getClient()->createAuthUrl() : '';
        $viewVariables['clientId']              = $googleClient->getClientId();
        $viewVariables['clientSecret']          = preg_replace('/\w/', '*', $googleClient->getClientSecret());
        $viewVariables['isClientConfigured']    = $clientConfigured;
        $viewVariables['isClientConfigurable']  = true;
        $viewVariables['isOAuthConfigured']     = count($accounts) > 0;
        $viewVariables['accounts']              = $accounts;
        $viewVariables['urlOptions']            = $urlOptions;
        $viewVariables['hasOAuthError']         = $hasOAuthError;
        $viewVariables['configuredMeasurables'] = ProviderGoogle::getInstance()->getConfiguredSiteIds();
        $viewVariables['nonce']                 = Nonce::getNonce('SEKP.google.config');
        $viewVariables['sitesInfos']            = [];

        $siteIds = $viewVariables['configuredMeasurables'];

        foreach ($siteIds as $siteId => $config) {
            $googleSiteUrl                        = $config['googleSearchConsoleUrl'];
            $viewVariables['sitesInfos'][$siteId] = Site::getSite($siteId);
            $lastRun                              = Option::get('GoogleImporterTask_LastRun_' . $siteId);

            if ($lastRun) {
                $lastRun = date('Y-m-d H:i', $lastRun) . ' UTC';
            } else {
                $lastRun = Piwik::translate('General_Never');
            }

            $viewVariables['sitesInfos'][$siteId]['lastRun'] = $lastRun;

            list($accountId, $url) = explode('##', $googleSiteUrl);

            try {
                $viewVariables['sitesInfos'][$siteId]['accountValid'] = $googleClient->testConfiguration($accountId);
            } catch (\Exception $e) {
                $viewVariables['sitesInfos'][$siteId]['accountValid'] = false;
            }

            $urls = $googleClient->getAvailableUrls($accountId);

            $viewVariables['sitesInfos'][$siteId]['urlValid'] = key_exists($url, $urls);

            // Property Sets and Apps are deprecated and will be removed, so warn users why it doesn't work anymore
            // @todo can be removed in august 2019
            if(strpos($url, 'sc-set:') === 0) {
                $notification          = new Notification('You are using a property set for importing. Property sets have been deprecated/removed by Google. To ensure no error occurs, please choose another site for import');
                $notification->context = Notification::CONTEXT_ERROR;
                Notification\Manager::notify($siteId.'srcset', $notification);
                $viewVariables['sitesInfos'][$siteId]['urlValid'] = false;
            }
            if(strpos($url, 'android-app:') === 0) {
                $notification          = new Notification('You are using a Android App for importing. Importing Android Apps has been deprecated/removed by Google. To ensure no error occurs, please choose another site for import');
                $notification->context = Notification::CONTEXT_ERROR;
                Notification\Manager::notify($siteId.'app', $notification);
                $viewVariables['sitesInfos'][$siteId]['urlValid'] = false;
            }
        }

        return $this->renderTemplate('google\configuration', $viewVariables);
    }

    /**
     * Save Google client configuration if set in request
     *
     * @return bool|null  bool on success or failure, null if not data present in request
     */
    protected function configureGoogleClientIfProvided()
    {
        $googleClient = ProviderGoogle::getInstance()->getClient();

        $config = Common::getRequestVar('client', '');

        if (empty($config) && !empty($_FILES['clientfile'])) {

            if (!empty($_FILES['clientfile']['error'])) {
                return false;
            }

            $file = $_FILES['clientfile']['tmp_name'];
            if (!file_exists($file)) {
                return false;
            }

            $config = file_get_contents($_FILES['clientfile']['tmp_name']);
        }

        if (!empty($config)) {
            Nonce::checkNonce('SEKP.google.config', Common::getRequestVar('config_nonce'));
            try {
                $config = Common::unsanitizeInputValue($config);
                return $googleClient->setClientConfig($config);
            } catch (\Exception $e) {
                return false;
            }
        }

        return null;
    }

    /**
     * Save google configuration for a site if given in request
     */
    protected function addGoogleSiteConfigIfProvided()
    {
        $googleSiteId        = Common::getRequestVar('googleSiteId', '');
        $googleAccountAndUrl = Common::getRequestVar('googleAccountAndUrl', '');
        $googleTypes         = explode(',', Common::getRequestVar('googleTypes', ''));

        if (!empty($googleSiteId) && !empty($googleAccountAndUrl)) {
            // Do not allow to configure websites with unsupported type or force enabled config
            if (SearchEngineKeywordsPerformance::isGoogleForceEnabled($googleSiteId) || WebsiteMeasurableType::ID !== Site::getTypeFor($googleSiteId)) {
                $notification          = new Notification(
                    Piwik::translate('SearchEngineKeywordsPerformance_WebsiteTypeUnsupported', [
                        Site::getNameFor($googleSiteId)
                    ])
                );

                if (class_exists('\Piwik\Plugins\RollUpReporting\Type') && \Piwik\Plugins\RollUpReporting\Type::ID === Site::getTypeFor($googleSiteId)) {
                    $notification->message .= '<br />' . Piwik::translate('SearchEngineKeywordsPerformance_WebsiteTypeUnsupportedRollUp');
                }

                $notification->context = Notification::CONTEXT_ERROR;
                $notification->raw     = true;
                $notification->flags   = Notification::FLAG_CLEAR;
                Notification\Manager::notify('websiteNotConfigurable', $notification);

                return;
            }

            $measurableSettings = new MeasurableSettings($googleSiteId);
            $measurableSettings->googleSearchConsoleUrl->setValue($googleAccountAndUrl);
            $measurableSettings->googleWebKeywords->setValue(in_array('web', $googleTypes));
            $measurableSettings->googleImageKeywords->setValue(in_array('image', $googleTypes));
            $measurableSettings->googleVideoKeywords->setValue(in_array('video', $googleTypes));
            $measurableSettings->save();

            $notification          = new Notification(
                Piwik::translate('SearchEngineKeywordsPerformance_WebsiteSuccessfulConfigured', [
                    Site::getNameFor($googleSiteId),
                    '<a href="https://matomo.org/faq/search-engine-keywords-performance/">',
                    '</a>'
                ])
            );
            $notification->context = Notification::CONTEXT_SUCCESS;
            $notification->raw     = true;
            $notification->flags   = Notification::FLAG_CLEAR;
            Notification\Manager::notify('websiteConfigured', $notification);
        }
    }

    /**
     * Removes a Google account if `remove` param is given in request
     */
    protected function removeGoogleAccountIfProvided()
    {
        $remove = Common::getRequestVar('remove', '');

        if (!empty($remove)) {
            ProviderGoogle::getInstance()->getClient()->removeAccount($remove);

            $sitesWithConfig = ProviderGoogle::getInstance()->getConfiguredSiteIds();
            foreach ($sitesWithConfig as $siteId => $siteConfig) {
                $googleSetting = explode('##', $siteConfig['googleSearchConsoleUrl']);
                if (!empty($googleSetting[0]) && $googleSetting[0] == $remove) {
                    $config = new MeasurableSettings($siteId);
                    $config->googleSearchConsoleUrl->setValue('0');
                    $config->save();
                }
            }
        }
    }
    /**
     * Removes a Google site config if `removeConfig` param is given in request
     */
    protected function removeGoogleSiteConfigIfProvided()
    {
        $removeConfig = Common::getRequestVar('removeConfig', '');
        if (!empty($removeConfig)) {
            $measurableSettings = new MeasurableSettings($removeConfig);
            $measurableSettings->googleSearchConsoleUrl->setValue('0');
            $measurableSettings->save();
        }
    }

    /**
     * Processes the response from google oauth service
     *
     * @return string
     * @throws \Exception
     */
    public function processAuthCode()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $error     = Common::getRequestVar('error', '');
        $oauthCode = Common::getRequestVar('code', '');

        if ($error) {
            return $this->configureGoogle(true);
        }

        try {
            ProviderGoogle::getInstance()->getClient()->processAuthCode($oauthCode);
        } catch (\Exception $e) {
            return $this->configureGoogle($e->getMessage());
        }

        // reload index action to prove everything is configured
        Url::redirectToUrl(Url::getCurrentUrlWithoutQueryString() . Url::getCurrentQueryStringWithParametersModified([
                'action' => 'configureGoogle',
                'code'   => null
            ]));
    }
    /******************************************************************************************
     *****************************************************************************************/

    /*****************************************************************************************
     *****************************************************************************************
     * Configuration actions for Bing provider
     */

    /**
     * Show configuration page for Bing
     *
     * @return string
     */
    public function configureBing()
    {
        Piwik::checkUserHasSomeAdminAccess();

        $viewVariables           = [];
        $viewVariables['apikey'] = '';
        $bingClient              = ProviderBing::getInstance()->getClient();

        $apiKey = Common::getRequestVar('apikey', '');

        if (!empty($apiKey)) {
            Nonce::checkNonce('SEKP.bing.config', Common::getRequestVar('config_nonce'));
            try {
                $bingClient->testConfiguration($apiKey);
                $bingClient->addAccount($apiKey, Piwik::getCurrentUserLogin());
            } catch (\Exception $e) {
                $viewVariables['error']  = $e->getMessage();
                $viewVariables['apikey'] = $apiKey;
            }

        }

        $this->addBingSiteConfigIfProvided();
        $this->removeBingSiteConfigIfProvided();
        $this->removeBingAccountIfProvided();

        $urlOptions = [];
        $accounts   = $bingClient->getAccounts();
        foreach ($accounts as &$account) {
            $account['urls']              = [];
            $account['created_formatted'] = Date::factory(date('Y-m-d',
                $account['created']))->getLocalized(Date::DATE_FORMAT_LONG);
            try {
                $bingClient->testConfiguration($account['apiKey']);
            } catch (\Exception $e) {
                $account['hasError'] = $e->getMessage();
                continue;
            }

            $account['urls'] = $bingClient->getAvailableUrls($account['apiKey'], false);

            foreach ($bingClient->getAvailableUrls($account['apiKey']) as $url => $status) {
                $urlOptions[$account['apiKey'] . '##' . $url] = $url . ' (' . substr($account['apiKey'], 0,
                        5) . '*****' . substr($account['apiKey'], -5, 5) . ')';
            }
        }

        $viewVariables['nonce']                 = Nonce::getNonce('SEKP.bing.config');
        $viewVariables['accounts']              = $accounts;
        $viewVariables['urlOptions']            = $urlOptions;
        $viewVariables['configuredMeasurables'] = ProviderBing::getInstance()->getConfiguredSiteIds();
        $viewVariables['sitesInfos']            = [];

        $siteIds = $viewVariables['configuredMeasurables'];

        foreach ($siteIds as $siteId => $config) {
            $viewVariables['sitesInfos'][$siteId] = Site::getSite($siteId);
            $lastRun                              = Option::get('BingImporterTask_LastRun_' . $siteId);

            if ($lastRun) {
                $lastRun = date('Y-m-d H:i', $lastRun) . ' UTC';
            } else {
                $lastRun = Piwik::translate('General_Never');
            }

            $viewVariables['sitesInfos'][$siteId]['lastRun'] = $lastRun;

            $bingSiteUrl = $config['bingSiteUrl'];
            list($apiKey, $url) = explode('##', $bingSiteUrl);

            try {
                $viewVariables['sitesInfos'][$siteId]['accountValid'] = $bingClient->testConfiguration($apiKey);
            } catch (\Exception $e) {
                $viewVariables['sitesInfos'][$siteId]['accountValid'] = false;
            }

            $urls = $bingClient->getAvailableUrls($apiKey);

            $viewVariables['sitesInfos'][$siteId]['urlValid'] = key_exists($url, $urls);
        }

        return $this->renderTemplate('bing\configuration', $viewVariables);
    }

    /**
     * Save Bing configuration for a site if given in request
     */
    protected function addBingSiteConfigIfProvided()
    {
        $bingSiteId        = Common::getRequestVar('bingSiteId', '');
        $bingAccountAndUrl = Common::getRequestVar('bingAccountAndUrl', '');

        if (!empty($bingSiteId) && !empty($bingAccountAndUrl)) {
            // Do not allow to configure websites with unsupported type or force enabled config
            if (SearchEngineKeywordsPerformance::isGoogleForceEnabled($bingSiteId) || WebsiteMeasurableType::ID !== Site::getTypeFor($bingSiteId)) {
                $notification          = new Notification(
                    Piwik::translate('SearchEngineKeywordsPerformance_WebsiteTypeUnsupported', [
                        Site::getNameFor($bingSiteId)
                    ])
                );

                if (class_exists('\Piwik\Plugins\RollUpReporting\Type') && \Piwik\Plugins\RollUpReporting\Type::ID === Site::getTypeFor($bingSiteId)) {
                    $notification->message .= '<br />' . Piwik::translate('SearchEngineKeywordsPerformance_WebsiteTypeUnsupportedRollUp');
                }

                $notification->context = Notification::CONTEXT_ERROR;
                $notification->raw     = true;
                $notification->flags   = Notification::FLAG_CLEAR;
                Notification\Manager::notify('websiteNotConfigurable', $notification);

                return;
            }

            $measurableSettings = new MeasurableSettings($bingSiteId);
            $measurableSettings->bingSiteUrl->setValue($bingAccountAndUrl);
            $measurableSettings->save();

            $notification          = new Notification(
                Piwik::translate('SearchEngineKeywordsPerformance_WebsiteSuccessfulConfigured', [
                    Site::getNameFor($bingSiteId),
                    '<a href="https://matomo.org/faq/search-engine-keywords-performance/">',
                    '</a>'
                ])
            );
            $notification->context = Notification::CONTEXT_SUCCESS;
            $notification->raw     = true;
            $notification->flags   = Notification::FLAG_CLEAR;
            Notification\Manager::notify('websiteConfigured', $notification);

        }

    }


    /**
     * Removes a Bing account if `remove` param is given in request
     */
    protected function removeBingAccountIfProvided()
    {
        $remove = Common::getRequestVar('remove', '');

        if (!empty($remove)) {
            ProviderBing::getInstance()->getClient()->removeAccount($remove);

            $sitesWithConfig = ProviderBing::getInstance()->getConfiguredSiteIds();
            foreach ($sitesWithConfig as $siteId => $siteConfig) {
                $bingSetting = explode('##', $siteConfig['bingSiteUrl']);
                if (!empty($bingSetting[0]) && $bingSetting[0] == $remove) {
                    $config = new MeasurableSettings($siteId);
                    $config->bingSiteUrl->setValue('0');
                    $config->save();
                }
            }
        }
    }

    /**
     * Removes a Bing site config if `removeConfig` param is given in request
     */
    protected function removeBingSiteConfigIfProvided()
    {
        $removeConfig = Common::getRequestVar('removeConfig', '');

        if (!empty($removeConfig)) {
            $measurableSettings = new MeasurableSettings($removeConfig);
            $measurableSettings->bingSiteUrl->setValue('0');
            $measurableSettings->save();
        }
    }
    /******************************************************************************************
     *****************************************************************************************/
}
