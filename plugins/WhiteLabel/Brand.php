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

namespace Piwik\Plugins\WhiteLabel;

class Brand
{
    private $brandName;

    public function __construct($brandName)
    {
        $this->brandName = $brandName;
    }

    public function replacePiwikWithBrand($output)
    {
        if (empty($this->brandName)) {
            return $output;
        }

        $tagManangerMatomoId = '"id":"Matomo"';
        $noBrandReplace = array('Piwik_', 'rssPiwik', 'loadingPiwik', 'Matomo_', 'rssMatomo', 'loadingMatomo', $tagManangerMatomoId);
        $temporaryReplace = array('__##PIWIK##__', '__##RSSPIWIK##__', '__##LOADINGPIWIK##__', '__##MATOMO##__', '__##RSSMATOMO##__', '__##LOADINGMATOMO##__', '__##MATOMOID##__');

        $output = str_replace($noBrandReplace, $temporaryReplace, $output);

        $oldBrands = ['Piwik', 'Matomo'];
        $newBrand = $this->brandName;

        foreach ($oldBrands as $oldBrand) {
            $replace     = array($oldBrand . ' ', ' ' . $oldBrand, $oldBrand . '&#x20;', '&#x20;' . $oldBrand, '"' . $oldBrand . '"', 'title="' . $oldBrand, 'content="' . $oldBrand, 'alt="' . $oldBrand, 'content="' . $oldBrand);
            $replaceWith = array($newBrand . ' ', ' ' . $newBrand, $newBrand . '&#x20;', '&#x20;' . $newBrand, '"' . $newBrand . '"', 'title="' . $newBrand, 'content="' . $newBrand, 'alt="' . $newBrand, 'content="' . $newBrand);

            $output = str_replace($replace, $replaceWith, $output);
        }

        $output = str_replace( ' - free/libre analytics platform', '', $output);
        $output = str_replace($temporaryReplace, $noBrandReplace, $output);
        return $output;
    }

    public function removeLinksToMatomo($output)
    {
        $pattern = array(
            '~<a[^>]*?href=[\'"]([^\'"]+?)[\'"].*?>.*?</a>~is',
        );
        $output = preg_replace_callback($pattern, array($this, 'rewriteLinkToSpanIfNeeded'), $output);
        return $output;
    }

    private function rewriteLinkToSpanIfNeeded($src)
    {
        $source = $src[0];
        if (!empty($src[1]) && (strpos($src[1], 'matomo.org') !== false || strpos($src[1], 'piwik.org') !== false)) {
            $source = str_replace($src[1], '', $source);
            $source = str_replace(array('href=""', "href=''"), '', $source);
            $source = str_replace(array('</a>', '< /a>', '</a >'), '</span>', $source);
            $source = str_replace('<a', '<span', $source);
        }

        return $source;
    }

    public function removeMobileAppAd($output)
    {
        $output = str_replace('<meta name="apple-itunes-app" content="app-id=737216887" />', '', $output);
        $output = str_replace('<meta name="google-play-app" content="app-id=org.piwik.mobile2">', '', $output);
        $output = str_replace('<meta name="google-play-app" content="app-id=org.matomo.mobile2">', '', $output);
        $output = str_replace('<link rel="manifest" href="plugins/CoreHome/javascripts/manifest.json">', '', $output);
        // we also remove by only app-id since it may change over time
        $output = str_replace('app-id=737216887', '', $output);
        $output = str_replace('app-id=org.piwik.mobile2', '', $output);
        $output = str_replace('app-id=org.matomo.mobile2', '', $output);

        return $output;
    }

    public function shouldReplaceBrand($module, $action)
    {
        if (!empty($module)) {
            if ($module === 'Feedback' && (empty($action) || $action === 'index')) {
                return false; // we do not replace the brand on the help page which is only visible to super users anyway
            }

            if ($module === 'Cloud' && (empty($action) || $action === 'help')) {
                return false; // we do not replace the Cloud help page. It is shown to users but we would then need to replace InnoCraft Matomo Cloud
            }

            if ($module === 'CorePluginsAdmin' && in_array($action, array('plugins', 'themes'))) {
                return false; // we do not replace eg the actual creator / owner in the list of plugins and themes
            }

            if ($module === 'Marketplace') {
                return false; // we do not replace anything in the Marketplace to mention the actual creators / owners etc
            }
        }

        return true;
    }

}
