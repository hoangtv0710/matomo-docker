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

use Monolog\Logger;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\IdPMetadataParser;
use Piwik\Config as PiwikConfig;
use Piwik\Container\StaticContainer;
use Piwik\Notification;
use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\Url;
use Piwik\Version;
use Piwik\View;
use \Exception;

/**
 * Login controller
 *
 * @package Login
 */
class Controller extends \Piwik\Plugins\Login\Controller
{
    /**
     * @var Saml\SamlFactory
     */
    private $samlFactory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Saml\SamlFactory $samlFactory
     * @param Logger $logger
     */
    public function __construct(Saml\SamlFactory $samlFactory = null, $logger = null)
    {
        parent::__construct();
        $this->logger = $logger ?: StaticContainer::get('Piwik\Plugins\LoginSaml\Logger');
        if ($samlFactory === null) {
            $samlFactory = new Saml\SamlFactory($this->logger);
        }
        $this->samlFactory = $samlFactory;
    }

    /**
     * Configure action is end-point for administration page.
     *
     * @return string
     */
    public function admin()
    {
        Piwik::checkUserHasSuperUserAccess();
        $view = new View('@LoginSaml/index');

        ControllerAdmin::setBasicVariablesAdminView($view);

        $this->setBasicVariablesView($view);

        $view->currentVersion = Version::VERSION;
        $view->ifForceSamlNotSupported = version_compare(Version::VERSION, '3.6.1', '<');
        $view->samlConfig = Config::getPluginOptionValuesWithDefaults();
        $view->identifyFieldOptions = Config::getIdentifyFieldOptions();
        $view->nameidformatOptions = Config::getNameIDFormatOptions();
        $view->requestedauthncontextOptions = Config::getRequestedAuthNContextOptions();
        $view->signaturealgorithmOptions = Config::getSignatureAlgorithmOptions();
        $view->digestalgorithmOptions = Config::getDigestalgorithmOptions();

        return $view->render();
    }

    /**
     * Present SP metadata XML page.
     */
    public function metadata()
    {
        $showAdminView = false;
        if (!isset($_GET['format']) || $_GET['format'] != "text/xml") {
            $showAdminView = true;
            Piwik::checkUserHasSuperUserAccess();
            $view = new View('@LoginSaml/metadata');
            ControllerAdmin::setBasicVariablesAdminView($view);
            $this->setBasicVariablesView($view);
        }

        try {
            $settings = $this->samlFactory->getSettings(true);
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);
            if (!empty($errors)) {
                throw new \Exception(
                    'Invalid SP metadata: '.implode(', ', $errors)
                );
            }

            if ($showAdminView) {
                $spData = $settings->getSPData();
                $view->spEntityid = $spData['entityId'];
                $view->acs = $spData['assertionConsumerService']['url'];
                if (isset($spData['singleLogoutService']['url'])) {
                    $view->sls = $spData['singleLogoutService']['url'];
                }
                $x509cert = $settings->getSPcert();
                if (!empty($x509cert)) {
                    $view->x509cert = $x509cert;
                }

                $view->metadata = htmlspecialchars_decode($metadata, ENT_QUOTES);
                $view->metadataUrl = $this->samlFactory->getDirectSamlMetadataUrl();
            } else {
                header('Content-Type: text/xml');
                echo $metadata;
            }
        } catch (Exception $e) {
            $this->logExceptionMessage($e);
            if ($showAdminView) {
                $view->metadataError = $e->getMessage();
            } else {
                throw $e;
            }
        }

        if ($showAdminView) {
            return $view->render();
        }
    }

    /**
     * Import IdP metadata view.
     *
     * @return string
     */
    public function importmetadata()
    {
        Piwik::checkUserHasSuperUserAccess();
        $view = new View('@LoginSaml/import');

        ControllerAdmin::setBasicVariablesAdminView($view);
        $this->setBasicVariablesView($view);
        return $view->render();
    }

    /**
     * SP-Initiated SSO SAML flow
     */
    public function singleSignOn()
    {
        if (Config::isSamlEnabled()) {
            $samlAuth = $this->samlFactory->getSamlAuth();
            $this->logger->info('Initiated the Single Sign On, Redirecting to the IdP');

            if (isset($_GET['target']) && Url::isLocalUrl($_GET['target'])) {
                $samlAuth->login($_GET['target']);
            } else {
                $samlAuth->login();
            }
        } else {
            $this->redirectToLoginWithError("SAML is disabled.");
        }
    }

    /**
     * SP-Initiated SLO SAML flow
     */
    public function singleLogOut()
    {
        if (Config::isSamlEnabled()) {
            if (Config::isSamlSLOEnabled()) {
                $samlAuth = $this->samlFactory->getSamlAuth();
                $nameId = $nameIdFormat = $sessionIndex = $nameIdNameQualifier = $nameIdSPNameQualifier = null;

                if (!empty($_SESSION['saml_data']["name_id"])) {
                    $nameId = $_SESSION['saml_data']["name_id"];
                }
                if (!empty($_SESSION['saml_data']["nameid_format"])) {
                    $nameIdFormat = $_SESSION['saml_data']["nameid_format"];
                }
                if (!empty($_SESSION['saml_data']["session_index"])) {
                    $sessionIndex = $_SESSION['saml_data']["session_index"];
                }
                if (!empty($_SESSION['saml_data']["nameid_nq"])) {
                    $nameIdNameQualifier = $_SESSION['saml_data']["nameid_nq"];
                }
                if (!empty($_SESSION['saml_data']["nameid_spnq"])) {
                    $nameIdSPNameQualifier = $_SESSION['saml_data']["nameid_spnq"];
                }

                $returnTo = null;
                $piwikUserLogin = Piwik::getCurrentUserLogin();
                $this->logger->info("Initiated the Single Log Out for user with login ".$piwikUserLogin);
                $samlAuth->logout($returnTo, array(), $nameId, $sessionIndex, false, $nameIdFormat, $nameIdNameQualifier, $nameIdSPNameQualifier);
            } else {
                $this->redirectToDashboardWithError("SAML Single Log Out is disabled.");
            }
        } else {
            $this->redirectToDashboardWithError("SAML is disabled.");
        }
    }

    /**
     * Assertion Consumer Service Endpoint
     */
    public function assertionConsumerService()
    {
        if (Config::isSamlEnabled()) {
            try {
                $this->logger->info('Initiated the Assertion Consumer Service');
                $samlAuth = $this->samlFactory->getSamlAuth();
                $samlAuth->processResponse();
                $this->logger->info('SAMLResponse processed');

                $debug = $samlAuth->getSettings()->isDebugActive();

                $errors = $samlAuth->getErrors();
                if (!empty($errors)) {
                    $this->logger->error('SAMLResponse rejected. '.$samlAuth->getLastErrorReason());
                    $this->logger->debug($samlAuth->getLastResponseXML());
                    $errorMsg = "Invalid SAMLResponse. ";
                    if ($debug) {
                        $errorMsg .= $samlAuth->getLastErrorReason();
                    }
                    $this->redirectToLoginWithError($errorMsg);
                } else {
                    $this->logger->info('SAMLResponse validated');
                    $attributes = $samlAuth->getAttributes();
                    $this->logger->debug('Attributes: '.json_encode($attributes));
                    $nameId = $samlAuth->getNameId();
                    $nameidFormat = $samlAuth->getNameIdFormat();
                    $sessionIndex = $samlAuth->getSessionIndex();
                    $nameIdNameQualifier = $samlAuth->getNameIdNameQualifier();
                    $nameIdSPNameQualifier = $samlAuth->getNameIdSPNameQualifier();
                    $sessionExpiration = $samlAuth->getSessionExpiration();
                    $this->logger->debug('NameId: '.$nameId.'  ||  NameIDFormat: '.$nameidFormat .'  ||  SessionIndex:'.$sessionIndex);

                    // Check if user exists
                    $isNewUser = empty($this->samlFactory->retrieveUserIfExists($attributes, $nameId));

                    $user = $this->samlFactory->retrieveUserAndCreateIfRequired($attributes, $nameId);

                    if ($user) {
                        $samlData = array ();
                        $samlData['saml_login'] = 1;
                        if (!empty($nameId)) {
                            $samlData['name_id'] = $nameId;
                        }
                        if (!empty($nameidFormat)) {
                            $samlData['nameid_format'] = $nameidFormat;
                        }
                        if (!empty($sessionIndex)) {
                            $samlData['session_index'] = $sessionIndex;
                        }
                        if (!empty($sessionExpiration)) {
                            $samlData['session_expiration'] = $sessionExpiration;
                        }
                        if (!empty($nameIdNameQualifier)) {
                            $samlData['nameid_nq'] = $nameIdNameQualifier;
                        }
                        if (!empty($nameIdSPNameQualifier)) {
                            $samlData['nameid_spnq'] = $nameIdSPNameQualifier;
                        }

                        $anyAccessSynched = false;
                        if (Config::isSamlSyncAccesEnabled()) {
                            $anyAccessSynched = $this->samlFactory->synchronizePiwikAccessFromSaml($user, $attributes);
                        }

                        if (!$anyAccessSynched && $isNewUser) {
                            $this->samlFactory->assignDefaultSitesViewAccessIfApplies($user);
                        }

                        if ($this->samlFactory->authenticateAndReloadAccess($user, $samlData)) {
                            // Redirect user
                            if (!empty($_POST['RelayState'])) {
                                $urlToRedirect = $_POST['RelayState'];
                                Url::redirectToUrl($urlToRedirect);
                            } else {
                                Piwik::redirectToModule('CoreHome');
                            }
                        }
                    }
                }
            } catch (Error $e) {
                $this->logExceptionMessage($e);
                $this->redirectToLoginWithError($e->getMessage());
            } catch (\Exception $e) {
                $this->logExceptionMessage($e);
                $this->redirectToLoginWithError($e->getMessage());
            }
        } else {
            $this->redirectToLoginWithError("SAML is disabled.");
        }
    }

    /**
     * Single Logout Service Endpoint
     */
    public function singleLogoutService()
    {
        if (Config::isSamlEnabled()) {
            if (Config::isSamlSLOEnabled()) {
                $piwikUserLogin = Piwik::getCurrentUserLogin();
                $this->logger->info("Initiated the Single Logout Service for user with login ".$piwikUserLogin);
                $retrieveFromServer = Config::getConfigOption('advanced_retrieve_parameters_from_server');
                
                try {
                    $samlAuth = $this->samlFactory->getSamlAuth();

                    $callBackLogout = '\Piwik\Plugins\Login\Controller::clearSession';

                    $samlAuth->processSLO(false, null, $retrieveFromServer, $callBackLogout);
                    $errors = $samlAuth->getErrors();
                    if (!empty($errors)) {
                        $this->logger->error("Error at Single Logout Service endpoint. User with login ".$piwikUserLogin.". ".$samlAuth->getLastErrorReason());
                    } else {
                        $this->logger->info("Single Logout Service executed. User with login ".$piwikUserLogin." logged out");
                    }

                    $logoutUrl = Url::getCurrentUrlWithoutFileName().'index.php';

                    $generalConfig = PiwikConfig::getInstance()->General;
                    if (!empty($generalConfig['login_logout_url'])) {
                        $logoutUrl = $generalConfig['login_logout_url'];
                    }
                    
                    Url::redirectToUrl($logoutUrl);
                } catch (Error $e) {
                    $this->logExceptionMessage($e);
                    $this->redirectToDashboardWithError($e->getMessage());
                } catch (\Exception $e) {
                    $this->logExceptionMessage($e);
                    $this->redirectToDashboardWithError($e->getMessage());
                }
            } else {
                $this->redirectToDashboardWithError("SAML SLO is disabled.");
            }
        } else {
            $this->redirectToDashboardWithError("SAML is disabled.");
        }
    }

    /**
     * @param \Exception $e
     */
    private function logExceptionMessage(\Exception $e)
    {
        $this->logger->error($e->getMessage());
    }

    /**
     * @param string $errorMessage
     */
    private function redirectToLoginWithError($errorMessage)
    {
        $baseUrl = Url::getCurrentUrlWithoutFileName();
        $loginUrl = $baseUrl . 'index.php?samlErrorMessage='.urlencode($errorMessage);
        Url::redirectToUrl($loginUrl);
        exit();
    }

    /**
     * @param string $errorMessage
     */
    private function redirectToDashboardWithError($errorMessage)
    {
        $notification = new Notification($errorMessage);
        $notification->context = Notification::CONTEXT_ERROR ;
        $notification->type = Notification::TYPE_TOAST;
        Notification\Manager::notify('LoginSaml_SamlNotification', $notification);

        $controllerResolver = StaticContainer::get('Piwik\Http\ControllerResolver');
        $parameters = array();
        $coreHomeController = $controllerResolver->getController('CoreHome', 'index', $parameters)[0];
        return $coreHomeController->index();
    }
}
