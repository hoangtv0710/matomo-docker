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
    angular.module('piwikApp').factory('experimentModel', experimentModel);

    experimentModel.$inject = ['piwikApi', '$q'];

    function experimentModel(piwikApi, $q) {
        var fetchPromise = {};

        var model = {
            experiments : [],
            goals : [],
            isLoading: false,
            isUpdating: false,
            filterStatus: '',
            fetchExperiments: fetchExperiments,
            findExperiment: findExperiment,
            deleteExperiment: deleteExperiment,
            archiveExperiment: archiveExperiment,
            finishExperiment: finishExperiment,
            createOrUpdateExperiment: createOrUpdateExperiment,
            fetchJsExperimentTemplate: fetchJsExperimentTemplate,
            fetchJsIncludeTemplate: fetchJsIncludeTemplate,
            fetchAvailableSuccessMetrics: fetchAvailableSuccessMetrics,
            fetchAvailableStatuses: fetchAvailableStatuses,
            reload: reload
        };

        return model;

        function reload()
        {
            model.experiments = [];
            fetchPromise = {};
            return fetchExperiments();
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

        function fetchExperiments() {
            var params = {filter_limit: '-1'};
            var key;

            if (model.filterStatus) {
                params.method = 'AbTesting.getExperimentsByStatuses';
                params.statuses = model.filterStatus;
                key = params.method + params.statuses;
            } else {
                params.method = 'AbTesting.getActiveExperiments';
                key = params.method;
            }

            if (!fetchPromise[key]) {
                fetchPromise[key] = piwikApi.fetch(params);
            }

            model.isLoading = true;
            model.experiments = [];

            return fetchPromise[key].then(function (experiments) {
                model.experiments = experiments;
                model.isLoading = false;
                return experiments;
            }, function () {
                model.isLoading = false;
            });
        }

        function fetchAvailableSuccessMetrics() {
            return piwikApi.fetch({method: 'AbTesting.getAvailableSuccessMetrics', filter_limit: '-1'});
        }

        function fetchAvailableStatuses() {
            return piwikApi.fetch({method: 'AbTesting.getAvailableStatuses', filter_limit: '-1'});
        }

        function fetchJsExperimentTemplate(idExperiment) {
            return piwikApi.fetch({method: 'AbTesting.getJsExperimentTemplate', idExperiment: idExperiment});
        }

        function fetchJsIncludeTemplate() {
            return piwikApi.fetch({method: 'AbTesting.getJsIncludeTemplate'});
        }

        function findExperiment(idExperiment) {

            // before going through an API request we first try to find it in loaded experiments
            var found;
            angular.forEach(model.experiments, function (experiment) {
                if (parseInt(experiment.idexperiment, 10) === idExperiment) {
                    found = experiment;
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
                idExperiment: idExperiment,
                method: 'AbTesting.getExperiment'
            }).then(function (experiment) {
                model.isLoading = false;
                return experiment;

            }, function (error) {
                model.isLoading = false;
            });
        }

        function deleteExperiment(idExperiment) {

            model.isUpdating = true;
            model.experiments = [];

            piwikApi.withTokenInUrl();

            return piwikApi.fetch({idExperiment: idExperiment, method: 'AbTesting.deleteExperiment'}).then(function (response) {
                model.isUpdating = false;

                return {type: 'success'};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

        function archiveExperiment(idExperiment) {

            model.isUpdating = true;

            return piwikApi.fetch({idExperiment: idExperiment, method: 'AbTesting.archiveExperiment'}).then(function (response) {
                model.isUpdating = false;

                return {type: 'success'};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

        function createOrUpdateExperiment(experiment, method) {
            experiment = angular.copy(experiment);
            experiment.method = method;

            var map = {
                idExperiment: 'idexperiment',
                confidenceThreshold: 'confidence_threshold',
                startDate: 'start_date',
                endDate: 'end_date',
                successMetrics: 'success_metrics',
                includedTargets: 'included_targets',
                excludedTargets: 'excluded_targets',
                percentageParticipants: 'percentage_participants',
                mdeRelative: 'mde_relative'
            };
            angular.forEach(map, function (value, key) {
                if (typeof experiment[value] !== 'undefined') {
                    experiment[key] = experiment[value];
                    delete experiment[value];
                }
            });

            angular.forEach(['name', 'description', 'hypothesis'], function (param) {
                if (experiment[param]) {
                    // trim values
                    experiment[param] = experiment[param].replace(/^\s+|\s+$/g, '');
                }
            });

            experiment.includedTargets = arrayFilter(experiment.includedTargets, function (target) {
                return !!(target && (target.value || target.type == 'any'));
            });

            experiment.excludedTargets = arrayFilter(experiment.excludedTargets, function (target) {
                return !!(target && target.value);
            });

            experiment.successMetrics = arrayFilter(experiment.successMetrics, function (metric) {
                return !!(metric && metric.metric);
            });

            experiment.variations = arrayFilter(experiment.variations, function (variation) {
                return !!(variation && variation.name);
            });

            if (experiment.original_redirect_url) {
                experiment.variations.push({name: 'original', redirect_url: experiment.original_redirect_url});
            }

            var postParams = ['successMetrics', 'includedTargets', 'excludedTargets', 'variations'];
            var post = {};
            for (var i = 0; i < postParams.length; i++) {
                var postParam = postParams[i];
                if (typeof experiment[postParam] !== 'undefined') {
                    post[postParam] = experiment[postParam];
                    delete experiment[postParam];
                }
            }

            model.isUpdating = true;

            piwikApi.withTokenInUrl();

            return piwikApi.post(experiment, post).then(function (response) {
                model.isUpdating = false;

                return {type: 'success', response: response};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

        function finishExperiment(idExperiment) {

            model.isUpdating = true;

            piwikApi.withTokenInUrl();

            return piwikApi.fetch({idExperiment: idExperiment, method: 'AbTesting.finishExperiment'}).then(function (response) {
                model.isUpdating = false;

                return {type: 'success', response: response};

            }, function (error) {
                model.isUpdating = false;
                return {type: 'error', message: error};
            });
        }

    }
})();