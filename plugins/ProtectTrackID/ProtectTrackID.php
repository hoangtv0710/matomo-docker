<?php 
/**
 * Plugin Name: Protect Track ID (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/ProtectTrackID
 * Description: Provides a option to protect idSite using hash instead default numeric
 * Author: Joubert RedRat
 * Author URI: https://github.com/joubertredrat/Piwik-ProtectTrackID
 * Version: 1.0.0
 */
?><?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * ProtectTrackID Main class, responsible for hash and unhash idSite
 *
 * @copyright (c) 2016 Joubert RedRat
 * @author Joubert RedRat <eu+github@redrat.com.br>
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @category Piwik_Plugins
 * @package ProtectTrackID
 */

namespace Piwik\Plugins\ProtectTrackID;

use Piwik\Container\StaticContainer;

 
if (defined( 'ABSPATH')
&& function_exists('add_action')) {
    $path = '/matomo/app/core/Plugin.php';
    if (defined('WP_PLUGIN_DIR') && WP_PLUGIN_DIR && file_exists(WP_PLUGIN_DIR . $path)) {
        require_once WP_PLUGIN_DIR . $path;
    } elseif (defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR && file_exists(WPMU_PLUGIN_DIR . $path)) {
        require_once WPMU_PLUGIN_DIR . $path;
    } else {
        return;
    }
    add_action('plugins_loaded', function () {
        if (function_exists('matomo_add_plugin')) {
            matomo_add_plugin(__DIR__, __FILE__, true);
        }
    });
}

class ProtectTrackID extends \Piwik\Plugin
{
    /**
     * Register event observers
     *
     * @return array
     */
    public function registerEvents()
    {
        return [
            'Piwik.getJavascriptCode' => 'hashIdJavaScript',
            'SitesManager.getImageTrackingCode' => 'hashIdImage',
            'Tracker.Request.getIdSite' => 'unhashId'
        ];
    }

    /**
     * Get plugin settings
     *
     * @return array
     */
    private function getSettings()
    {
        $settings = StaticContainer::get('Piwik\Plugins\ProtectTrackID\SystemSettings');

        return [
            'base' => $settings->base->getValue(),
            'salt' => $settings->salt->getValue(),
            'length' => $settings->length->getValue()
        ];
    }

    /**
     * Creates a hash from a integer id
     *
     * @param int $idSite
     * @return int|string
     */
    private function hashId($idSite)
    {
        extract($this->getSettings());

        if (is_null($base) || empty($base) ||
            is_null($salt) || empty($salt) ||
            is_null($length) || empty($length)
        ) {
            return $idSite;
        }

        require_once(__DIR__.'/vendor/autoload.php');

        $Hashid = new \Hashids\Hashids($salt, $length, $base);
        return $Hashid->encode($idSite);
    }

    /**
     * Hash id site for JavaScript Tracking Code
     *
     * @param array &$codeImpl
     * @param array $parameters
     * @return void
     */
    public function hashIdJavaScript(&$codeImpl, $parameters)
    {
        $codeImpl['idSite'] = $this->hashId($codeImpl['idSite']);
    }

    /**
     * Hash id site for Image Tracking Link
     *
     * @param array &$piwikUrl
     * @param array &$urlParams
     * @return void
     */
    public function hashIdImage(&$piwikUrl, &$urlParams)
    {
        $urlParams['idsite'] = $this->hashId($urlParams['idsite']);
    }

    /**
     * Unhash id site
     *
     * @param int &$idSite
     * @param array $params
     * @return void
     */
    public function unhashId(&$idSite, $params)
    {
        if ($this->validateHash($params['idsite'])) {
            require_once(__DIR__.'/vendor/autoload.php');

            extract($this->getSettings());

            $Hashid = new \Hashids\Hashids($salt, $length, $base);
            $idSite = $Hashid->decode($params['idsite'])[0];
        }
    }

    /**
     * Verify if hash is valid from settings
     *
     * @param string $hash
     * @return bool
     */
    public function validateHash($hash)
    {
        extract($this->getSettings());

        if (is_null($base) || empty($base) ||
            is_null($salt) || empty($salt) ||
            is_null($length) || empty($length)
        ) {
            return false;
        }

        $regex = '/^('.implode('|', str_split($base)).'){'.$length.'}$/';
        return (bool) preg_match($regex, $hash);
    }
}
