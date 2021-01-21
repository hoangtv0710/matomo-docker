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
 * <div piwik-experiment-edit-basic>
 */
(function () {
    angular.module('piwikApp').directive('piwikExperimentEditBasic', piwikExperimentEditBasic);

    piwikExperimentEditBasic.$inject = ['piwik'];

    function piwikExperimentEditBasic(piwik){

        return {
            restrict: 'A',
            templateUrl: 'plugins/AbTesting/angularjs/manage/edit/basic.directive.html?cb=' + piwik.cacheBuster
        };
    }
})();