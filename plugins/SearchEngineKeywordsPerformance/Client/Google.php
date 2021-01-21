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
namespace Piwik\Plugins\SearchEngineKeywordsPerformance\Client;

use Piwik\Config;
use Piwik\Date;
use Piwik\Piwik;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Configuration\Google as Configuration;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\InvalidClientConfigException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\InvalidCredentialsException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\MissingClientConfigException;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\MissingOAuthConfigException;
use Piwik\Log;
use Piwik\Plugins\SearchEngineKeywordsPerformance\Exceptions\UnknownAPIException;
use Piwik\Url;

class Google
{
    /**
     * @var \Google_Client
     */
    protected $googleClient = null;

    /**
     * @var Configuration
     */
    protected $configuration = null;

    /**
     * Google constructor.
     *
     * @param Configuration  $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;

    }

    /**
     * @return \Google_Client
     */
    protected function getGoogleClient()
    {
        $googleClient = new \Google_Client();
        $googleClient->addScope(\Google_Service_Webmasters::WEBMASTERS_READONLY);
        $googleClient->addScope(\Google_Service_Oauth2::USERINFO_PROFILE);
        $googleClient->setAccessType('offline');
        $googleClient->setApprovalPrompt('force');
        $redirectUrl = Url::getCurrentUrlWithoutQueryString() . '?module=SearchEngineKeywordsPerformance&action=processAuthCode';
        $googleClient->setRedirectUri($redirectUrl);

        $proxyHost = Config::getInstance()->proxy['host'];

        if ($proxyHost) {
            $proxyPort     = Config::getInstance()->proxy['port'];
            $proxyUser     = Config::getInstance()->proxy['username'];
            $proxyPassword = Config::getInstance()->proxy['password'];

            if ($proxyUser) {
                $proxy = sprintf('http://%s:%s@%s:%s', $proxyUser, $proxyPassword, $proxyHost, $proxyPort);
            } else {
                $proxy = sprintf('http://%s:%s', $proxyHost, $proxyPort);
            }
            $httpClient = new \GuzzleHttp\Client([
                'proxy'      => $proxy,
                'exceptions' => false,
                'base_uri'   => \Google_Client::API_BASE_PATH
            ]);
            $googleClient->setHttpClient($httpClient);
        }

        return $googleClient;
    }

    /**
     * Passes through a direct call to the \Google_Client class
     *
     * @param string $method
     * @param array  $params
     * @return mixed
     * @throws MissingClientConfigException
     * @throws MissingOAuthConfigException
     */
    public function __call($method, $params = [])
    {
        return call_user_func_array([$this->getConfiguredClient('', true), $method], $params);
    }

    /**
     * Process the given auth code to gain access and refresh token from google api
     *
     * @param string $authCode
     * @throws MissingClientConfigException
     */
    public function processAuthCode($authCode)
    {
        try {
            $client = $this->getConfiguredClient('');
        } catch (MissingOAuthConfigException $e) {
            // ignore missing oauth config
        }

        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        $userInfo = $this->getUserInfoByAccessToken($accessToken);
        $id       = $userInfo->getId();

        $this->addAccount($id, $accessToken, Piwik::getCurrentUserLogin());
        Piwik::postEvent('SearchEngineKeywordsPerformance.AccountAdded', [
            [
                'provider' => \Piwik\Plugins\SearchEngineKeywordsPerformance\Provider\Google::getInstance()->getName(),
                'account'  => $userInfo->getName()
            ]
        ]);
    }

    /**
     * Sets the client configuration
     *
     * @param $config
     * @return boolean
     */
    public function setClientConfig($config)
    {
        try {
            $client      = $this->getGoogleClient();
            $configArray = @json_decode($config, true);
            $this->configureClient($client, $configArray);
        } catch (\Exception $e) {
            return false;
        }

        $this->configuration->setClientConfig($config);
        Piwik::postEvent('SearchEngineKeywordsPerformance.GoogleClientConfigChanged');
        return true;
    }

    /**
     * @param \Google_Client $client
     * @param array          $config
     *
     * @throws MissingClientConfigException
     */
    protected function configureClient($client, $config)
    {
        try {
            @$client->setAuthConfig($config);
        } catch (\Exception $e) {
            throw new MissingClientConfigException();
        }

        // no client config available
        if (!$client->getClientId() || !$client->getClientSecret()) {
            throw new MissingClientConfigException();
        }
    }


    /**
     * Returns configured client api keys
     *
     * @return array
     */
    public function getAccounts()
    {
        return $this->configuration->getAccounts();
    }

    /**
     * Removes client api key
     *
     * @param $id
     * @return bool
     */
    public function removeAccount($id)
    {
        $userInfo = $this->getUserInfo($id);
        $this->configuration->removeAccount($id);
        Piwik::postEvent('SearchEngineKeywordsPerformance.AccountRemoved', [
            [
                'provider' => \Piwik\Plugins\SearchEngineKeywordsPerformance\Provider\Google::getInstance()->getName(),
                'account'  => $userInfo['name']
            ]
        ]);
        return true;
    }

    /**
     * Adds a client api key
     *
     * @param $id
     * @param $config
     * @param $username
     * @return boolean
     */
    public function addAccount($id, $accessToken, $username)
    {
        $userInfo = $this->getUserInfoByAccessToken($accessToken);

        $config = [
            'userInfo'    => [
                'picture' => $userInfo->picture,
                'name'    => $userInfo->name,
            ],
            'accessToken' => $accessToken
        ];

        $this->configuration->addAccount($id, $config, $username);
        return true;
    }

    /**
     * Returns if client is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        return ($this->configuration->getClientConfig() && count($this->configuration->getAccounts()) > 0);
    }

    /**
     * Returns configured Google_Client object
     *
     * @param string $accessToken
     * @param bool   $ignoreMissingConfigs
     * @return \Google_Client
     * @throws MissingClientConfigException
     * @throws MissingOAuthConfigException
     */
    public function getConfiguredClient($accessToken, $ignoreMissingConfigs = false)
    {
        $client = $this->getGoogleClient();
        try {
            $this->configure($client, $accessToken);
        } catch (\Exception $e) {
            if (!$ignoreMissingConfigs) {
                throw $e;
            }
        }
        return $client;
    }

    /**
     * Loads configuration and sets common configuration for Google_Client
     *
     * @param \Google_Client $client
     * @param string         $accessToken
     * @throws MissingOAuthConfigException
     * @throws MissingClientConfigException
     */
    protected function configure($client, $accessToken)
    {
        // import shipped client config if available
        if (!$this->configuration->getClientConfig()) {
            $this->configuration->importShippedClientConfigIfAvailable();
        }

        $this->configureClient($client, $this->configuration->getClientConfig());

        try {
            $client->setAccessToken($accessToken);
        } catch (\Exception $e) {
            throw new MissingOAuthConfigException($e->getMessage());
        }
    }

    public function getUserInfo($accountId)
    {
        return $this->configuration->getUserInfo($accountId);
    }

    protected function getUserInfoByAccessToken($accessToken)
    {
        $service = new \Google_Service_Oauth2($this->getConfiguredClient($accessToken));
        return $service->userinfo->get();
    }

    /**
     * Checks if account can be used to query the API
     *
     * @param string $accountId
     * @return bool
     * @throws \Exception
     */
    public function testConfiguration($accountId)
    {
        $accessToken = $this->configuration->getAccessToken($accountId);

        try {
            $service = new \Google_Service_Webmasters($this->getConfiguredClient($accessToken));
            $service->sites->listSites();
        } catch (\Exception $e) {
            $this->handleServiceException($e);
            throw $e;
        }

        return true;
    }

    /**
     * Returns the urls keyword data is available for (in connected google account)
     *
     * @param string $accountId
     * @param bool   $removeUrlsWithoutAccess wether to return unverified urls
     * @return array
     */
    public function getAvailableUrls($accountId, $removeUrlsWithoutAccess = true)
    {
        $accessToken = $this->configuration->getAccessToken($accountId);
        $sites       = [];

        try {
            $service  = new \Google_Service_Webmasters($this->getConfiguredClient($accessToken));
            $response = $service->sites->listSites();
        } catch (\Exception $e) {
            return $sites;
        }

        foreach ($response as $site) {
            if (!$removeUrlsWithoutAccess || $site['permissionLevel'] != 'siteUnverifiedUser') {
                $sites[$site['siteUrl']] = $site['permissionLevel'];
            }
        }

        return $sites;
    }

    /**
     * Returns the search analytics data from google search console for the given parameters
     *
     * @param string $accountId
     * @param string $url   url, eg. http://matomo.org
     * @param string $date  day string, eg. 2016-12-24
     * @param string $type  'web', 'image' or 'video'
     * @param int    $limit maximum of rows to fetch
     * @return \Google_Service_Webmasters_SearchAnalyticsQueryResponse
     * @throws InvalidClientConfigException
     * @throws InvalidCredentialsException
     * @throws MissingOAuthConfigException
     * @throws MissingClientConfigException
     * @throws UnknownAPIException
     */
    public function getSearchAnalyticsData($accountId, $url, $date, $type = 'web', $limit = 500)
    {
        $accessToken = $this->configuration->getAccessToken($accountId);

        if (empty($accessToken)) {
            throw new MissingOAuthConfigException();
        }

        $limit = min($limit, 5000); // maximum allowed by API is 5.000

        // Google API is only able to handle dates in the last 3 months
        $threeMonthBefore = Date::now()->subMonth(3);
        $archivedDate     = Date::factory($date);
        if ($archivedDate->isEarlier($threeMonthBefore) || $archivedDate->isToday()) {
            Log::debug("[SearchEngineKeywordsPerformance] Skip fetching keywords from Search Console for today and dates more than 3 months in the past");
            return null;
        }

        $service = new \Google_Service_Webmasters($this->getConfiguredClient($accessToken));
        $request = new \Google_Service_Webmasters_SearchAnalyticsQueryRequest();
        $request->setStartDate($date);
        $request->setEndDate($date);
        $request->setDimensions(['query']);
        $request->setRowLimit($limit);
        $request->setSearchType($type);

        $retries = 0;
        while ($retries < 5) {
            try {
                $response = $service->searchanalytics->query($url, $request);
                return $response;
            } catch (\Exception $e) {
                $this->handleServiceException($e, $retries < 4);
                usleep(500 * $retries);
                $retries++;
            }
        }

        return null;
    }

    /**
     * Returns an array of dates where search analytics data is availabe for on search console
     *
     * @param string $accountId
     * @param string $url url, eg. http://matomo.org
     * @return array
     * @throws MissingClientConfigException
     * @throws MissingOAuthConfigException
     * @throws InvalidClientConfigException
     * @throws InvalidCredentialsException
     * @throws UnknownAPIException
     */
    public function getDatesWithSearchAnalyticsData($accountId, $url)
    {
        $accessToken = $this->configuration->getAccessToken($accountId);
        $service     = new \Google_Service_Webmasters($this->getConfiguredClient($accessToken));
        $request     = new \Google_Service_Webmasters_SearchAnalyticsQueryRequest();
        $request->setStartDate(Date::now()->subYear(1)->toString());
        $request->setEndDate(Date::now()->toString());
        $request->setDimensions(['date']);

        $retries = 0;
        while ($retries < 5) {
            try {
                $entries = $service->searchanalytics->query($url, $request);

                if (empty($entries) || !($rows = $entries->getRows())) {
                    return [];
                }

                $days = [];
                foreach ($rows as $row) {
                    /** @var \Google_Service_Webmasters_ApiDataRow $row */
                    $keys   = $keys = $row->getKeys();
                    $days[] = array_shift($keys);
                }

                return array_unique($days);
            } catch (\Exception $e) {
                $this->handleServiceException($e, $retries < 4);
                $retries++;
                usleep(500 * $retries);
            }
        }

        return [];
    }

    /**
     * @param \Exception $e
     * @param bool $ignoreUnknowns
     * @throws InvalidClientConfigException
     * @throws InvalidCredentialsException
     * @throws UnknownAPIException
     */
    protected function handleServiceException($e, $ignoreUnknowns = false)
    {
        if (!($e instanceof \Google_Service_Exception)) {
            return;
        }
        $error = json_decode($e->getMessage(), true);
        if (!empty($error['error']) && $error['error'] == 'invalid_client') {
            // invalid credentials
            throw new InvalidClientConfigException($error['error_description']);
        } elseif (!empty($error['error']['code']) && $error['error']['code'] == 401) {
            // invalid credentials
            throw new InvalidCredentialsException($error['error']['message'], $error['error']['code']);
        } elseif (!empty($error['error']['code']) && $error['error']['code'] == 403) {
            // no access for given resource (website / app)
            throw new InvalidCredentialsException($error['error']['message'], $error['error']['code']);
        } elseif (!empty($error['error']['code']) && in_array($error['error']['code'], [500, 503]) && !$ignoreUnknowns) {
            // backend or api server error
            throw new UnknownAPIException($error['error']['message'], $error['error']['code']);
        }
    }
}
