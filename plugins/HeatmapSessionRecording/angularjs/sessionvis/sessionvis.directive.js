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
 * <div piwik-session-recording-vis >
 */
(function () {
    angular.module('piwikApp').directive('piwikSessionRecordingVis', piwikSessionRecordingVis);

    piwikSessionRecordingVis.$inject = ['piwik'];

    function piwikSessionRecordingVis(piwik){

        return {
            restrict: 'A',
            scope: {
                offsetAccuracy: '=',
                scrollAccuracy: '=',
                autoPlayEnabled: '=',
                skipPausesEnabled: '=',
                replaySpeed: '='
            },
            templateUrl: 'plugins/HeatmapSessionRecording/angularjs/sessionvis/sessionvis.directive.html?cb=' + piwik.cacheBuster,
            controller: 'SessionRecordingVisController',
            controllerAs: 'sessionVis'
        };
    }
})();