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
    angular.module('piwikApp').controller('ManageHeatmapController', ManageHeatmapController);

    ManageHeatmapController.$inject = ['$scope', '$rootScope', '$location'];

    function ManageHeatmapController($scope, $rootScope, $location) {

        this.editMode = false;

        var self = this;

        this.breakpointMobile = $scope.breakpointMobile;
        this.breakpointTablet = $scope.breakpointTablet;

        function removeAnyHsrNotification()
        {
            var UI = require('piwik/UI');
            new UI.Notification().remove('hsrmanagement');
        }

        function initState() {
            var $search = $location.search();
            if ('idSiteHsr' in $search) {
                if ($search.idSiteHsr === 0 || $search.idSiteHsr === '0') {

                    var parameters = {isAllowed: true};
                    $rootScope.$emit('HeatmapSessionRecording.initAddHeatmap', parameters);
                    if (parameters && !parameters.isAllowed) {

                        self.editMode = false;
                        self.idSiteHsr = null;

                        return;
                    }
                }
                self.editMode = true;
                self.idSiteHsr = parseInt($search.idSiteHsr, 10);
            } else {
                self.editMode = false;
                self.idSiteHsr = null;
            }

            removeAnyHsrNotification();
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