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
    angular.module('piwikApp').factory('hsrModel', hsrModel);

    hsrModel.$inject = ['piwikApi', '$q', '$filter'];

    function hsrModel(piwikApi, $q, $filter) {
        var fetchPromise = {};
        var goalsPromise = null;
        var translate = $filter('translate');

        var model = {
            hsrs : [],
            allHsrs : [],
            isLoading: false,
            isUpdating: false,
            context: 'Heatmap',
            findHsr: findHsr,
            createOrUpdateHsr: createOrUpdateHsr,
            deleteHsr: deleteHsr,
            completeHsr: completeHsr,
            reload: reload,
            fetchHsrs: fetchHsrs,
            filterRules: filterRules,
            fetchAvailableStatuses: fetchAvailableStatuses,
            onFilterStatusChange: onFilterStatusChange,
            statusOptions: [],
            filterStatus: ''
        };

        model.statusOptions.push({key: '', value: translate('General_All')});
        model.statusOptions.push({key: 'active', value: translate('HeatmapSessionRecording_StatusActive')});
        model.statusOptions.push({key: 'ended', value: translate('HeatmapSessionRecording_StatusEnded')});

        return model;

        function reload()
        {
            model.hsrs = [];
            model.allHsrs = [];
            fetchPromise = {};
            return fetchHsrs();
        }

        function filterRules(rules)
        {
            return arrayFilter(rules, function (target) {
                return !!target && (target.value || target.type === 'any');
            });
        }

        function arrayFilter(array, filter)
        {
            var entries = [];

            angular.forEach(array, function (value) {
                if (filter(value)) {
                    entries.push(value);
                }
            });

            return entries;
        }

        function getApiMethodInContext(apiMethod)
        {
            return apiMethod + model.context;
        }

        function fetchAvailableStatuses() {
            return piwikApi.fetch({method: 'HeatmapSessionRecording.getAvailableStatuses'});
        }

        function onFilterStatusChange()
        {
            if (!model.filterStatus) {
                model.hsrs = model.allHsrs;
            } else {
                model.hsrs = $filter('filter')(model.allHsrs, model.filterStatus, true, 'status');
            }
        }

        function fetchHsrs() {
            var method = 'HeatmapSessionRecording.getHeatmaps';
            if (model.context === 'SessionRecording') {
                method = 'HeatmapSessionRecording.getSessionRecordings';
            }

            var params = {method: method, filter_limit: '-1'};
            var key = params.method;

            if (!fetchPromise[key]) {
                fetchPromise[key] = piwikApi.fetch(params);
            }

            model.isLoading = true;
            model.hsrs = [];
            model.allHsrs = [];

            return fetchPromise[key].then(function (hsrs) {
                model.allHsrs = hsrs;
                onFilterStatusChange();

                model.isLoading = false;
                return hsrs;
            }, function () {
                model.isLoading = false;
            });
        }

        function findHsr(idSiteHsr) {

            // before going through an API request we first try to find it in loaded hsrs
            var found;
            angular.forEach(model.allHsrs, function (hsr) {
                if (!found && parseInt(hsr.idsitehsr, 10) === idSiteHsr) {
                    found = hsr;
                }
            });

            if (found) {
                var deferred = $q.defer();
                deferred.resolve(found);
                return deferred.promise;
            }

            // otherwise we fetch it via API
            model.isLoading = true;

            return piwikApi.fetch({
                idSiteHsr: idSiteHsr,
                method: getApiMethodInContext('HeatmapSessionRecording.get'), filter_limit: '-1'
            }).then(function (record) {
                model.isLoading = false;
                return record;

            }, function (error) {
                model.isLoading = false;
            });
        }

        function deleteHsr(idSiteHsr) {

            model.isUpdating = true;
            model.hsrs = [];
            model.allHsrs = [];

            piwikApi.withTokenInUrl();

            return piwikApi.fetch({idSiteHsr: idSiteHsr, method: getApiMethodInContext('HeatmapSessionRecording.delete')}).then(function (response) {
                model.isUpdating = false;

                return {type: 'success'};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

        function completeHsr(idSiteHsr) {

            model.isUpdating = true;
            model.hsrs = [];
            model.allHsrs = [];

            piwikApi.withTokenInUrl();

            return piwikApi.fetch({idSiteHsr: idSiteHsr, method: getApiMethodInContext('HeatmapSessionRecording.end')}).then(function (response) {
                model.isUpdating = false;

                return {type: 'success'};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

        function createOrUpdateHsr(hsr, method) {
            hsr = angular.copy(hsr);
            hsr.method = method;

            var map = {
                idSiteHsr: 'idsitehsr',
                matchPageRules: 'match_page_rules',
                sampleLimit: 'sample_limit',
                sampleRate: 'sample_rate',
                excludedElements: 'excluded_elements',
                screenshotUrl: 'screenshot_url',
                breakpointMobile: 'breakpoint_mobile',
                breakpointTablet: 'breakpoint_tablet',
                minSessionTime: 'min_session_time',
                requiresActivity: 'requires_activity',
                captureKeystrokes: 'capture_keystrokes',
            };

            angular.forEach(map, function (value, key) {
                if (typeof hsr[value] !== 'undefined') {
                    hsr[key] = hsr[value];
                    delete hsr[value];
                }
            });

            hsr.matchPageRules = filterRules(hsr.matchPageRules);
            hsr.requiresActivity = hsr.requiresActivity ? '1' : '0';
            hsr.captureKeystrokes = hsr.captureKeystrokes ? '1' : '0';
            delete hsr.page_treemirror; // would be too big to post again to server and not needed to be saved

            angular.forEach(['name', 'excludedElements', 'screenshotUrl'], function (param) {
                if (hsr[param]) {
                    // trim values
                    hsr[param] = String(hsr[param]).replace(/^\s+|\s+$/g, '');
                }
            });

            var postParams = ['matchPageRules'];
            var post = {};
            for (var i = 0; i < postParams.length; i++) {
                var postParam = postParams[i];
                if (typeof hsr[postParam] !== 'undefined') {
                    post[postParam] = hsr[postParam];
                    delete hsr[postParam];
                }
            }

            model.isUpdating = true;

            piwikApi.withTokenInUrl();

            return piwikApi.post(hsr, post).then(function (response) {
                model.isUpdating = false;

                return {type: 'success', response: response};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

    }
})();