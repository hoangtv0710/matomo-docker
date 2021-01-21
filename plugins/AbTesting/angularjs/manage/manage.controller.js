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
    angular.module('piwikApp').controller('ManageExperimentsController', ManageExperimentsController);

    ManageExperimentsController.$inject = ['$scope', '$rootScope', '$location'];

    function ManageExperimentsController($scope, $rootScope, $location) {

        this.editMode = false;

        var self = this;

        function removeAnyExperimentNotification()
        {
            var UI = require('piwik/UI');
            new UI.Notification().remove('experimentsmanagement');
        }

        function initState() {
            var $search = $location.search();
            if ('idExperiment' in $search) {

                if ($search.idExperiment === 0 || $search.idExperiment === '0') {
                    var parameters = {isAllowed: true};
                    $rootScope.$emit('AbTesting.initAddExperiment', parameters);
                    if (parameters && !parameters.isAllowed) {
                        self.editMode = false;
                        self.idExperiment = null;

                        return;
                    }
                }

                self.editMode = true;
                self.idExperiment = parseInt($search.idExperiment, 10);
            } else {
                self.editMode = false;
                self.idExperiment = null;
            }

            removeAnyExperimentNotification();
        }

        initState();

        var onChangeSuccess = $rootScope.$on('$locationChangeSuccess', initState);

        $scope.$on('$destroy', function() {
            if (onChangeSuccess) {
                onChangeSuccess();
            }
        });
    }
})();
