<?php 
/**
 * Plugin Name: Profile Avatar (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/ProfileAvatar
 * Description: Show a random avatar on the Visitor Profile
 * Author: Lukas Winkler
 * Author URI: https://lw1.at
 * Version: 0.1.2
 */
?><?php

namespace Piwik\Plugins\ProfileAvatar;

use Piwik\Plugin;

 
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

class ProfileAvatar extends Plugin
{

    public function registerEvents()
    {
        return [
            'Live.getExtraVisitorDetails' => 'getExtraVisitorDetails'
        ];
    }

    public function getExtraVisitorDetails(&$result): void
    {
        $settings = new UserSettings();
        $visitorID = $result["visitorId"];
        $hash = hash("sha256", $visitorID);

        if ($settings->dataURLs->getValue()) {
            $chosenGenerator = $settings->avatarType->getValue();
            $generator = GeneratorCollection::getGeneratorClasses($chosenGenerator,$hash);
            $result['visitorAvatar'] = $generator->asDataUrl();
        } else {
            $result['visitorAvatar'] = "?module=ProfileAvatar&action=getProfileAvatar&hash=$hash";
        }
    }
}
