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

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\UsersManager\Model as UserModel;
use Piwik\Url;
use Exception;

/**
 * Class LoginSaml is main plugin class. It contains events mapping and custom vendors loading.
 *
 * @codeCoverageIgnore
 * @package Piwik\Plugins\LoginSaml
 */
class LoginSaml extends Plugin
{
    /**
     * @param null $pluginName
     */
    public function __construct($pluginName = null)
    {
        $this->loadVendors();

        parent::__construct($pluginName);
    }

    public function registerEvents()
    {
        return $this->getListHooksRegistered();
    }

    /**
     * Returns a list of hooks with associated event observers.
     *
     * Derived classes should use this method to associate callbacks with events.
     *
     * @return array
     */
    public function getListHooksRegistered()
    {
        return array(
            'AssetManager.getJavaScriptFiles'        => 'getJavaScriptFiles',
            'Template.loginNav'                      => 'loginNav',
            'Controller.Login.login'                 => 'dispatchLoginAction',
            'Controller.Login.logout'                => array(
                'before' => true,
                'function' => 'dispatchLogoutAction'
            ),
            'Controller.LoginLdap.logout'            => array(
                'before' => true,
                'function' => 'dispatchLogoutAction'
            ),
            'Controller.LoginHttpAuth.logout'        => array(
                'before' => true,
                'function' => 'dispatchLogoutAction'
            ),
            'API.Request.dispatch'                   => 'onApiRequestDispatch',
            'Request.dispatch'                       => 'onRequestDispatch',
            'Login.authenticate.successful'          => 'onLoginDone',
        );
    }

    /**
     * Add plugin javascript files to Matomo assets.
     *
     * @param array &$jsFiles
     */
    public function getJavaScriptFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/LoginSaml/angularjs/admin/admin.controller.js";
    }

    /**
     * Add link to SSO login page into Matomo login screen.
     *
     * @param string &$content
     * @param string $position
     */
    public function loginNav(&$content, $position)
    {
        $samlLoginUrl = "index.php?module=LoginSaml&action=singleSignOn";

        $referer = Url::getReferrer();
        if ($referer === false) {
            $referer = Url::getCurrentUrl();
        }
        if (Url::isLocalUrl($referer) &&
            strpos($referer, 'Login') === false &&
            strpos($referer, 'Logout') === false) {
            $samlLoginUrl .= "&target=".urlencode($referer);
        }

        if (Config::isForceSamlEnabled() && !isset($_GET['normal']) && (!isset($_GET['action']) || $_GET['action'] != 'confirmResetPassword')) {
            if (!empty($_GET['samlErrorMessage'])) {
                echo '<script>$("#login_form").hide();</script>';
                echo '<div piwik-notification noclear="true" context="error">'.htmlspecialchars($_GET['samlErrorMessage'], ENT_QUOTES, 'UTF-8').'</div>';
                echo '<a class="btn pull-right" href="'.$samlLoginUrl.'">SAML Login</a><br>';
                exit();
            } else if (empty($_POST) || empty($_POST["form_login"]) || empty($_POST["form_password"])) {
                Url::redirectToUrl($samlLoginUrl);
            }
        } else {
            switch ($position) {
                case 'top':
                    if (!empty($_GET['samlErrorMessage'])) {
                        $content .= '<div piwik-notification noclear="true" context="error">'.htmlspecialchars($_GET['samlErrorMessage'], ENT_QUOTES, 'UTF-8').'</div>';
                    }
                    if (Config::isSamlEnabled()) {
                        // Here is the point where I can force the SAML SSO
                        $content .= '<a class="btn pull-right" href="'.$samlLoginUrl.'">SAML Login</a>';
                    }
                    break;
            }
        }
    }

    /**
     * Reroute login screen error message in redirection.
     *
     * @param array &$parameters
     */
    public function dispatchLoginAction(&$parameters)
    {
        if (!empty($_GET['errorMessage'])) {
            $parameters['messageNoAccess'] = urldecode($_GET['errorMessage']);
        }
    }

    public function dispatchLogoutAction(&$parameters)
    {
        if (Config::isSamlEnabled() && Config::isSamlSLOEnabled()) {
            if (!empty($_SESSION['saml_data']['saml_login'])) {
                $sloUrlToRedirect = Url::getCurrentUrlWithoutFileName().'index.php?module=LoginSaml&action=singleLogOut';
                Url::redirectToUrl($sloUrlToRedirect);
            }
        }
    }

    public function onApiRequestDispatch(&$parameters, $pluginName, $methodName)
    {
        $forceSAMLEnabled = Config::isForceSamlEnabled();
        $loggedUser = Piwik::getCurrentUserLogin();
        if ($pluginName == 'UsersManager' && $forceSAMLEnabled) {
            if ($methodName == 'updateUser' && !empty($parameters['password'])) {
                // Disable the ability to change password (and email)
                if (!Piwik::hasTheUserSuperUserAccess($loggedUser)) {
                    throw new Exception(Piwik::translate('LoginSaml_ForceSamlOnlySuperusersCanChangePassword'));
                }
            }
        }
    
        $optionsIdentifyField = Config::samlUsersIdentifiedBy();
        if ($optionsIdentifyField != 'username') {
            // Prevent the ability to change mail if it is used as identifier
            if ($methodName == 'updateUser' && !empty($parameters['email'])) {
                if (Piwik::getCurrentUserEmail() != $parameters['email'] && !Piwik::hasTheUserSuperUserAccess($loggedUser)) {
                    throw new Exception(Piwik::translate('LoginSaml_OnlySuperusersCanChangeEmail'));
                }
            }
        }
    
    }

    public function onRequestDispatch($pluginName, $methodName, &$parameters)
    {
        $forceSAMLEnabled = Config::isForceSamlEnabled();
        if ($forceSAMLEnabled) {
            if ($pluginName == 'Login') {
                if (in_array($methodName, array('resetPasswordFirstStep','resetPassword', 'confirmResetPassword'))) {
                    $login = isset($_POST['form_login'])? $_POST['form_login'] : $_GET['login'];
                    if (empty($login) || !$this->checkIfLoginIsSuperUser($login)) {
                        throw new Exception("Reset Password is not allowed when Force SAML is enabled. Action only allowed for Super Users");
                    }
                } if ($methodName === 'logme') {
                    throw new Exception("Automatic login is not allowed when Force SAML is enabled.");
                }
            }
        }
    }

    // important to do this after a successful login otherwise unauthenticated users could tell if a user was
    // a superuser or not
    public function onLoginDone($login)
    {
        $forceSAMLEnabled = Config::isForceSamlEnabled();
        if (!$forceSAMLEnabled) {
            return;
        }

        if (!isset($_GET['normal'])) {
            return;
        }

        $userModel = new UserModel();
        $user = $userModel->getUser($login);
        if (empty($user)) {
            $user = $userModel->getUserByEmail($login);
        }

        if (!$this->checkIfLoginIsSuperUser($login)) {
            throw new Exception("Only Super Users can login using the normal method.");
        }
    }

    private function checkIfLoginIsSuperUser($login)
    {
        $userModel = new UserModel();
        $user = $userModel->getUser($login);
        if (empty($user)) {
            $user = $userModel->getUserByEmail($login);
        }

        $isSuperuser = true;
        if (!empty($user) && (!isset($user['superuser_access']) || $user['superuser_access'] != 1)) {
            $isSuperuser = false;
        }

        return $isSuperuser;
    }

    /**
     * Load plugin vendors.
     */
    private function loadVendors()
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }
}
