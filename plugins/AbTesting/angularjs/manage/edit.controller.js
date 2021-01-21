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
    angular.module('piwikApp').controller('ExperimentEditController', ExperimentEditController);

    ExperimentEditController.$inject = ['$scope', 'experimentModel', 'piwik', '$location', '$filter', '$timeout', '$rootScope'];

    function ExperimentEditController($scope, experimentModel, piwik, $location, $filter, $timeout, $rootScope) {

        var self = this;
        var currentId = null;
        var notificationId = 'experimentsmanagement';

        var translate = $filter('translate');

        this.isDirty = false;
        this.model = experimentModel;
        this.jsIncludeTemplateCode = '';

        this.model.fetchJsIncludeTemplate().then(function (response) {
            self.jsIncludeTemplateCode = response.value;
        });

        this.percentageParticipantsOptions = [];
        var percentageParticipants = [1,5,10,15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,100];
        for (var i = 0; i < percentageParticipants.length; i++) {
            this.percentageParticipantsOptions.push({key: '' + percentageParticipants[i], value: percentageParticipants[i] + '%'});
        }

        this.mdeRelativeOptions = [];
        var mdeRelativeOptions = [1,2,3,4,5,8,10,15,20,25,30,40,50,60,70,75,80,90,100,125,150,200,300];
        for (var i = 0; i < mdeRelativeOptions.length; i++) {
            this.mdeRelativeOptions.push({key: '' + mdeRelativeOptions[i], value: mdeRelativeOptions[i] + '%'});
        }
        this.trafficAllocationOptions = [];
        for (var i = 0; i < 101; i++) {
            this.trafficAllocationOptions.push({key: i, value: i + '%'});
        }

        this.confidenceThresholdOptions = [];
        var confidenceThresholdOptions = ['90.0','95.0', '98.0', '99.0', '99.5'];
        for (var i = 0; i < confidenceThresholdOptions.length; i++) {
            this.confidenceThresholdOptions.push({key: '' + confidenceThresholdOptions[i], value: confidenceThresholdOptions[i] + '%'});
        }

        this.createExperimentTargetTypes = [
            {key: 'any', value: translate('AbTesting_ActivateExperimentOnAllPages')},
            {key: 'equals_simple', value: translate('AbTesting_ActiveExperimentOnSomePages')}
        ];

        function setUtcTime() {
            self.utcTime = getUtcTime();
            $timeout(setUtcTime, 10000);
        }

        setUtcTime();

        experimentModel.fetchAvailableSuccessMetrics().then(function (metrics) {
            var options = [];
            if (metrics && metrics.length) {
                for (var i = 0; i < metrics.length; i++) {
                    options.push({key: metrics[i].value, value: metrics[i].name});
                }
            }

            self.successMetricOptions = options;
        });

        function getUtcTime() {
            var date = new Date();
            if (date && date.toUTCString) {
                return date.toUTCString();
            }
        }

        function getNotification()
        {
            var UI = require('piwik/UI');
            return new UI.Notification();
        }

        function removeAnyExperimentNotification()
        {
            var notification = getNotification();
            notification.remove('experimentsmanagement');
            notification.remove('ajaxHelper');
        }

        function showNotification(message, context)
        {
            var notification = getNotification();
            notification.show(message, {context: context, id: notificationId});
            $timeout(function () {
                notification.scrollToNotification();
            }, 100);
        }

        function showErrorFieldNotProvidedNotification(title)
        {
            var message = _pk_translate('AbTesting_ErrorXNotProvided', [title]);
            showNotification(message, 'error');
        }

        function hasSuccessMetric(successMetric) {

            if (!self.successMetricOptions) {
                return;
            }

            for (i = 0; i < self.successMetricOptions.length; i++) {
                var metric = self.successMetricOptions[i];
                if (metric && metric.key === successMetric) {
                    return true;
                }
            }

            return false;
        }

        function init(idExperiment)
        {
            self.create = idExperiment == '0';
            self.edit   = !self.create;
            self.confirmedEdit = false;
            self.action = 'basic';
            self.experiment = {};
            self.jsTemplateCode = '';

            if (self.edit && idExperiment) {
                self.editTitle = 'AbTesting_EditExperiment';
                self.model.findExperiment(idExperiment).then(function (experiment) {
                    if (!experiment) {
                        return;
                    }

                    self.experiment = experiment;
                    self.confirmedEdit = experiment.status !== 'running' && experiment.status !== 'finished';

                    if (!experiment.variations.length) {
                        self.addVariation();
                    }

                    self.addDefaultTargetIfNeeded();
                    self.addDefaultSuccessMetricIfNeeded();

                    var parts;
                    if (self.experiment.start_date) {
                        parts = self.experiment.start_date.split(' ');
                        self.experiment.start_date_date = parts[0];
                        self.experiment.start_date_time = parts[1];
                    }

                    if (self.experiment.end_date) {
                        parts = self.experiment.end_date.split(' ');
                        self.experiment.end_date_date = parts[0];
                        self.experiment.end_date_time = parts[1];
                    }

                    self.model.fetchJsExperimentTemplate(idExperiment).then(function (response) {
                        self.jsExperimentTemplateCode = response.value;
                    });

                    self.isDirty = false;

                });
            } else if (self.create) {
                self.editTitle = 'AbTesting_CreateNewExperiment';
                self.experiment = {
                    idSite: piwik.idSite,
                    name: '',
                    description: '',
                    hypothesis: '',
                    variations: [],
                    confidence_threshold: '95.0'
                };
                self.addVariation();
                self.addDefaultTargetIfNeeded();
                self.isDirty = false;
            }
        }

        this.addDefaultTargetIfNeeded = function () {
            if (this.experiment &&
                (!this.experiment.included_targets || !this.experiment.included_targets.length)) {
                this.experiment.included_targets = [{attribute: 'url', type: 'any', value: '', inverted: 0}];
            }

            if (this.experiment &&
                (!this.experiment.excluded_targets || !this.experiment.excluded_targets.length)) {
                this.experiment.excluded_targets = [{attribute: 'url', type: 'equals_exactly', value: '', inverted: 0}];
            }
        };

        this.addDefaultSuccessMetricIfNeeded = function () {
            if (this.experiment &&
                (!this.experiment.success_metrics || !this.experiment.success_metrics.length)) {
                this.experiment.success_metrics = [];

                var defaultMetric = 'nb_conversions';
                if (!hasSuccessMetric(defaultMetric)) {
                    defaultMetric = 'nb_pageviews';
                }

                this.experiment.success_metrics.push({metric: defaultMetric});

                if (hasSuccessMetric('nb_orders')) {
                    this.experiment.success_metrics.push({metric: 'nb_orders'});
                }
                if (hasSuccessMetric('nb_orders_revenue')) {
                    this.experiment.success_metrics.push({metric: 'nb_orders_revenue'});
                }
            }
        };

        this.addSuccessMetric = function () {
            this.experiment.success_metrics.push({metric: ''});
            this.isDirty = true;
        };

        this.removeSuccessMetric = function (index) {
            if (index > -1) {
                this.experiment.success_metrics.splice(index, 1);
                this.isDirty = true;
            }
        };

        this.getNumVariations = function()
        {
            if (!this.experiment || !this.experiment.variations) {
                return 0;
            }

            return this.experiment.variations.length;
        };

        this.isVariationNameAlreadyUsed = function (variationName) {
            var used = false;
            angular.forEach(this.experiment.variations, function (variation) {
                if (variation.name && variationName === variation.name) {
                    used = true;
                }
            });

            return used;
        };

        this.removeVariation = function(index) {
            if (index > -1) {
                this.experiment.variations.splice(index, 1);
                this.isDirty = true;
            }
        };

        this.addVariation = function() {
            var index = this.getNumVariations() + 1;
            var name = 'Variation' + index;
            while (this.isVariationNameAlreadyUsed(name)) {
                name += '_';
            }

            this.experiment.variations.push({name: name, percentage: ''});
            this.isDirty = true;
        };

        this.removeIncludedTarget = function(index) {
            if (index > -1) {
                this.experiment.included_targets.splice(index, 1);
                this.isDirty = true;
            }
        };

        this.removeExcludedTarget = function(index) {
            if (index > -1) {
                this.experiment.excluded_targets.splice(index, 1);
                this.isDirty = true;
            }
        };

        this.addIncludedTarget = function() {
            this.experiment.included_targets.push({attribute: 'url', type: 'equals_simple', value: '', inverted: 0});
            this.isDirty = true;
        };

        this.addExcludedTarget = function() {
            this.experiment.excluded_targets.push({attribute: 'url', type: 'equals_simple', value: '', inverted: 0});
            this.isDirty = true;
        };

        this.shouldAllocateMoreTrafficToOriginalVariation = function ()
        {
            // eg 20% when there are 4 variations + 1 original by default
            var original = this.getDefaultVariationPercentage();

            var numberOfOriginalVariations = 1;
            var numVariations = this.getNumVariations() + 1;

            // eg 20% when there are 4 variations + 1 original by default
            var defaultPercentageWhenNotCustomizedTraffic = Math.round(100 / numVariations);
            // eg 10%
            var halfNeededTraffic = Math.floor(defaultPercentageWhenNotCustomizedTraffic / 2);

            // has allocated eg less than 10% to original, we recommend to allocate more
            if (halfNeededTraffic > original) {
                return true;
            }

            return false;
        };

        this.getDefaultVariationPercentage = function () {
            if (!this.experiment || !this.experiment.variations) {
                return 0;
            }

            var percentageUsed = 100;
            var numberOfOriginalVariations = 1;
            var numVariations = this.getNumVariations() + numberOfOriginalVariations;

            angular.forEach(this.experiment.variations, function (variation) {
                if (variation && variation.percentage) {
                    percentageUsed = percentageUsed - parseInt(variation.percentage, 10);
                    numVariations--;
                }
            });

            if (numVariations > 0) {
                var result = Math.round(percentageUsed / numVariations);

                if (result > 100) {
                    result = 100;
                }

                if (result < 0) {
                    result = 0;
                }

                return result;
            }

            return 0;
        };

        this.hasAllocated100PercentToVariations = function () {
            if (!this.experiment || !this.experiment.variations) {
                return false;
            }

            var percentage = 0; // for original

            angular.forEach(this.experiment.variations, function (variation) {
                if (variation && variation.percentage) {
                    percentage += parseInt(variation.percentage, 10);
                }
            });

            return 100 >= percentage;
        };

        this.finishExperiment = function () {
            this.isUpdating = true;

            var idExperiment = this.experiment.idexperiment;

            function doStop() {
                experimentModel.finishExperiment(idExperiment).then(function (response) {
                    if (response.type === 'error') {
                        return;
                    }

                    init(idExperiment);
                    showNotification(translate('AbTesting_ExperimentFinished'), response.type);
                    experimentModel.reload();
                });
            }

            piwikHelper.modalConfirm('#confirmFinishExperiment', {yes: doStop});
        };

        this.cancel = function () {
            $scope.idExperiment = null;
            currentId = null;

            var $search = $location.search();
            delete $search.idExperiment;
            $location.search($search);
        };

        function checkRequiredFieldsAreSet()
        {
            var title;

            if (!self.experiment.name) {
                title = _pk_translate('AbTesting_ExperimentName');
                showErrorFieldNotProvidedNotification(title);
                return false;
            }

            if (!self.experiment.hypothesis) {
                title = _pk_translate('AbTesting_Hypothesis');
                showErrorFieldNotProvidedNotification(title);
                return false;
            }

            if (!self.experiment.description) {
                title = _pk_translate('General_Description');
                showErrorFieldNotProvidedNotification(title);
                return false;
            }

            return true;
        }

        this.createExperiment = function () {
            var method = 'AbTesting.addExperiment';

            removeAnyExperimentNotification();

            if (!checkRequiredFieldsAreSet()) {
                return;
            }

            if (this.experiment.included_targets[0] &&
                this.experiment.included_targets[0].type &&
                this.experiment.included_targets[0].type == 'equals_simple') {

                if (!this.experiment.included_targets[0].value) {
                    showNotification(translate('AbTesting_ErrorCreateNoUrlDefined'), 'error');
                    return;
                }

                this.experiment.included_targets = [{
                    attribute: 'url', inverted: '0', type: 'equals_simple', value: this.experiment.included_targets[0].value
                }];
            } else {
                this.experiment.included_targets = [{attribute: 'url', inverted: '0', type: 'any', value: ''}];
            }

            this.addDefaultSuccessMetricIfNeeded();

            this.isUpdating = true;

            experimentModel.createOrUpdateExperiment(this.experiment, method).then(function (response) {
                if (response.type === 'error') {
                    return;
                }

                self.isDirty = false;

                var idExperiment = response.response.value;

                experimentModel.reload().then(function () {
                    if (piwik.helper.isAngularRenderingThePage()) {
                        $rootScope.$emit('updateReportingMenu');
                        var $search = $location.search();
                        $search.idExperiment = idExperiment;
                        $location.search($search);
                    } else {
                        $location.url('/?idExperiment=' + idExperiment);
                    }

                    $timeout(function () {
                        showNotification(translate('AbTesting_ExperimentCreated'), response.type);
                    }, 200);
                });
            });
        };

        this.showEmbedAction = function () {
            if (!this.isDirty) {
                this.action='embed';
                return;
            }

            piwikHelper.modalConfirm('#updateExperimentNeededToEmbed', {yes: function () {}});
        };

        this.setValueHasChanged = function () {
            this.isDirty = true;
        };

        this.updateExperiment = function () {

            removeAnyExperimentNotification();

            if (!checkRequiredFieldsAreSet()) {
                return;
            }

            var method = 'AbTesting.updateExperiment';

            this.isUpdating = true;

            var willUpdateStartExperiment = false;

            if (this.experiment.start_date) {
                var startDate = $filter('toLocalTime')(this.experiment.start_date, false);
                var now = new Date();
                if (startDate <= now && this.experiment.status === 'created') {
                    willUpdateStartExperiment = true;
                }
            }

            function doUpdateExperiment()
            {
                experimentModel.createOrUpdateExperiment(self.experiment, method).then(function (response) {
                    if (response.type === 'error') {
                        return;
                    }

                    var idexperiment = self.experiment.idexperiment;

                    self.isDirty = false;
                    self.experiment = {};

                    experimentModel.reload().then(function () {
                        init(idexperiment);
                    });
                    showNotification(translate('AbTesting_ExperimentUpdated'), response.type);
                });
            }

            if (willUpdateStartExperiment) {
                piwikHelper.modalConfirm('#confirmUpdateStartExperiment', {yes: doUpdateExperiment});
            } else {
                doUpdateExperiment();
            }
        };

        $scope.$watch('idExperiment', function (newValue, oldValue) {
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