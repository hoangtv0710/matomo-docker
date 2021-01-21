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
    angular.module('piwikApp').controller('SessionRecordingEditController', SessionRecordingEditController);

    SessionRecordingEditController.$inject = ['$scope', 'hsrModel', 'piwik', 'piwikApi', '$location', '$filter', '$timeout', '$rootScope'];

    function SessionRecordingEditController($scope, hsrModel, piwik, piwikApi, $location, $filter, $timeout, $rootScope) {

        this.isDirty = false;
        this.model = hsrModel;
        this.model.context = 'SessionRecording';
        this.showAdvancedView = false;

        var self = this;
        var currentId = null;
        var notificationId = 'hsrmanagement';

        var translate = $filter('translate');
        this.sampleLimits = [];

        var i;
        piwikApi.fetch({method: 'HeatmapSessionRecording.getAvailableSessionRecordingSampleLimits'}).then(function (sampleLimits) {
            self.sampleLimits = [];
            if (sampleLimits && angular.isArray(sampleLimits)) {
                for (i = 0; i < sampleLimits.length; i++) {
                    self.sampleLimits.push({key: '' + sampleLimits[i], value: sampleLimits[i]});
                }
            }
        });

        this.minSessionTimes = [];
        var minSessionTimes = [0, 5, 10, 15, 20, 30, 45, 60, 90, 120];
        for (i = 0; i < minSessionTimes.length; i++) {
            this.minSessionTimes.push({key: '' + minSessionTimes[i], value: minSessionTimes[i] + ' seconds'});
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
                self.editTitle = 'HeatmapSessionRecording_EditSessionRecordingX';
                self.model.findHsr(idSiteHsr).then(function (siteHsr) {
                    if (!siteHsr) {
                        return;
                    }
                    self.siteHsr = siteHsr;
                    self.siteHsr.sample_rate = String(self.siteHsr.sample_rate);
                    self.siteHsr.sample_limit = String(self.siteHsr.sample_limit);
                    self.siteHsr.min_session_time = String(self.siteHsr.min_session_time);
                    self.siteHsr.requires_activity = Boolean(self.siteHsr.requires_activity);
                    self.siteHsr.capture_keystrokes = Boolean(self.siteHsr.capture_keystrokes);

                    self.addInitialMatchPageRule();
                    self.isDirty = false;
                });
            } else if (self.create) {
                self.editTitle = 'HeatmapSessionRecording_CreateNewSessionRecording';
                self.siteHsr = {
                    idSite: piwik.idSite,
                    name: '',
                    sample_rate: '10.0',
                    sample_limit: '250',
                    min_session_time: '0',
                    requires_activity: true,
                    capture_keystrokes: false
                };
                self.addInitialMatchPageRule();
                self.isDirty = false;
            }
        }

        this.addInitialMatchPageRule = function () {
            if (!this.siteHsr) {
                return;
            }
            if (this.siteHsr.match_page_rules && this.siteHsr.match_page_rules.length) {
                return;
            }
            this.siteHsr.match_page_rules = [{attribute: 'url', type: 'any', value: '', inverted: 0}];
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

            hsrModel.createOrUpdateHsr(this.siteHsr, 'HeatmapSessionRecording.addSessionRecording').then(function (response) {
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
                        showNotification(translate('HeatmapSessionRecording_SessionRecordingCreated'), response.type);
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

            hsrModel.createOrUpdateHsr(this.siteHsr, 'HeatmapSessionRecording.updateSessionRecording').then(function (response) {
                if (response.type === 'error') {
                    return;
                }

                var idSiteHsr = self.siteHsr.idsitehsr;

                self.isDirty = false;
                self.siteHsr = {};

                hsrModel.reload().then(function () {
                    init(idSiteHsr);
                });
                showNotification(translate('HeatmapSessionRecording_SessionRecordingUpdated'), response.type);
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