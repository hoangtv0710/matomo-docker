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
 *
 */

angular.module('piwikApp').directive('piwikUsersFlowVisualization', function(){

    return {
        restrict: 'A',
        scope: {actionsPerStep: '@', levelOfDetail: '@', userFlowSource: '@'},
        templateUrl: 'plugins/UsersFlow/angularjs/visualization/sankey.directive.html?cb=' + piwik.cacheBuster,
        controller: 'UsersFlowVisualizationController',
        controllerAs: 'usersFlow',
        compile: function (element, attrs) {

            return function (scope, element, attrs, controller) {
                 controller.setRootElement(element);
            };
        }
    }
});