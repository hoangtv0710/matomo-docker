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
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use OneLogin\Saml2\Error;
use Piwik\Access;
use Piwik\Container\StaticContainer;
use Piwik\Common;
use Piwik\Date;
use Piwik\Plugins\UsersManager\UserUpdater;
use Piwik\Url;
use Piwik\Plugins\LoginSaml\Config;
use Piwik\Plugins\LoginSaml\SamlSessionInitializer;
use Piwik\Plugins\UsersManager\Model as UserModel;
use Piwik\Plugins\UsersManager\API as UsersManagerAPI;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\ProxyHttp;
use Piwik\SettingsPiwik;
use Piwik\Site;
use Piwik\Tracker\Cache;

/**
 * Class SamlFactory
 *
 * @package Piwik\Plugins\LoginSaml\Saml
 */
class SamlFactory
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Auth
     */
    private $samlAuth;

    /**
     * @var array
     */
    private $configData;

    /**
     * UsersManager API instance used to add and get users.
     *
     * @var \Piwik\Plugins\UsersManager\API
     */
    private $usersManagerApi;

    /**
     * UserModel instance used to access user data. We don't go through the API in
     * order to avoid thrown exceptions.
     *
     * @var UserModel
     */
    private $userModel;

    /**
     * @param Logger $logger
     */
    public function __construct($logger = null)
    {
        if ($logger !== null) {
            $this->logger = $logger;
        }
        $this->configData = Config::getPluginOptionValuesWithDefaults();

        $this->usersManagerApi = UsersManagerAPI::getInstance();
        $this->userModel = new UserModel();

        if (ProxyHttp::isHttps()) {
            Utils::setSelfProtocol('https');
        }
    }

    public function getSamlAuth()
    {
        if ($this->samlAuth === null) {
            try {
                $settingsInfo = $this->getSamlSettings();
                $this->samlAuth = new Auth($settingsInfo);
            } catch (Error $e) {
                $errorMsg = "Error initializing SAML. ".$e->getMessage();
            } catch (\Exception $e) {
                $errorMsg = "Error initializing SAML. ".$e->getMessage();
            }
            if (isset($errorMsg)) {
                $this->logger->error($errorMsg);
                throw new Error($errorMsg);
            }
        }
        return $this->samlAuth;
    }

    public function getSettings($validateSPOnly = false)
    {
        $settingsInfo = $this->getSamlSettings();
        $settings = new Settings($settingsInfo, $validateSPOnly);
        return $settings;
    }

    public function getDirectSamlMetadataUrl()
    {
        $baseUrl = Url::getCurrentUrlWithoutFileName();
        return $this->getSamlUrl('metadata').'&format=text/xml';
    }

    public function getSamlUrl($action)
    {
        $baseUrl = Url::getCurrentUrlWithoutFileName();
        $baseSamlUrl = $baseUrl . 'index.php?module=LoginSaml&action=';
        return $baseSamlUrl . $action;
    }

    /**
     * This method return Saml configuration array.
     *
     * @return array
     * @throws \Exception
     */
    public function getSamlSettings()
    {
        $configData = $this->configData;
        if (empty($configData['advanced_spentityid'])) {
            $sp_entity_id = $this->getSamlUrl('metadata');
        } else {
            $sp_entity_id = $configData['advanced_spentityid'];
        }

        $sp_acs_url = $this->getSamlUrl('assertionConsumerService');
        $sp_sls_url = $this->getSamlUrl('singleLogoutService');

        $metadata = array(
            'strict' => $configData['advanced_strict'],
            'debug' => $configData['advanced_debug'],
            'sp' => array(
                'entityId' => $sp_entity_id,
                'assertionConsumerService' => array(
                    'url' => $sp_acs_url,
                ),
                /*
                'attributeConsumingService' => array(
                    "ServiceName" => "Piwik",
                    "serviceDescription" => "Piwik. Analytics platform",
                    "requestedAttributes" => array(
                        array(
                            "name" => "uid",
                            "isRequired" => true,
                            "friendlyName" => "username"
                        ),
                        array(
                            "name" => "mail",
                            "isRequired" => true,
                            "friendlyName" => "email"
                        ),
                        array(
                            "name" => "displayname",
                            "isRequired" => true,
                            "friendlyName" => "alias"
                        )
                    )
                ),
                */
                'NameIDFormat' => $configData['advanced_nameidformat'],
                'x509cert' => $configData['advanced_sp_x509cert'],
                'privateKey' => $configData['advanced_sp_privatekey'],
            ),
            'idp' => array(
                'entityId' => $configData['idp_entityid'],
                'singleSignOnService' => array(
                    'url' => $configData['idp_sso'],
                ),
                'singleLogoutService' => array(
                    'url' => $configData['idp_slo'],
                ),
                'x509cert' => $configData['idp_x509cert']
            ),
            'security' => array(
                'nameIdEncrypted' => isset($configData['advanced_nameid_encrypted'])? (bool)$configData['advanced_nameid_encrypted'] : false,
                'authnRequestsSigned' => isset($configData['advanced_authn_request_signed'])? (bool)$configData['advanced_authn_request_signed'] : false,
                'logoutRequestSigned' => isset($configData['advanced_logout_request_signed'])? (bool)$configData['advanced_logout_request_signed'] : false,
                'logoutResponseSigned' => isset($configData['advanced_logout_response_signed'])? (bool)$configData['advanced_logout_response_signed'] : false,
                'signMetadata' => isset($configData['advanced_metadata_signed'])? (bool)$configData['advanced_metadata_signed'] : false,
                'wantMessagesSigned' => isset($configData['advanced_want_message_signed'])? (bool)$configData['advanced_want_message_signed'] : false,
                'wantAssertionsSigned' => isset($configData['advanced_want_assertion_signed'])? (bool)$configData['advanced_want_assertion_signed'] : false,
                'wantAssertionsEncrypted' => isset($configData['advanced_want_assertion_encrypted'])? (bool)$configData['advanced_want_assertion_encrypted'] : false,
                'wantNameIdEncrypted' => isset($configData['advanced_want_nameid_encrypted'])? (bool) $configData['advanced_want_nameid_encrypted'] : false,
                'requestedAuthnContext' => isset($configData['advanced_requestedauthncontext']) && $configData['advanced_requestedauthncontext'] !== 0 ? $configData['advanced_requestedauthncontext'] : false,
                'requestedAuthnContextComparison' => 'exact',
                'signatureAlgorithm' => $configData['advanced_signaturealgorithm'],
                'digestAlgorithm' => $configData['advanced_digestalgorithm'],
                'wantNameId' => false,
                'wantXMLValidation' => true,
                'relaxDestinationValidation' => true,
                'lowercaseUrlencoding' => false,
            )
        );
        if ($configData['options_enable_slo']) {
            $metadata['sp']['singleLogoutService'] = array(
                'url' => $sp_sls_url,
            );
        }

        return $metadata;
    }

    public function retrieveUserIfExists($attributes, $nameid)
    {
        $configData = $this->configData;
        $user = null;

        if (empty($configData['options_identify_field'])) {
            $configData['options_identify_field'] = 'email';
        }

        if ($configData['options_identify_field'] == 'username') {
            $userIdentifyValue = $username = $this->retrieveUsername($attributes, "SSO");
            $user = $this->userModel->getUser($username);
        } else {
            $userIdentifyValue = $email = $this->retrieveEmail($attributes, "SSO", $nameid);
            $user = $this->userModel->getUserByEmail($email);
        }

        return $user;
    }

    public function retrieveUserAndCreateIfRequired($attributes, $nameid)
    {
        $configData = $this->configData;
        $username = $email = null;
        $usersManagerApi = $this->usersManagerApi;

        if (empty($configData['options_identify_field'])) {
            $configData['options_identify_field'] = 'email';
        }

        if ($configData['options_identify_field'] == 'username') {
            $userIdentifyValue = $username = $this->retrieveUsername($attributes, "SSO");
            $user = $this->userModel->getUser($username);
        } else {
            $userIdentifyValue = $email = $this->retrieveEmail($attributes, "SSO", $nameid);
            $user = $this->userModel->getUserByEmail($email);
        }

        if (empty($user)) {
            if (!$configData['options_autocreate']) {
                throw new \Exception("User with ".$configData['options_identify_field']." ".$userIdentifyValue." does not exists and just-in-time provisioning is disabled");
            }

            if (empty($username)) {
                $username = $this->retrieveUsername($attributes, "Just-in-time provisioning");
            }
            if (empty($email)) {
                $email = $this->retrieveEmail($attributes, "Just-in-time provisioning", $nameid);
            }

            if (empty($username) || empty($email)) {
                throw new \Exception("Just-in-time provisioning error. Username ($username) or Email ($email) is empty.");
            }

            if ($this->userModel->userExists($username)) {
                throw new \Exception("Just-in-time provisioning error. Username ".$username." already exists, can't create an account for ".$email);
            }

            if ($this->userModel->userEmailExists($email)) {
                throw new \Exception("Just-in-time provisioning error. Email ".$email." already exists, can't create an account for ".$username);
            }

            $alias = $this->retrieveAlias($attributes);
            if (empty($alias)) {
                $alias = $username;
            }
            $randomPassword = md5(
                Common::generateUniqId() .
                microtime(true) .
                Common::generateUniqId() .
                SettingsPiwik::getSalt()
            );
            $randomHashedPassword = password_hash($randomPassword, PASSWORD_BCRYPT);
            $tokenAuth = $usersManagerApi->createTokenAuth($username);
            $dateRegistered = Date::now()->getDatetime();

            $this->userModel->addUser($username, $randomHashedPassword, $email, $alias, $tokenAuth, $dateRegistered);
            $user = $this->userModel->getUser($username);
            $this->logger->info("Added user ".$user['login']);
        }
        return $user;
    }

    public function synchronizePiwikAccessFromSaml($user, $attributes)
    {
        $anyAccessSynched = false;

        $piwikLogin = $user['login'];
        $usersManagerApi = $this->usersManagerApi;
        $userAccessParser = new UserAccessParser($this->configData, $this->logger, $attributes);
        $userAccess = $userAccessParser->getPiwikUserAccessForSamlUser();

        if (empty($userAccess)) {
            $this->logger->warning(
                "SamlFactory::".__FUNCTION__.": User '".$piwikLogin."' has no access in SAML, but access synchronization is enabled."
            );

            return false;
        }

        // for the synchronization, need to reset all user accesses
        $this->userModel->deleteUserAccess($piwikLogin);
        $this->userModel->setSuperUserAccess($piwikLogin, false);

        foreach ($userAccess as $userAccessLevel => $sites) {
            Access::doAsSuperUser(function () use ($usersManagerApi, $userAccessLevel, $sites, $piwikLogin) {
                if ($userAccessLevel == 'superuser') {
                    if (method_exists('Piwik\Plugins\UsersManager\UserUpdater', 'setSuperUserAccessWithoutCurrentPassword')) {
                        $userUpdater = new UserUpdater();
                        $userUpdater->setSuperUserAccessWithoutCurrentPassword($piwikLogin, true);
                    } else {
                        $usersManagerApi->setSuperUserAccess($piwikLogin, true);
                    }
                    $this->logger->info(
                        "PiwikAccess synched. User '".$piwikLogin."' is now supersuer"
                    );
                } else {
                    $usersManagerApi->setUserAccess($piwikLogin, $userAccessLevel, $sites);
                    $this->logger->info(
                        "PiwikAccess synched. Access of user '".$piwikLogin."' updated"
                    );
                }
            });
            $anyAccessSynched = true;
        }

        return $anyAccessSynched;
    }

    public function assignDefaultSitesViewAccessIfApplies($user)
    {
        // Now let manage User access
        $defaultSitesWithViewAccess = $this->configData['options_new_user_default_sites_view_access'];
        if (!empty($defaultSitesWithViewAccess)) {
            $usersManagerApi = $this->usersManagerApi;

            $siteIds = Access::doAsSuperUser(function () use ($defaultSitesWithViewAccess) {
                return Site::getIdSitesFromIdSitesString($defaultSitesWithViewAccess);
            });
            $allIds = Access::doAsSuperUser(function () use ($defaultSitesWithViewAccess) {
                return SitesManagerAPI::getInstance()->getAllSitesId();
            });
            $siteIds = array_intersect($siteIds, $allIds);
            if (empty($siteIds)) {
                $this->logger->warning("SAML settings defines invalid default sites ids '".$defaultSitesWithViewAccess."' at 'Options' section. New user ".$user['login']." will not have any access.");
            } else {
                $this->logger->info("Adding to user ".$user['login']. " view access to sites: ".join(',', $siteIds));
                Access::doAsSuperUser(function () use ($user, $usersManagerApi, $siteIds) {
                    $usersManagerApi->setUserAccess($user['login'], 'view', $siteIds);
                });
                $user = $this->userModel->getUser($user['login']);
            }
        } else {
            $this->logger->warning("SAML settings does not define default sites to provide access to new users on its 'Options' section. New user ".$user['login']." will not have any access.");
        }
    }

    /**
     * Authenticate user and reload user access.
     * At the end init user session.
     *
     * @param array $user
     * @return bool
     */
    public function authenticateAndReloadAccess(array $user, array $samlData)
    {
        $auth = StaticContainer::get('Piwik\Auth');
        $access = Access::getInstance();
        $samlSessionInitializer = new SamlSessionInitializer();

        $auth->setLogin($user['login']);
        if (isset($user['token_auth'])) {
            $auth->setTokenAuth($user['token_auth']);
        }
        if ($access->reloadAccess($auth)) {
            Cache::deleteTrackerCache();
            $samlSessionInitializer->initSession($auth, $samlData);
            $this->logger->info('User with login '.$user['login'].' authenticated in Matomo');
            return true;
        }
        return false;
    }

    private function retrieveUsername($attributes, $action)
    {
        if (empty($this->configData['attributemapping_username'])) {
            throw new \Exception("Username mapping is required in order to execute the SAML ".$action);
        }
        $usernameMapping = $this->configData['attributemapping_username'];
        if (empty($attributes[$usernameMapping])) {
            throw new \Exception("Username was not provided by the IdP and is required in order to execute the SAML ".$action);
        }
        return $attributes[$usernameMapping][0];
    }

    private function retrieveEmail($attributes, $action, $nameid)
    {
        $possible_mail = null;
        if (!empty($nameid) && strpos($nameid, '@') !== false) {
            $possible_mail = $nameid;
        }

        if (empty($this->configData['attributemapping_email'])) {
            if (empty($possible_mail)) {
                throw new \Exception("Email mapping is required in order to execute the SAML ".$action);
            } else {
                return $possible_mail;
            }
        } else {
            $emailMapping = $this->configData['attributemapping_email'];
            if (empty($attributes[$emailMapping])) {
                if (empty($possible_mail)) {
                    throw new \Exception("Email was not provided by the IdP and is required in order to execute the SAML ".$action);
                } else {
                    return $possible_mail;
                }
            } else {
                return $attributes[$emailMapping][0];
            }
        }
    }

    private function retrieveAlias($attributes)
    {
        $alias = null;
        if (!empty($this->configData['attributemapping_email'])) {
            $aliasMapping = $this->configData['attributemapping_alias'];
            if (!empty($attributes[$aliasMapping])) {
                $alias = $attributes[$aliasMapping][0];
            }
        }
        return $alias;
    }

    /**
     * Gets the {@link $usersManagerApi} property.
     *
     * @return UsersManagerAPI
     */
    public function getUsersManagerApi()
    {
        return $this->usersManagerApi;
    }

    /**
     * Sets the {@link $usersManagerApi} property.
     *
     * @param UsersManagerAPI $usersManagerApi
     */
    public function setUsersManagerApi(UsersManagerAPI $usersManagerApi)
    {
        $this->usersManagerApi = $usersManagerApi;
    }

    /**
     * Gets the {@link $userModel} property.
     *
     * @return UserModel
     */
    public function getUserModel()
    {
        return $this->userModel;
    }

    /**
     * Sets the {@link $userModel} property.
     *
     * @param UserModel $userModel
     */
    public function setUserModel($userModel)
    {
        $this->userModel = $userModel;
    }
}
