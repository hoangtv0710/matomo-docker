<?php 
/**
 * Plugin Name: Site Url Tracking ID (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/SiteUrlTrackingID
 * Description: Enables to use any of the site URLs of a website as the tracking ID in addition to the numeric site ID.
 * Author: Kaan Erturk
 * Author URI: https://github.com/KaanErturk/piwik-SiteUrlTrackingID
 * Version: 1.0.3
 */
?><?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SiteUrlTrackingID;

use Piwik\Common;
use Piwik\Db;

 
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

class SiteUrlTrackingID extends \Piwik\Plugin
{
    
    /**
     * Get prefixed table name
     *
     * @param string $rawTableName
     * @return string
     */
    private function getTable($rawTableName)
    {
        return Common::prefixTable($rawTableName);
    }
    
    /**
     * Register event observers
     *
     * @return array
     */
    public function registerEvents()
    {
        return [
            'Piwik.getJavascriptCode' => 'getSiteURLjs',
            'SitesManager.getImageTrackingCode' => 'getSiteURLimg',
            'Tracker.Request.getIdSite' => 'getSiteID'
        ];
    }

    /**
     * Get main site URL from the site ID
     *
     * @param int $idSite
     * @return string|int
     */
    public function getSiteURL($idSite)
    {
        $sql = 'SELECT main_url FROM ' . $this->getTable('site') . ' WHERE idsite = ?';
        
        $siteURL = Db::fetchOne($sql, array($idSite));
        
        return (!empty($siteURL) ? preg_replace('/^.+?\:\/\//i', '', $siteURL) : $idSite);
    }
    
    /**
     * Get site ID from the site URL
     *
     * @param int &$idSite
     * @param array $params
     * @return void
     */
    public function getSiteID(&$idSite, $params)
    {
        if (!(is_int($idSite) && $idSite > 0))
        {
            $sql = 'SELECT s.idsite FROM (
                        (SELECT idsite FROM ' . $this->getTable('site') . ' WHERE main_url LIKE ?)
                            UNION ALL
                        (SELECT idsite FROM ' . $this->getTable('site_url') . ' WHERE url LIKE ?)
                    ) AS s
                    GROUP BY idsite
                    ORDER BY idsite ASC
                    LIMIT 1';
            
            $siteURLfull = '%://' . $params['idsite'];
            
            $idSite = (int) Db::fetchOne($sql, array($siteURLfull, $siteURLfull));
        }
    }

    /**
     * Get site URL for the JavaScript Tracking Code
     *
     * @param array &$codeImpl
     * @param array $parameters
     * @return void
     */
    public function getSiteURLjs(&$codeImpl, $parameters)
    {
        $codeImpl['idSite'] = $this->getSiteURL($codeImpl['idSite']);
    }

    /**
     * Get site URL for the Image Tracking Link
     *
     * @param array &$piwikUrl
     * @param array &$urlParams
     * @return void
     */
    public function getSiteURLimg(&$piwikUrl, &$urlParams)
    {
        $urlParams['idsite'] = $this->getSiteURL($urlParams['idsite']);
    }
    
}
