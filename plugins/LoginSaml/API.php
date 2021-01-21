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

use OneLogin\Saml2\IdPMetadataParser;
use Piwik\Container\StaticContainer;
use Piwik\Common;
use Piwik\Piwik;
use Exception;

/**
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Saves LoginSaml config.
     *
     * @param string $data JSON encoded config array.
     * @return array
     * @throws Exception if user does not have super access,
     *         if this is not a POST method or
     *         if JSON is not supplied.
     */
    public function saveSamlConfig($data)
    {
        $this->checkHttpMethodIsPost();
        Piwik::checkUserHasSuperUserAccess();

        $data = json_decode(Common::unsanitizeInputValue($data), true);

        $validation = Config::validateData($data);
        Config::savePluginOptions($data);

        if (!empty($validation['error']) || !empty($validation['missing'])) {
            $errorMessage = "";
            if (!empty($validation['missing'])) {
                $errorMessage .= Piwik::translate('LoginSaml_MissingData').': '.implode(", ", $validation['missing']).".  ";
            }
            if (!empty($validation['error'])) {
                $errorMessage .= Piwik::translate('LoginSaml_InvalidData').': '.implode(", ", $validation['error']);
            }
            throw new Exception($errorMessage);
        }

        return array('result' => 'success', 'message' => Piwik::translate("General_YourChangesHaveBeenSaved"));
    }

    public function importIdPMetadata($data)
    {
        $this->checkHttpMethodIsPost();
        Piwik::checkUserHasSuperUserAccess();

        $data = json_decode(Common::unsanitizeInputValue($data), true);
        $metadataInfo = $idp_entity_id = null;
        if (!empty($data['idp_entityid'])) {
            $idp_entity_id = $data['idp_entityid'];
        }

        try {
            if (!empty($data['idp_metadata_xml'])) {
                $metadataInfo = IdPMetadataParser::parseXML(
                    $data['idp_metadata_xml'],
                    $idp_entity_id,
                    'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'
                );
            } else if (!empty($data['idp_metadata_url'])) {
                $metadataInfo = IdPMetadataParser::parseRemoteXML(
                    $data['idp_metadata_url'],
                    $idp_entity_id,
                    'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'
                );
            }
        } catch (\Exception $e) {
            $logger = StaticContainer::get('Piwik\Plugins\LoginSaml\Logger');
            $logger->info('Error importing IdP data. '.$e->getMessage());
        }

        if (!empty($metadataInfo)) {
            Config::injectSamlValues($metadataInfo, true);

            return array('result' => 'success', 'message' => Piwik::translate("LoginSaml_IdPMetadataImported"));
        } else {
            return array('result' => 'error', 'message' => Piwik::translate("LoginSaml_IdPMetadataError"));
        }
    }

    private function checkHttpMethodIsPost()
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            throw new \Exception("Invalid HTTP method.");
        }
    }
}
