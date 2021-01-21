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
 * <div piwik-heatmap-vis id-site-hsr="" page-mirror="">
 */
(function () {
    angular.module('piwikApp').directive('piwikHeatmapVis', piwikHeatmapVis);

    piwikHeatmapVis.$inject = ['piwik'];

    function piwikHeatmapVis(piwik){

        return {
            restrict: 'A',
            scope: {
                idSiteHsr: '=',
                deviceTypes: '=',
                heatmapTypes: '=',
                breakpointMobile: '=',
                breakpointTablet: '=',
                offsetAccuracy: '=',
                heatmapPeriod: '=',
                heatmapDate: '=',
                url: '=',
                isActive: '=',
                numSamples: '=',
                excludedElements: '='
            },
            templateUrl: 'plugins/HeatmapSessionRecording/angularjs/heatmapvis/heatmapvis.directive.html?cb=' + piwik.cacheBuster,
            controller: 'HeatmapVisController',
            controllerAs: 'heatmapVis'
        };
    }
})();