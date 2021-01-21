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
    angular.module('piwikApp').controller('HeatmapEditController', HeatmapEditController);

    HeatmapEditController.$inject = ['$scope', 'hsrModel', 'piwik', '$location', '$filter', '$timeout', '$rootScope'];

    function HeatmapEditController($scope, hsrModel, piwik, $location, $filter, $timeout, $rootScope) {

        var self = this;
        var currentId = null;
        var notificationId = 'hsrmanagement';

        var translate = $filter('translate');

        this.isDirty = false;
        this.model = hsrModel;
        this.model.context = 'Heatmap';
        this.showAdvancedView = false;

        var i;
        this.sampleLimits = [];
        var sampleLimits = [1000, 2000, 5000];
        for (i = 0; i < sampleLimits.length; i++) {
            this.sampleLimits.push({key: '' + sampleLimits[i], value: sampleLimits[i]});
        }

        this.sampleRates = [];
        var sampleRates = [0.1, 0.5, 1, 2, 3, 4, 5, 6, 8, 10, 15, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        for (i = 0; i < sampleRates.length; i++) {
            this.sampleRates.push({key: '' + (sampleRates[i].toFixed(1)), value: sampleRates[i] + '%'});
        }

        function getNotification()
        {
            var UI = require('piwik/UI');
            return new UI.Notification();
        }

        function removeAnyHsrNotification()
        {
            var notification = getNotification();
            notification.remove(notificationId);
            notification.remove('ajaxHelper');
        }

        function showNotification(message, context)
        {
            var notification = getNotification();
            notification.show(message, {context: context, id: notificationId});
            $timeout(function () {
                notification.scrollToNotification();
            }, 200);
        }

        function showErrorFieldNotProvidedNotification(title)
        {
            var message = _pk_translate('HeatmapSessionRecording_ErrorXNotProvided', [title]);
            showNotification(message, 'error');
        }

        function init(idSiteHsr)
        {
            self.create = idSiteHsr == '0';
            self.edit   = !self.create;
            self.siteHsr = {};
            self.showAdvancedView = false;

            piwik.helper.lazyScrollToContent();

            if (self.edit && idSiteHsr) {
                self.editTitle = 'HeatmapSessionRecording_EditHeatmapX';
                self.model.findHsr(idSiteHsr).then(function (siteHsr) {
                    if (!siteHsr) {
                        return;
                    }
                    self.siteHsr = siteHsr;
                    self.siteHsr.sample_rate = String(self.siteHsr.sample_rate);
                    self.siteHsr.sample_limit = String(self.siteHsr.sample_limit);

                    self.addInitialMatchPageRule();
                    self.isDirty = false;
                });
            } else if (self.create) {

                self.editTitle = 'HeatmapSessionRecording_CreateNewHeatmap';
                self.siteHsr = {
                    idSite: piwik.idSite,
                    name: '',
                    sample_rate: '10.0',
                    sample_limit: '1000',
                    breakpoint_mobile: $scope.breakpointMobile,
                    breakpoint_tablet: $scope.breakpointTablet
                };
                self.isDirty = false;

                var params = $location.search();

                if (params && 'name' in params && params['name']) {
                    self.siteHsr.name = params['name'];
                    self.isDirty = true;
                }

                if (params && 'matchPageRules' in params && params['matchPageRules']) {
                    self.siteHsr.match_page_rules = JSON.parse(params['matchPageRules']);
                    self.isDirty = true;
                } else {
                    self.addInitialMatchPageRule();
                }
            }
        }

        this.addInitialMatchPageRule = function () {
            if (!this.siteHsr) {
                return;
            }
            if (this.siteHsr.match_page_rules && this.siteHsr.match_page_rules.length) {
                return;
            }
            this.addMatchPageRule();
        };

        this.addMatchPageRule = function () {
            if (!this.siteHsr) {
                return;
            }
            if (!this.siteHsr.match_page_rules || !this.siteHsr.match_page_rules.length) {
                this.siteHsr.match_page_rules = [];
            }

            this.siteHsr.match_page_rules.push({attribute: 'url', type: 'equals_simple', value: '', inverted: 0});

            this.isDirty = true;
        };

        this.removeMatchPageRule = function (index) {
            if (this.siteHsr && index > -1) {
                this.siteHsr.match_page_rules.splice(index, 1);
                this.isDirty = true;
            }
        };

        this.cancel = function () {
            $scope.idSiteHsr = null;
            currentId = null;

            var $search = $location.search();
            delete $search.idSiteHsr;
            $location.search($search);
        };

        $scope.$on('$destroy', function() {
            $scope.idSiteHsr = null;
            currentId = null;
        });

        function checkRequiredFieldsAreSet()
        {
            var title;

            if (!self.siteHsr.name) {
                title = _pk_translate('General_Name');
                showErrorFieldNotProvidedNotification(title);
                return false;
            }

            if (!self.siteHsr.match_page_rules
                  || !self.siteHsr.match_page_rules.length
                  || !hsrModel.filterRules(self.siteHsr.match_page_rules).length) {
                title = _pk_translate('HeatmapSessionRecording_ErrorPageRuleRequired');
                showNotification(title, 'error');
                return false;
            }

            return true;
        }

        this.createHsr = function () {
            removeAnyHsrNotification();

            if (!checkRequiredFieldsAreSet()) {
                return;
            }

            this.isUpdating = true;

            hsrModel.createOrUpdateHsr(this.siteHsr, 'HeatmapSessionRecording.addHeatmap').then(function (response) {
                self.isUpdating = false;

                if (!response || response.type === 'error' || !response.response) {
                    return;
                }

                self.isDirty = false;

                var idSiteHsr = response.response.value;

                hsrModel.reload().then(function () {
                    if (piwik.helper.isAngularRenderingThePage()) {
                        $rootScope.$emit('updateReportingMenu');
                        var $search = $location.search();
                        $search.idSiteHsr = idSiteHsr;
                        $location.search($search);
                    } else {
                        $location.url('/?idSiteHsr=' + idSiteHsr);
                    }

                    $timeout(function () {
                        showNotification(translate('HeatmapSessionRecording_HeatmapCreated'), response.type);
                    }, 200);
                });
            }, function () {
                self.isUpdating = false;
            });
        };

        this.setValueHasChanged = function () {
            this.isDirty = true;
        };

        this.updateHsr = function () {

            removeAnyHsrNotification();

            if (!checkRequiredFieldsAreSet()) {
                return;
            }

            this.isUpdating = true;

            hsrModel.createOrUpdateHsr(this.siteHsr, 'HeatmapSessionRecording.updateHeatmap').then(function (response) {
                if (response.type === 'error') {
                    return;
                }

                var idSiteHsr = self.siteHsr.idsitehsr;

                self.isDirty = false;
                self.siteHsr = {};

                hsrModel.reload().then(function () {
                    init(idSiteHsr);
                });
                showNotification(translate('HeatmapSessionRecording_HeatmapUpdated'), response.type);
            });
        };

        $scope.$watch('idSiteHsr', function (newValue, oldValue) {
            if (newValue === null) {
                return;
            }
            if (newValue != oldValue || currentId === null) {
                currentId = newValue;
                init(newValue);
            }
        });
    }
})();