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
    angular.module('piwikApp').controller('SessionRecordingListController', SessionRecordingListController);

    SessionRecordingListController.$inject = ['$scope', 'hsrModel', 'piwik', 'piwikApi', '$location', '$rootScope'];

    function SessionRecordingListController($scope, hsrModel, piwik, piwikApi, $location, $rootScope) {

        this.model = hsrModel;
        this.model.context = 'SessionRecording';
        this.model.filterStatus = '';

        var self = this;

        this.createHsr = function () {
            this.editHsr(0);
        };

        this.editHsr = function (idSiteHsr) {
            var $search = $location.search();
            $search.idSiteHsr = idSiteHsr;
            $location.search($search);
        };

        this.deleteHsr = function (hsr) {
            function doDelete() {
                hsrModel.deleteHsr(hsr.idsitehsr).then(function () {
                    hsrModel.reload();

                    $rootScope.$emit('updateReportingMenu');
                });
            }

            piwik.helper.modalConfirm('#confirmDeleteSessionRecording', {yes: doDelete});
        };

        this.completeHsr = function (hsr) {
            function doComplete() {
                hsrModel.completeHsr(hsr.idsitehsr).then(function () {
                    hsrModel.reload();
                });
            }

            piwik.helper.modalConfirm('#confirmEndSessionRecording', {yes: doComplete});
        };

        this.model.fetchHsrs();
    }
})();