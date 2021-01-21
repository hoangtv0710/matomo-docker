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

use Piwik\AuthResult;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\Model;

class SamlSessionInitializer extends \Piwik\Session\SessionInitializer
{
    /**
     * Authenticates the user and, if successful, initializes an authenticated session.
     *
     * @param \Piwik\Auth $auth The Auth implementation to use.
     * @param bool $rememberMe Whether the authenticated session should be remembered after
     *                         the browser is closed or not.
     * @param array $samlData SAML data to be stored in session
     * @throws \Exception If authentication fails or the user is not allowed to login for some reason.
     */
    public function initSession(\Piwik\Auth $auth, array $samlData = null)
    {
        $this->regenerateSessionId();

        $authResult = $this->doAuthenticateSession($auth);

        if (!$authResult->wasAuthenticationSuccessful()) {
            Piwik::postEvent('Login.authenticate.failed', array($auth->getLogin()));

            $this->processFailedSession();
        } else {
            Piwik::postEvent('Login.authenticate.successful', array($auth->getLogin()));

            $this->processSuccessfulSession($authResult, $samlData);
        }
    }

    protected function doAuthenticateSession(\Piwik\Auth $auth)
    {
        if (empty($auth->getLogin())) {
            return new AuthResult(AuthResult::FAILURE, "", null);
        }

        Piwik::postEvent(
            'Login.authenticate',
            array(
                $auth->getLogin(),
            )
        );
        $userModel = new Model();
        $user = $userModel->getUser($auth->getLogin());
        if (empty($user)) {
            return new AuthResult(AuthResult::FAILURE, $auth->getLogin(), null);
        }

        $isSuperUser = (int) $user['superuser_access'];
        $code = $isSuperUser ? AuthResult::SUCCESS_SUPERUSER_AUTH_CODE : AuthResult::SUCCESS;

        return new AuthResult($code, $user['login'], $user['token_auth']);
    }

    /**
     * Executed when the session was successfully authenticated.
     *
     * @param AuthResult $authResult The successful authentication result.
     * @param array $samlData SAML data to be stored in session
     */
    protected function processSuccessfulSession(\Piwik\AuthResult $authResult, array $samlData = null)
    {
        parent::processSuccessfulSession($authResult);

        if (!empty($samlData)) {
            $_SESSION['saml_data'] = $samlData;
        }

        if (Config::isSamlSyncSessionExpirationEnabled()) {
            if (isset($samlData['session_expiration']) && !empty($samlData['session_expiration'])) {
                $_SESSION['session.info']['expiration'] = $samlData['session_expiration'];
            }
        }
    }
}
