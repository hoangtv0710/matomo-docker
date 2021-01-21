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
namespace Piwik\Plugins\LoginSaml\Diagnostic;

use Piwik\Plugins\Diagnostics\Diagnostic\Diagnostic;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResult;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResultItem;
use Piwik\Translation\Translator;

/**
 * Check openssl is installed and loaded
 */
class LoginSamlCheck implements Diagnostic
{
    /**
     * @var Translator
     */
    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function execute()
    {
        $label = $this->translator->translate('Installation_SystemCheckExtensions');
        $result = new DiagnosticResult($label);
        $longErrorMessage = '';
        $requiredExtensions = $this->getRequiredExtensions();
        foreach ($requiredExtensions as $extension) {
            if (! extension_loaded($extension)) {
                $status = DiagnosticResult::STATUS_ERROR;
                $comment = $extension . ': ' . $this->translator->translate('Installation_RestartWebServer');
                $longErrorMessage .= '<p>' . $this->getHelpMessage($extension) . '</p>';
            } else {
                $status = DiagnosticResult::STATUS_OK;
                $comment = $extension;
            }
            $result->addItem(new DiagnosticResultItem($status, $comment));
        }
        $result->setLongErrorMessage($longErrorMessage);
        return array($result);
    }

    /**
     * @return string[]
     */
    private function getRequiredExtensions()
    {
        $requiredExtensions = array(
            'openssl'
        );
        return $requiredExtensions;
    }
    private function getHelpMessage($missingExtension)
    {
        $messages = array(
            'openssl'       => 'LoginSaml_SystemCheckOpensslHelp'
        );
        return $this->translator->translate($messages[$missingExtension]);
    }
}
