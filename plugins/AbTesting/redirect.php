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

/**
 * USAGE: Append a query string ?id=$IDEXPERIMENT to "$yourPiwikDomain/plugins/AbTesting/redirect.php" eg
 * https://demo.matomo.org/plugins/AbTesting/redirect.php?id=35
 *
 * It the experiment exists, and if any redirect URL is defined, it will redirect to one of the configured
 * redirect URLs. A cookie will be set to make sure the user will be always redirected to the same URL.
 */
if (empty($_GET['id']) ||
    !preg_match('/^\d+$/', (string) $_GET['id'])) {
    http_response_code(400);
    exit;
}

$idExperiment = (int) abs($_GET['id']);

define('PIWIK_INCLUDE_PATH', realpath('../..'));
define('PIWIK_USER_PATH', PIWIK_INCLUDE_PATH);
define('PIWIK_DOCUMENT_ROOT', PIWIK_INCLUDE_PATH);
define('PIWIK_ENABLE_DISPATCH', false);
define('PIWIK_ENABLE_ERROR_HANDLER', false);
define('PIWIK_ENABLE_SESSION_START', false);
define('PIWIK_DISPLAY_ERRORS', 0);

// we do not load index.php as it would register safeMode!
require_once PIWIK_INCLUDE_PATH . '/core/bootstrap.php';

// we do not want it to be validate, saves 25-50ms or up to 50% of whole request
class Validator {
    public function validate() {}
}
$validator = new Validator();
$environment = new \Piwik\Application\Environment(null, array(
    'Piwik\Application\Kernel\EnvironmentValidator' => $validator
));
try {
    $environment->init();
} catch(\Piwik\Exception\NotYetInstalledException $e) {
    http_response_code(403);
    exit;
}

$cacheDir = \Piwik\Container\StaticContainer::get('path.cache');

// we could directly use the ID here but for security reason it is much better to use the int casted values in case preg_match above ever breaks
$cacheFile = $cacheDir . 'abtesting_' . $idExperiment . '.php';

if (is_readable($cacheFile)) {
    @include $cacheFile;

    if (!empty($experiment['data']) && !empty($experiment['ttl']) && $experiment['ttl'] >= time()) {
        $experiment = $experiment['data'];
    } else {
        $experiment = array();
    }
}

if (empty($experiment)) {
    $model = \Piwik\Container\StaticContainer::get('\Piwik\Plugins\AbTesting\Dao\Experiment');
    $experiment = $model->getExperimentById($idExperiment);

    if (!empty($experiment)) {
        // we do not check for experiment status here as otherwise redirects might suddenly no longer work
        $cache = array(
            'ttl' => time() + (60 * 60), // cache 1 hour
            'data' => $experiment
        );

        \Piwik\Filesystem::mkdir($cacheDir);
        if (is_writable($cacheDir) || is_writable($cacheFile)) {
            @file_put_contents($cacheFile, '<?php $experiment = ' . var_export($cache, 1) . ';');
        }
    }
}

if (empty($experiment)) {
    http_response_code(404);
    exit;
}

include_once __DIR__ . '/redirect/vendor/autoload.php';

$variations = array();

if (!empty($experiment['original_redirect_url'])) {
    $variations[] = array('name' => '0', 'url' => $experiment['original_redirect_url'], 'percentage' => null);
}

if (!empty($experiment['variations'])) {
    foreach ($experiment['variations'] as $variation) {
        // we use recognize variations that have a URL defined to make sure to redirect from this location
        if (!empty($variation['redirect_url'])) {
            $variations[] = array(
                'name' => (int) $variation['idvariation'],
                'percentage' => isset($variation['percentage']) ? $variation['percentage'] : null,
                'url' => $variation['redirect_url']
            );
        }
    }
}

if (empty($variations)) {
    // no url to redirect
    http_response_code(404);
    exit;
}

$experiment = new \InnoCraft\Experiments\Experiment((int) $experiment['idexperiment'], $variations);
$activated = $experiment->getActivatedVariation();

if (!empty($activated)) {
    // redirects to either a URL or does nothing if one of the variations has not a URL defined
    $activated->run();
}
