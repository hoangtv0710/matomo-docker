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

(function () {
    angular.module('piwikApp').controller('HeatmapSessionUrlTargetController', HeatmapSessionUrlTargetController);

    HeatmapSessionUrlTargetController.$inject = ['$scope', 'piwikApi', '$filter'];

    var targetPromise = null;
    var urlTargetAttributes = null;

    function HeatmapSessionUrlTargetController($scope, piwikApi, $filter) {

        var translate = $filter('translate');
        if (!targetPromise) {
            targetPromise = piwikApi.fetch({method: 'HeatmapSessionRecording.getAvailableTargetPageRules', filter_limit: '-1'});
        }

        var self = this;

        this.onTypeChange = function () {
            if (self.pattern_type.indexOf('not_') === 0) {
                $scope.urlTarget.type = self.pattern_type.substring('not_'.length);
                $scope.urlTarget.inverted = '1';
            } else {
                $scope.urlTarget.type = self.pattern_type;
                $scope.urlTarget.inverted = 0;
            }
        };

        this.trim = function (value) {
            return $.trim(value);
        };

        this.onAttributeChange = function () {
            if (!$scope.urlTarget.attribute) {
                return;
            }

            var selectedType = self.pattern_type;

            var types = self.targetOptions[$scope.urlTarget.attribute];

            var found = false;
            angular.forEach(types, function (type) {
                if (selectedType == type.key) {
                    found = true;
                }
            });

            if (!found && types[0]) {
                self.pattern_type = types[0].key;
                this.onTypeChange();
            }
        };

        function setTargetAttributes(targetAttributes) {

            targetAttributes = angular.copy(targetAttributes);

            var attributes = [];
            angular.forEach(targetAttributes, function (value) {
                attributes.push({key: value.value, value: value.name});
            });

            self.targetAttributes = attributes;
            self.targetOptions = {};
            self.targetExamples = {};

            angular.forEach(targetAttributes, function (targetAttribute) {
                self.targetOptions[targetAttribute.value] = [];

                if ($scope.allowAny && targetAttribute.value == 'url') {
                    self.targetOptions[targetAttribute.value].push({value: translate('HeatmapSessionRecording_TargetTypeIsAny'), key: 'any'});
                }

                self.targetExamples[targetAttribute.value] = targetAttribute.example;

                angular.forEach(targetAttribute.types, function (type) {
                    self.targetOptions[targetAttribute.value].push({value: type.name, key: type.value});
                    self.targetOptions[targetAttribute.value].push({value: translate('HeatmapSessionRecording_TargetTypeIsNot', type.name), key: 'not_' + type.value});
                });
            });
        }

        if (urlTargetAttributes) {
            setTargetAttributes(urlTargetAttributes);
        } else {
            targetPromise.then(function (targetAttributes) {
                urlTargetAttributes = targetAttributes;
                setTargetAttributes(urlTargetAttributes);
            });
        }

        if ($scope.urlTarget.inverted && $scope.urlTarget.inverted !== '0') {
            self.pattern_type = 'not_' + $scope.urlTarget.type;
        } else {
            self.pattern_type = $scope.urlTarget.type;
        }
    }
})();