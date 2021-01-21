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
 * <div piwik-target-test>
 */
(function () {
    angular.module('piwikApp').directive('piwikTargetTest', piwikTargetTest);

    piwikTargetTest.$inject = ['piwik'];

    function piwikTargetTest(piwik){

        function isValidUrl(url) {
            return url.indexOf('://') > 3;
        }

        function getLocation(url) {
            var parser = document.createElement('a');
            parser.href = url;

            return {href: url, pathname: parser.pathname, search: parser.search};
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
                    filtered.push(targets[i]);
                }
            }

            return filtered;
        }

        return {
            restrict: 'A',
            scope: {
                includedTargets: '=',
                excludedTargets: '=',
            },
            templateUrl: 'plugins/AbTesting/angularjs/targettest/targettest.directive.html?cb=' + piwik.cacheBuster,
            controller: function ($scope) {

                function runTest() {
                    if (!$scope.targetTest || !$scope.targetTest.url) {
                        return;
                    }

                    if (!isValidUrl($scope.targetTest.url)) {
                        $scope.targetTest.isValid = false;
                        return;
                    }

                    $scope.targetTest.isValid = true;

                    var locationBackup = piwikAbTestingTarget.location;

                    piwikAbTestingTarget.location = getLocation($scope.targetTest.url);

                    var included = filterTargetsWithEmptyValue($scope.includedTargets);
                    var excluded = filterTargetsWithEmptyValue($scope.excludedTargets);
                    $scope.targetTest.matches = piwikAbTestingTarget.matchesTargets(included, excluded);

                    piwikAbTestingTarget.location = locationBackup;
                }

                $scope.onUrlChange = runTest;
                $scope.$watch(function() {
                    return JSON.stringify($scope.includedTargets) + JSON.stringify($scope.excludedTargets);
                }, function (newValue, oldValue) {
                    if (newValue !== oldValue) {
                        runTest();
                    }
                });
            }
        };
    }
})();