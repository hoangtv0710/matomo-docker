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
 * <div piwik-experiment-url-target>
 */
(function () {
    angular.module('piwikApp').directive('piwikExperimentUrlTarget', piwikExperimentUrlTarget);

    piwikExperimentUrlTarget.$inject = ['piwik', 'piwikApi', '$filter'];

    function piwikExperimentUrlTarget(piwik, piwikApi, $filter){

        var translate = $filter('translate');
        var targetPromise = piwikApi.fetch({method: 'AbTesting.getAvailableTargetAttributes'});

        return {
            restrict: 'A',
            scope: {
                urlTarget: '=?',
                canBeRemoved: '=?',
                disableIfNoValue: '=?',
                allowAny: '=?',
                onAddUrl: '&?',
                onRemoveUrl: '&?',
                onAnyChange: '&?',
            },
            templateUrl: 'plugins/AbTesting/angularjs/urltarget/urltarget.directive.html?cb=' + piwik.cacheBuster,
            controller: function ($scope) {

                $scope.onTypeChange = function () {
                    if ($scope.pattern_type.indexOf('not_') === 0) {
                        $scope.urlTarget.type = $scope.pattern_type.substring('not_'.length);
                        $scope.urlTarget.inverted = '1';
                    } else {
                        $scope.urlTarget.type = $scope.pattern_type;
                        $scope.urlTarget.inverted = 0;
                    }
                };

                $scope.onAttributeChange = function () {
                    if (!$scope.urlTarget.attribute) {
                        return;
                    }

                    var selectedType = $scope.pattern_type;

                    var types = $scope.targetOptions[$scope.urlTarget.attribute];

                    var found = false;
                    angular.forEach(types, function (type) {
                        if (selectedType == type.key) {
                            found = true;
                        }
                    });

                    if (!found && types[0]) {
                        $scope.pattern_type = types[0].key;
                        $scope.onTypeChange();
                    }
                };

                if ($scope.urlTarget.inverted && $scope.urlTarget.inverted !== '0') {
                    $scope.pattern_type = 'not_' + $scope.urlTarget.type;
                } else {
                    $scope.pattern_type = $scope.urlTarget.type;
                }
            },
            link: function (scope, element, attrs, ngModel) {

                targetPromise.then(function (targetAttributes) {
                    targetAttributes = angular.copy(targetAttributes);

                    var attributes = [];
                    angular.forEach(targetAttributes, function (value) {
                        attributes.push({key: value.value, value: value.name});
                    });

                    scope.targetAttributes = attributes;

                    scope.targetOptions = {};
                    scope.targetExamples = {};

                    angular.forEach(targetAttributes, function (targetAttribute) {
                        scope.targetOptions[targetAttribute.value] = [];

                        if (scope.allowAny && targetAttribute.value == 'url') {
                            scope.targetOptions[targetAttribute.value].push({value: translate('AbTesting_TargetTypeIsAny'), key: 'any'});
                        }

                        scope.targetExamples[targetAttribute.value] = targetAttribute.example;

                        angular.forEach(targetAttribute.types, function (type) {
                            scope.targetOptions[targetAttribute.value].push({value: type.name, key: type.value});
                            scope.targetOptions[targetAttribute.value].push({value: translate('AbTesting_TargetTypeIsNot', type.name), key: 'not_' + type.value});
                        });
                    });

                });
            }
        };
    }
})();