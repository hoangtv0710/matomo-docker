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
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

namespace Piwik\Plugins\WooCommerceAnalytics;

use Piwik\Common;
use Piwik\Log;
use Piwik\Piwik;
use Piwik\SettingsPiwik;

class Controller extends \Piwik\Plugin\ControllerAdmin
{
    public function install()
    {
        Piwik::checkUserHasSomeAdminAccess();

        return $this->renderTemplate('download');
    }

    public function download()
    {
        // we do not download the file directly as we may want to restrict who can download this file and
        // be able to change the raw file name etc.
        // also ZIP files might be blocked via htaccess etc
        $pathToPlugin = PIWIK_DOCUMENT_ROOT . '/plugins/WooCommerceAnalytics/woocommerce-piwik-analytics.zip';
        $filename = 'woocommerce-piwik-analytics.zip';

        $byteEnd = filesize($pathToPlugin);

        Common::sendHeader('Content-Disposition: inline; filename=' . $filename);
        Common::sendHeader('Pragma: ');
        Common::sendHeader('Expires: ');
        Common::sendHeader("Cache-Control: no-cache, must-revalidate");
        Common::sendHeader('Vary: Accept-Encoding');
        Common::sendHeader('Content-Length: ' . $byteEnd);
        Common::sendHeader('Content-Type: application/zip');

        // we do not use ProxyHttp::serverStaticFile because it does compress the zip file and the wordpress
        // plugin wouldn't be able to handle it.
        if (!_readfile($pathToPlugin, $byteStart = 0, $byteEnd)) {
            Common::sendResponseCode(500);
            Log::warning('Readfile failed');
        }

        exit;
    }

    public function pluginUpdate()
    {
        // JSON files might be blocked via htaccess etc so we use an action. Also to be able to restrict access
        // in the future if needed and we need to adjust the URLs anyway based on current piwik url

        $piwikUrl = SettingsPiwik::getPiwikUrl();

        $piwikUrl = str_replace(array('piwik.php', 'tracker.php', 'index.php'), '', $piwikUrl);
        if (substr($piwikUrl, -1) !== '/') {
            $piwikUrl .= '/';
        }

        $details = array(
            "name" => "WooCommerce Matomo Analytics",
            "slug" => "woocommerce-piwik-analytics",
            "version" => '1.0.15',
            "last_updated" => "2020-11-13 00:30:00",
            "download_url" => $piwikUrl . "index.php?module=WooCommerceAnalytics&action=download",
            "homepage" => "https://plugins.matomo.org/WooCommerceAnalytics/",
            "requires" => "4.5",
            "tested" => "5.6",
            "author" => "InnoCraft",
            "author_homepage" => "https://www.innocraft.com",
            "sections" => array(
                "description" => Piwik::translate('WooCommerceAnalytics_WoocommercePluginDescription')
            ),
            "banners" => array(
                "low" => $piwikUrl . "plugins/WooCommerceAnalytics/images/bannerlow.png",
                "high" => $piwikUrl . "plugins/WooCommerceAnalytics/images/bannerhigh.png"
            )
        );
        echo json_encode($details);
        exit;
    }
}
