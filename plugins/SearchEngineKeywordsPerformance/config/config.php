<?php

require dirname(dirname(__FILE__)) . '/vendor/autoload.php';

return [
    'Piwik\Plugins\SearchEngineKeywordsPerformance\Client\Google' => DI\object(),
    'diagnostics.optional'                              => DI\add([
        DI\get('Piwik\Plugins\SearchEngineKeywordsPerformance\Diagnostic\BingAccountDiagnostic'),
        DI\get('Piwik\Plugins\SearchEngineKeywordsPerformance\Diagnostic\GoogleAccountDiagnostic'),
    ]),
];
