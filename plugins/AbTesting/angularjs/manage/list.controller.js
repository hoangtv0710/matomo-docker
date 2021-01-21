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
    angular.module('piwikApp').controller('ExperimentsListController', ExperimentsListController);

    ExperimentsListController.$inject = ['$scope', 'experimentModel', 'piwik', '$location', '$rootScope'];

    function ExperimentsListController($scope, experimentModel, piwik, $location, $rootScope) {

        this.siteName = piwik.siteName;
        this.model = experimentModel;

        var self = this;
        experimentModel.fetchAvailableStatuses().then(function (statuses) {
            self.statusOptions = [];
            self.statusOptions.push({key: '', value: _pk_translate('AbTesting_StatusActive')});

            if (statuses && statuses.length) {
                for (var i = 0; i < statuses.length; i++) {
                    self.statusOptions.push({key: statuses[i].value, value: statuses[i].name});
                }
            }
        });

        this.createExperiment = function () {
            this.editExperiment(0);
        };

        this.editExperiment = function (idExperiment) {
            var $search = $location.search();
            $search.idExperiment = idExperiment;
            $location.search($search);
        };

        this.deleteExperiment = function (experiment) {
            function doDelete() {
                experimentModel.deleteExperiment(experiment.idexperiment).then(function () {
                    experimentModel.reload();
                });
            }

            piwikHelper.modalConfirm('#confirmDeleteExperiment', {yes: doDelete});
        };

        this.archiveExperiment = function (experiment) {

            function doArchive() {
                experimentModel.archiveExperiment(experiment.idexperiment).then(function () {
                    experimentModel.reload();
                });
            }

            piwikHelper.modalConfirm('#confirmArchiveExperiment', {yes: doArchive});
        };

        this.onFilterStatusChange = function () {
            this.model.fetchExperiments();
        };

        this.onFilterStatusChange();
    }
})();