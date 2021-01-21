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
namespace Piwik\Plugins\LoginSaml\Saml;

use Monolog\Logger;
use Piwik\Access;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\SettingsPiwik;
use Piwik\Site;

/**
 * Parses the values of SAML attributes that describe an SAML user's Matomo access.
 *
 * ### Access Attribute Format
 *
 * Access attributes can have different formats, the simplest is simply a list of IDs
 * or `'all'`, eg:
 *
 *     view: 1,2,3
 *     admin: all
 *
 * ### Managing Multiple Matomo Instances
 *
 * If the SAML server in question manages access for only a single Matomo instance, this
 * will suffice. To support multiple Matomo instances, it is allowed to identify the
 * server instance within the attributes, eg:
 *
 *     view: piwikServerA:1,2,3
 *     view: piwikServerB:1,2,3
 *     admin: piwikServerA:all;piwikServerB:2,3
 *
 * In this example, the user is granted view access for sites 1, 2 & 3 for Matomo instance
 * 'A' and Matomo instance 'B', and is granted admin access for all sites in Piwik instance 'A',
 * but only sites 2 & 3 in Matomo instance 'B'.
 *
 * As demonstrated above, instance ID/site list pairs (ie, `"piwikServerA:1,2,3"`) can be in
 * multiple values, or in a single value separated by a delimiter.
 *
 * The seaparator used to split instance ID/site list pairs and the delimiter used to
 * separate pair from other pairs can both be customized through INI config options.
 *
 * ### Identifying Matomo Instances
 *
 * In the above example, Matomo instances are identified by a special name, ie,
 * `"piwikServerA"` or `"piwikServerB"`. By default, however, instances are identified by
 * the instance's host, port and url. For example:
 *
 *     view: piwikA.myhost.com/path/to/piwik:1,2,3
 *     view: piwikB.myhost.com/path/to/piwik:all
 *     admin: piwikC.com:all
 *     superuser: piwikC.com;piwikD.com
 *
 * If you want to use a specific name, you would have to set the `[LoginSAML] instance_name`
 * INI config option for each of your Matomo instances.
 *
 * _Note: If identifying by URLs with port values, the `[LoginSAML] user\_access\_attribute\_server\_separator`
 * config option should be set to something other than `':'`._
 *
 * ### Access Attribute Flexibility
 *
 * In order to make error conditions as rare as possible, this parser has been coded
 * to be flexible when identifying instance IDs. Any malformed looking access values are
 * logged with at least DEBUG level.
 */
class UserAccessParser
{
    private $configData = array();
    private $samlAttributes = array();

    /**
     * @var Logger
     */
    private $logger = null;

     /**
     * The delimiter that separates individual instance ID/site list pairs from other pairs.
     *
     * For example, if `'#'` is used, the access attribute will be expected to be like:
     *
     *     piwikServerA:1#piwikServerB:2#piwikServerC:3
     *
     * @var string
     */
    private $serverSpecificationDelimiter = ';';

    /**
     * The separator used to separate instance IDs from site ID lists.
     *
     * For example, if `'#'` is used, the access attribute will be expected be like:
     *
     *     piwikServerA#1;piwikServerB#2,3;piwikServerC#3
     *
     * @var string
     */
    private $serverIdsSeparator = ':';

    /**
     * A special name for this Matomo instance. If not null, we check if a specification in
     * an SAML attribute value applies to this instance if the instance ID contains this value.
     *
     * If null, the instance ID is expected to be this Matomo instance's URL.
     *
     * @var string
     */
    private $thisPiwikInstanceName = null;

    /**
     * Cache for all site IDs. Set once by {@link getAllSites()}.
     *
     * Maps int site IDs w/ unspecified data.
     *
     * @var array
     */
    private $allSites = null;

    public function __construct($configData, $logger, $samlAttributes)
    {
        if (empty($configData['saml_view_access_field'])) {
            $configData['saml_view_access_field'] = 'view';
        }

        if (empty($configData['saml_admin_access_field'])) {
            $configData['saml_admin_access_field'] = 'admin';
        }

        if (empty($configData['saml_superuser_access_field'])) {
            $configData['saml_superuser_access_field'] = 'superuser';
        }

        $this->configData = $configData;
        $this->logger = $logger;
        $this->samlAttributes = $samlAttributes;
        if (!empty($configData['user_access_attribute_server_specification_delimiter'])) {
            $this->serverSpecificationDelimiter = $configData['user_access_attribute_server_specification_delimiter'];
        }
        if (!empty($configData['user_access_attribute_server_separator'])) {
            $this->serverIdsSeparator = $configData['user_access_attribute_server_separator'];
        }
        if (!empty($configData['instance_name'])) {
            $this->thisPiwikInstanceName = $configData['instance_name'];
        }
    }

    public function getPiwikUserAccessForSamlUser()
    {
        // if the user is a superuser, we don't need to check the other attributes
        if ($this->isSuperUserAccessGrantedForSamlUser()) {
            $this->logger->debug("UserAccessParser::".__FUNCTION__.": user found to be superuser");

            return array('superuser' => true);
        }

        $sitesByAccess = array();

        $viewAccessValue = $this->retrieveViewAccessValue();
        if (!empty($viewAccessValue)) {
            $this->addSiteAccess($sitesByAccess, 'view', $viewAccessValue);
        }

        $adminAccessValue = $this->retrieveAdminAccessValue();
        if (!empty($adminAccessValue)) {
            $this->addSiteAccess($sitesByAccess, 'admin', $adminAccessValue);
        }

        $accessBySite = array();
        foreach ($sitesByAccess as $site => $access) {
            $accessBySite[$access][] = $site;
        }
        return $accessBySite;
    }

    private function addSiteAccess(&$sitesByAccess, $accessLevel, $accessAttributeValues)
    {
        $this->logger->debug("UserAccessParser::".__FUNCTION__.": attribute value for ".$accessLevel." access is ".join(',', $accessAttributeValues));

        $siteIds = array();
        foreach ($accessAttributeValues as $value) {
            $siteIds = array_merge($siteIds, $this->getSiteIdsFromAccessAttribute($value));
        }

        $this->logger->debug("UserAccessParser::".__FUNCTION__.": adding ".$accessLevel." access for sites: ".join(',', $siteIds));

        $allSitesSet = $this->getSetOfAllSites();
        foreach ($siteIds as $idSite) {
            if (!isset($allSitesSet[$idSite])) {
                $this->logger->debug("UserAccessParser::".__FUNCTION__.": site [ id = ".$idSite." ] does not exist, ignoring");
                continue;
            }

            $sitesByAccess[$idSite] = $accessLevel;
        }
    }

    private function getSetOfAllSites()
    {
        if ($this->allSites === null) {
            $this->allSites = array_flip(Access::doAsSuperUser(function () {
                return SitesManagerAPI::getInstance()->getSitesIdWithAtLeastViewAccess();
            }));
        }

        return $this->allSites;
    }

    private function isSuperUserAccessGrantedForSamlUser()
    {
        $superUserattributeValue = $this->retrieveSuperUserAdminAccessValue();

        foreach ($superUserattributeValue as $value) {
            if ($this->getSuperUserAccessFromSuperUserAttribute($value)) {
                return true;
            }
        }
        return false;
    }

    public function retrieveViewAccessValue()
    {
        return $this->retrieveAccessValue('saml_view_access_field');
    }

    public function retrieveAdminAccessValue()
    {
        return $this->retrieveAccessValue('saml_admin_access_field');
    }

    public function retrieveSuperUserAdminAccessValue()
    {
        return $this->retrieveAccessValue('saml_superuser_access_field');
    }

    protected function retrieveAccessValue($config_field_name)
    {
        $accessValue = array();
        if (!empty($this->configData[$config_field_name])) {
            $accessMapping = $this->configData[$config_field_name];
            if (array_key_exists($accessMapping, $this->samlAttributes)) {
                $value = $this->samlAttributes[$accessMapping];
                if (!is_array($value)) {
                    $value = array($value);
                }
                $accessValue = $value;
            }
        }
        return $accessValue;
    }

    /**
     * Returns list of int site IDs from site list found in SAML.
     *
     * @param string $sitesSpec eg, `"1,2,3"` or `"all"`
     * @return int[]
     */
    protected function getSitesFromSitesList($sitesSpec)
    {
        return Access::doAsSuperUser(function () use ($sitesSpec) {
            return Site::getIdSitesFromIdSitesString($sitesSpec);
        });
    }

    /**
     * Returns true if an SAML access attribute value marks a user as a superuser.
     *
     * The superuser attribute doesn't need to have a site list so it just contains
     * a list of instances.
     */
    public function getSuperUserAccessFromSuperUserAttribute($attributeValue)
    {
        $attributeValue = trim($attributeValue);

        if ($attributeValue == 1
            || strtolower($attributeValue) == 'true'
            || empty($attributeValue)
        ) {
            // special case when not managing multiple Matomo instances
            return true;
        }

        $instanceIds = $this->getSuperUserInstancesFromAttribute($attributeValue);
        foreach ($instanceIds as $instanceId) {
            if ($this->isInstanceIdForThisInstance($instanceId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the list of instance IDs in a superuser access attribute value.
     *
     * @return string[]
     */
    protected function getSuperUserInstancesFromAttribute($attributeValue)
    {
        $delimiters = $this->serverIdsSeparator . $this->serverSpecificationDelimiter;
        $result = preg_split("/[" . preg_quote($delimiters) . "]/", $attributeValue);
        return array_map('trim', $result);
    }

    /**
     * Parses a SAML access attribute value and returns the list of site IDs that apply to
     * this specific Matomo instance.
     *
     * @var string $attributeValue eg `"piwikServerA:1,2,3;piwikServerB:4,5,6"`.
     * @return array
     */
    public function getSiteIdsFromAccessAttribute($attributeValue)
    {
        $result = array();

        $instanceSpecs = explode($this->serverSpecificationDelimiter, $attributeValue);
        foreach ($instanceSpecs as $spec) {
            list($instanceId, $sitesSpec) = $this->getInstanceIdAndSitesFromSpec($spec);
            if ($this->isInstanceIdForThisInstance($instanceId)) {
                $result = array_merge($result, $this->getSitesFromSitesList($sitesSpec));
            }
        }

        return $result;
    }

    /**
     * Returns the instance ID and list of sites from an instance ID/sites list pair.
     *
     * @param string $spec eg, `"piwikServerA:1,2,3"`
     * @return string[] contains two string elements
     */
    public function getInstanceIdAndSitesFromSpec($spec)
    {
        $parts = explode($this->serverIdsSeparator, $spec);

        if (count($parts) == 1) {
            $parts = array(null, $parts[0]);
        } else if (count($parts) >= 2) {
            if (count($parts) > 2) {
                $this->logger->debug("UserAccessParser::".__FUNCTION__.": Improper server specification in SAML access attribute: '".$spec."'");
            }

            $parts = array($parts[0], $parts[1]);
        }

        return array_map('trim', $parts);
    }

    /**
     * Returns true if an instance ID string found in SAML refers to this instance or not.
     *
     * If not instance ID is specified, will always return `true`.
     *
     * @param string $instanceId eg, `"piwikServerA"` or `"piwikA.mysite.com"`
     * @return bool
     */
    public function isInstanceIdForThisInstance($instanceId)
    {
        if (empty($instanceId)) {
            return true;
        }

        if (empty($this->thisPiwikInstanceName)) {
            $result = $this->isUrlThisInstanceUrl($instanceId);
        } else {
            preg_match("/\\b" . preg_quote($this->thisPiwikInstanceName) . "\\b/", $instanceId, $matches);

            if (empty($matches)) {
                $result = false;
            } else {
                if (strlen($matches[0]) != strlen($instanceId)) {
                    $this->logger->debug("UserAccessParser::".__FUNCTION__.": Found extra characters in Matomo instance ID. Whole ID entry = ".$instanceId.".");
                }

                $result = true;
            }
        }

        if ($result) {
            $this->logger->debug("UserAccessParser::".__FUNCTION__.": Matched this instance with '".$instanceId."'.");
        }

        return $result;
    }

    /**
     * Returns true if the supplied instance ID refers to this Matomo instance, false if otherwise.
     * Assumes the instance ID is the base URL to the Matomo instance.
     *
     * @param string $instanceIdUrl
     * @return bool
     */
    protected function isUrlThisInstanceUrl($instanceIdUrl)
    {
        $thisPiwikUrl = SettingsPiwik::getPiwikUrl();
        $thisPiwikUrl = $this->getNormalizedUrl($thisPiwikUrl, $isThisPiwikUrl = true);

        $instanceIdUrl = $this->getNormalizedUrl($instanceIdUrl);

        return $thisPiwikUrl == $instanceIdUrl;
    }

    private function getNormalizedUrl($url, $isThisPiwikUrl = false)
    {
        $parsed = @parse_url($url);
        if (empty($parsed)) {
            if ($isThisPiwikUrl) {
                $this->logger->warning("UserAccessParser::".__FUNCTION__.": Invalid Matomo URL found for this instance '".$url."'.");
            } else {
                $this->logger->debug("UserAccessParser::".__FUNCTION__.": Invalid instance ID URL found '".$url."'.");
            }

            return false;
        }

        if (empty($parsed['scheme']) && empty($parsed['host'])) {
            $url = 'http://' . $url;
            $parsed = @parse_url($url);
        }

        if (empty($parsed['host'])) {
            $this->logger->debug("UserAccessParser::".__FUNCTION__.": Found strange URL - '".$url."'.");
            $parsed['host'] = '';
        }

        if (!isset($parsed['port'])) {
            $parsed['port'] = 80;
        }

        if (substr(@$parsed['path'], -1) !== '/') {
            $parsed['path'] = @$parsed['path'] . '/';
        }

        return $parsed['host'] . ':' . $parsed['port'] . $parsed['path'];
    }

    /**
     * Returns the {@link $serverSpecificationDelimiter} property value.
     *
     * @return string
     */
    public function getServerSpecificationDelimiter()
    {
        return $this->serverSpecificationDelimiter;
    }

    /**
     * Sets the {@link $serverSpecificationDelimiter} property.
     *
     * @param string $serverSpecificationDelimiter
     */
    public function setServerSpecificationDelimiter($serverSpecificationDelimiter)
    {
        $this->serverSpecificationDelimiter = $serverSpecificationDelimiter;
    }

    /**
     * Returns the {@link $serverIdsSeparator} property value.
     *
     * @return string
     */
    public function getServerIdsSeparator()
    {
        return $this->serverIdsSeparator;
    }

    /**
     * Sets the {@link $serverIdsSeparator} property value.
     *
     * @param string $serverIdsSeparator
     */
    public function setServerIdsSeparator($serverIdsSeparator)
    {
        $this->serverIdsSeparator = $serverIdsSeparator;
    }

    /**
     * Returns the {@link $thisPiwikInstanceName} property value.
     *
     * @return string
     */
    public function getThisPiwikInstanceName()
    {
        return $this->thisPiwikInstanceName;
    }

    /**
     * Sets the {@link $thisPiwikInstanceName} property value.
     *
     * @param string $thisPiwikInstanceName
     */
    public function setThisPiwikInstanceName($thisPiwikInstanceName)
    {
        $this->thisPiwikInstanceName = $thisPiwikInstanceName;
    }
}
