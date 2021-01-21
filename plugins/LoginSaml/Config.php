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
namespace Piwik\Plugins\LoginSaml;

use OneLogin\Saml2\Constants;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use Piwik\Config as PiwikConfig;
use Piwik\Piwik;
use Exception;

/**
 * Utility class with methods to manage LoginSaml INI configuration.
 */
class Config
{
    public static $defaultConfig = array(
        'status' => 0,
        'idp_entityid' => '',
        'idp_sso' => '',
        'idp_slo' => '',
        'idp_x509cert' => '',
        'options_autocreate' => 0,
        'options_new_user_default_sites_view_access' => '',
        'options_identify_field' => 'email',
        'options_enable_slo' => 0,
        'options_forcesaml' => 0,
        'attributemapping_username' => '',
        'attributemapping_email' => '',
        'attributemapping_alias' => '',
        'enable_synchronize_access_from_saml' => 0,
        'sync_saml_session_expiration' => 0,
        'saml_view_access_field' => 'view',
        'saml_admin_access_field' => 'admin',
        'saml_superuser_access_field' => 'superuser',
        'user_access_attribute_server_specification_delimiter' => ';',
        'user_access_attribute_server_separator' => ':',
        'instance_name' => null,
        'advanced_strict' => 1,
        'advanced_debug' => 0,
        'advanced_spentityid' => '',
        'advanced_nameidformat' => Constants::NAMEID_UNSPECIFIED,
        'advanced_requestedauthncontext' => 0,
        'advanced_nameid_encrypted' => 0,
        'advanced_authn_request_signed' => 0,
        'advanced_logout_request_signed' => 0,
        'advanced_logout_response_signed' => 0,
        'advanced_metadata_signed' => 0,
        'advanced_want_message_signed' => 0,
        'advanced_want_assertion_signed' => 0,
        'advanced_want_assertion_encrypted' => 0,
        'advanced_want_nameid_encrypted' => 0,
        'advanced_retrieve_parameters_from_server' => 0,
        'advanced_sp_x509cert' => '',
        'advanced_sp_privatekey' => '',
        'advanced_signaturealgorithm' => XMLSecurityKey::RSA_SHA1,
        'advanced_digestalgorithm' => XMLSecurityDSig::SHA1
    );

    /**
     * Returns an INI option value that is stored in the `[LoginSaml]` config section.
     *
     * @param $optionName
     * @return mixed
     */
    public static function getConfigOption($optionName)
    {
        return self::getConfigOptionFrom(PiwikConfig::getInstance()->LoginSaml, $optionName);
    }

    public static function getConfigOptionFrom($config, $optionName)
    {
        if (isset($config[$optionName])) {
            return $config[$optionName];
        } else {
            return self::getDefaultConfigOptionValue($optionName);
        }
    }

    public static function getDefaultConfigOptionValue($optionName)
    {
        return @self::$defaultConfig[$optionName];
    }

    public static function isSamlEnabled()
    {
        $configData = self::getPluginOptionValuesWithDefaults();
        return (isset($configData['status']) && $configData['status']);
    }

    public static function isSamlSLOEnabled()
    {
        $configData = self::getPluginOptionValuesWithDefaults();
        return (isset($configData['options_enable_slo']) && $configData['options_enable_slo']);
    }

    public static function isForceSamlEnabled()
    {
        $configData = self::getPluginOptionValuesWithDefaults();
        return (isset($configData['options_forcesaml']) && $configData['options_forcesaml']);
    }

    public static function samlUsersIdentifiedBy()
    {
        $configData = self::getPluginOptionValuesWithDefaults();
        return $configData['options_identify_field'];
    }

    public static function isSamlSyncAccesEnabled()
    {
        $configData = self::getPluginOptionValuesWithDefaults();
        return (isset($configData['enable_synchronize_access_from_saml']) && $configData['enable_synchronize_access_from_saml']);
    }

    public static function isSamlSyncSessionExpirationEnabled()
    {
        $configData = self::getPluginOptionValuesWithDefaults();
        return (isset($configData['sync_saml_session_expiration']) && $configData['sync_saml_session_expiration']);
    }

    public static function getPluginOptionValuesWithDefaults()
    {
        $result = self::$defaultConfig;
        foreach ($result as $name => $ignore) {
            $actualValue = self::getConfigOption($name);

            if (isset($actualValue)) {
                $result[$name] = $actualValue;
            }
        }
        return $result;
    }

    public static function validateData(&$data)
    {
        $error = [];
        $missing = [];

        if (empty($data['idp_entityid'])) {
            $missing[] = Piwik::translate('LoginSaml_IdPEntityId');
        }

        if (!empty($data['idp_sso'])) {
            if (!filter_var($data['idp_sso'], FILTER_VALIDATE_URL)) {
                $error[] = Piwik::translate('LoginSaml_IdPSSOURL');
                unset($data['idp_sso']);
            }
        } else {
            $missing[] = Piwik::translate('LoginSaml_IdPSSOURL');
        }

        if (!empty($data['idp_slo']) && !filter_var($data['idp_slo'], FILTER_VALIDATE_URL)) {
            $error[] = Piwik::translate('LoginSaml_IdPSLOURL');
            unset($data['idp_slo']);
        }

        if (empty($data['idp_x509cert'])) {
            $missing[] = Piwik::translate('LoginSaml_IdPx509CERT');
        }

        if (!isset($data['options_identify_field']) || $data['options_identify_field'] == "email") {
            if (empty($data['attributemapping_email'])) {
                $missing[] = Piwik::translate('LoginSaml_AttributeMappingEMAIL');
            }
        } else {
            if (empty($data['attributemapping_username'])) {
                $missing[] = Piwik::translate('LoginSaml_AttributeMappingUSERNAME');
            }
        }

        if (!empty($data['advanced_authn_request_signed']) ||
            !empty($data['advanced_logout_request_signed']) ||
            !empty($data['advanced_logout_response_signed']) ||
            !empty($data['advanced_metadata_signed']) ||
            !empty($data['advanced_want_nameid_encrypted']) ||
            !empty($data['advanced_want_assertion_encrypted'])) {
            if (empty($data['advanced_sp_privatekey'])) {
                $missing[] = Piwik::translate('LoginSaml_AdvancedSPx509CERT');
            }
            if (empty($data['advanced_sp_privatekey'])) {
                $missing[] = Piwik::translate('LoginSaml_AdvancedSPPRIVATEKEY');
            }
        }
        return array('error' => $error, 'missing' => $missing);
    }

    public static function savePluginOptions($config)
    {
        $loginSaml = PiwikConfig::getInstance()->LoginSaml;

        foreach (self::$defaultConfig as $name => $value) {
            if (isset($config[$name])) {
                $loginSaml[$name] = $config[$name];
            }
        }

        PiwikConfig::getInstance()->LoginSaml = $loginSaml;
        PiwikConfig::getInstance()->forceSave();
    }

    public static function injectSamlValues($values, $resetIdPValues = false)
    {
        $loginSaml = PiwikConfig::getInstance()->LoginSaml;

        if (isset($values['sp']) && isset($values['sp']['NameIDFormat']) &&
            !empty($values['sp']['NameIDFormat'])) {
            $loginSaml['advanced_nameidformat'] = $values['sp']['NameIDFormat'];
        }

        if (isset($values['idp'])) {
            if ($resetIdPValues) {
                $loginSaml['idp_entityid'] = '';
                $loginSaml['idp_sso'] = '';
                $loginSaml['idp_slo'] = '';
                $loginSaml['idp_x509cert'] = '';
            }

            if (isset($values['idp']['entityId']) && !empty($values['idp']['entityId'])) {
                $loginSaml['idp_entityid'] = $values['idp']['entityId'];
            }
            if (isset($values['idp']['singleSignOnService']) && !empty($values['idp']['singleSignOnService'])) {
                $loginSaml['idp_sso'] = $values['idp']['singleSignOnService']['url'];
            }
            if (isset($values['idp']['singleLogoutService']) && !empty($values['idp']['singleLogoutService'])) {
                $loginSaml['idp_slo'] = $values['idp']['singleLogoutService']['url'];
            }
            if (isset($values['idp']['x509cert']) && !empty($values['idp']['x509cert'])) {
                $loginSaml['idp_x509cert'] = $values['idp']['x509cert'];
            }
        }

        PiwikConfig::getInstance()->LoginSaml = $loginSaml;
        PiwikConfig::getInstance()->forceSave();
    }

    public static function getNameIDFormatOptions()
    {
        return array(
            array('key' => Constants::NAMEID_UNSPECIFIED,
                  'value' => Constants::NAMEID_UNSPECIFIED
            ),
            array('key' => Constants::NAMEID_EMAIL_ADDRESS,
                  'value' => Constants::NAMEID_EMAIL_ADDRESS
            ),
            array('key' => Constants::NAMEID_ENCRYPTED,
                  'value' => Constants::NAMEID_ENCRYPTED
            ),
            array('key' => Constants::NAMEID_TRANSIENT,
                  'value' => Constants::NAMEID_TRANSIENT
            ),
            array('key' => Constants::NAMEID_PERSISTENT,
                  'value' => Constants::NAMEID_PERSISTENT
            ),
            array('key' => Constants::NAMEID_ENTITY,
                  'value' => Constants::NAMEID_ENTITY
            ),
            array('key' => Constants::NAMEID_KERBEROS,
                  'value' => Constants::NAMEID_KERBEROS
            ),
        );
    }

    public static function getRequestedAuthNContextOptions()
    {
        return array(
            array('key' => Constants::AC_UNSPECIFIED,
                  'value' => Constants::AC_UNSPECIFIED
            ),
            array('key' => Constants::AC_PASSWORD,
                  'value' => Constants::AC_PASSWORD

            ),
            array('key' => Constants::AC_PASSWORD_PROTECTED,
                  'value' => Constants::AC_PASSWORD_PROTECTED
            ),
            array('key' => Constants::AC_X509,
                  'value' => Constants::AC_X509
            ),
            array('key' => Constants::AC_SMARTCARD,
                  'value' => Constants::AC_SMARTCARD
            ),
            array('key' => Constants::AC_KERBEROS,
                  'value' => Constants::AC_KERBEROS
            ),
            array('key' => Constants::AC_WINDOWS,
                  'value' => Constants::AC_WINDOWS
            ),
            array('key' => Constants::AC_TLS,
                  'value' => Constants::AC_TLS
            ),
        );
    }

    public static function getSignatureAlgorithmOptions()
    {
        return array(
            array('key' => XMLSecurityKey::RSA_SHA1,
                  'value' => 'RSA_SHA1'
            ),
            array('key' => XMLSecurityKey::RSA_SHA256,
                  'value' => 'RSA_SHA256'
            ),
            array('key' => XMLSecurityKey::RSA_SHA384,
                  'value' => 'RSA_SHA384'
            ),
            array('key' => XMLSecurityKey::RSA_SHA512,
                  'value' => 'RSA_SHA512'
            ),
            array('key' => XMLSecurityKey::DSA_SHA1,
                  'value' => 'DSA_SHA1'
            )
        );
    }

    public static function getDigestalgorithmOptions()
    {
        return array(
            array('key' => XMLSecurityDSig::SHA1,
                  'value' => 'SHA1'
            ),
            array('key' => XMLSecurityDSig::SHA256,
                  'value' => 'SHA256'
            ),
            array('key' => XMLSecurityDSig::SHA384,
                  'value' => 'SHA384'
            ),
            array('key' => XMLSecurityDSig::SHA512,
                  'value' => 'SHA512'
            )
        );
    }

    public static function getIdentifyFieldOptions()
    {
        return array(
            array('key' => 'username',
                  'value' => 'username'
            ),
            array('key' => 'email',
                  'value' => 'email'
            )
        );
    }
}
