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
 * Usage:
 * <div piwik-hsr-target-test included-targets="{...}">
 */
(function () {
    angular.module('piwikApp').directive('piwikHsrTargetTest', piwikHsrTargetTest);

    piwikHsrTargetTest.$inject = ['piwik', '$timeout', 'piwikApi'];

    function piwikHsrTargetTest(piwik, $timeout, piwikApi){

        function isValidUrl(url) {
            return url.indexOf('://') > 3;
        }

        function filterTargetsWithEmptyValue(targets)
        {
            if (!targets) {
                return;
            }
            
            targets = angular.copy(targets);

            var filtered = [];
            for (var i = 0; i < targets.length; i++) {
                if (targets[i] && targets[i].value) {
                    targets[i].value = $.trim(targets[i].value);
                    filtered.push(targets[i]);
                }
                if (targets[i] && targets[i].type && targets[i].type === 'any') {
                    filtered.push(targets[i]);
                }
            }

            return filtered;
        }

        return {
            restrict: 'A',
            scope: {
                includedTargets: '=',
            },
            templateUrl: 'plugins/HeatmapSessionRecording/angularjs/targettest/targettest.directive.html?cb=' + piwik.cacheBuster,
            controller: function ($scope) {
                var validateUrlTimeout = null;
                var validateUrlPromise = null;

                function getTargetUrl()
                {
                    return $.trim($scope.targetTest.url);
                }

                function checkValidUrl()
                {
                    if (!$scope.targetTest || !getTargetUrl()) {
                        return false;
                    }

                    if (!isValidUrl(getTargetUrl())) {
                        $scope.targetTest.isValid = false;
                        $scope.targetTest.matches = false;
                        return false;
                    }

                    $scope.targetTest.isValid = true;
                    return true;
                }

                function checkIsMatchingUrl()
                {
                    if (!checkValidUrl()) {
                        return;
                    }

                    var url = getTargetUrl();

                    if (validateUrlPromise) {
                        validateUrlPromise.abort();
                        validateUrlPromise = null;
                    }

                    var included = filterTargetsWithEmptyValue($scope.includedTargets);

                    if (!included || !included.length) {
                        return;
                    }

                    $scope.targetTest.isLoadingTestMatchPage = true;

                    validateUrlPromise = piwikApi.post({method: 'HeatmapSessionRecording.testUrlMatchPages', url: url}, {matchPageRules: included}, {placeat: '#hsrTargetValidationError'});
                    validateUrlPromise.then(function (response) {
                        $scope.targetTest.isLoadingTestMatchPage = false;

                        var included = filterTargetsWithEmptyValue($scope.includedTargets);

                        if (!included || !included.length) {
                            return;
                        }

                        if (!response || !response.url || response.url != getTargetUrl()) {
                            return;
                        }

                        $scope.targetTest.matches = response.matches;

                    })['catch'](function (error) {
                        $scope.targetTest.isLoadingTestMatchPage = false;
                    });
                }

                function runTest() {
                    if (!checkValidUrl()) {
                        return;
                    }

                    if (validateUrlTimeout) {
                        $timeout.cancel(validateUrlTimeout);
                        validateUrlTimeout = null;
                    }

                    // we wait for 200ms before actually sending a request as user might be still typing
                    validateUrlTimeout = $timeout(function () {
                        checkIsMatchingUrl();
                        validateUrlTimeout = null;
                    }, 200);
                }

                $scope.onUrlChange = runTest;
                $scope.$watch(function() {
                    return JSON.stringify($scope.includedTargets);
                }, function (newValue, oldValue) {
                    if (newValue !== oldValue) {
                        runTest();
                    }
                });
            }
        };
    }
})();