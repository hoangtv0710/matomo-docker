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
 * <div piwik-manage-multiattribution>
 */
(function () {
    angular.module('piwikApp').directive('piwikManageMultiattribution', piwikManageMultiAttribution);

    piwikManageMultiAttribution.$inject = ['piwik', 'piwikApi', '$timeout', '$filter', '$rootScope'];

    function piwikManageMultiAttribution(piwik, piwikApi, $timeout, $filter, $rootScope){

        var translate = $filter('translate');

        function applyScope(scope)
        {
            $timeout(function () {
                scope.$apply();
            }, 1);
        }

        function isNumeric(text) {
            return !isNaN(parseFloat(text)) && isFinite(text);
        }

        return {
            restrict: 'A',
            templateUrl: 'plugins/MultiChannelConversionAttribution/angularjs/manage-attribution/manage-attribution.directive.html?cb=' + piwik.cacheBuster,
            compile: function (element, attrs) {

                return function (scope, element, attrs, controller) {

                    var manageGoals = element.parents('[piwik-manage-goals]');
                    if (manageGoals.length) {
                        var id = manageGoals.attr('show-goal');
                        if (isNumeric(id)) {
                            controller.initGoal('Goals.updateGoal', id);
                        }
                    }
                };
            },
            controllerAs: 'manageAttributionCtrl',
            controller: function ($scope) {

                var self = this;
                var fetchAttributionPromise = null;

                this.isLoading = false;

                this.reset = function () {
                    this.goalAttribution = {isEnabled: true};
                };

                this.reset();

                function resetForm() {
                    self.reset();

                    if (fetchAttributionPromise && fetchAttributionPromise.abort) {
                        fetchAttributionPromise.abort();
                        fetchAttributionPromise = null;
                        self.isLoading = false;
                        applyScope($scope);
                    }
                }

                $rootScope.$on('Goals.cancelForm', resetForm);

                function initGoalForm(event, goalMethodAPI, goalId) {

                    resetForm();

                    if (!goalId || goalMethodAPI == 'Goals.addGoal') {
                        return;
                    }

                    self.isLoading = true;

                    fetchAttributionPromise = piwikApi.fetch({method: 'MultiChannelConversionAttribution.getGoalAttribution', idGoal: goalId});
                    fetchAttributionPromise.then(function (response) {
                        self.isLoading = false;

                        if (fetchAttributionPromise && response) {
                            self.goalAttribution = response;

                            self.goalAttribution.isEnabled = (self.goalAttribution.isEnabled && self.goalAttribution.isEnabled !== '0');
                        }
                        fetchAttributionPromise = null;
                        applyScope($scope);
                    })['catch'](function (error) {
                        self.isLoading = false;
                    });
                }

                $rootScope.$on('Goals.beforeInitGoalForm', initGoalForm);

                function onSetAttribution (event, parameters, piwikApi) {
                    if (!self.goalAttribution) {
                        return;
                    }
                    var isEnabled = self.goalAttribution.isEnabled ? 1 : 0;
                    piwikApi.addPostParams({multiAttributionEnabled: isEnabled});
                }

                $rootScope.$on('Goals.beforeAddGoal', onSetAttribution);
                $rootScope.$on('Goals.beforeUpdateGoal', onSetAttribution);

                // eg when appending idGoal=$ID a goal will be edited directly. The event "Goals.beforeInitGoalForm" will
                // be posted before this controller is initialized, therefore need to have possibility to load goal
                // directly
                this.initGoal = function (method, idGoal) {
                    initGoalForm({}, method, idGoal);
                };
            }
        };
    }
})();