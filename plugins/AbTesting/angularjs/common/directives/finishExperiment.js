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
 * <a href="javascript:;"
 piwik-finish-experiment
 idexperiment="{{ experiment.idexperiment }}">{{ 'AbTesting_ActionFinishExperiment'|translate }}</a>
 */
(function () {
    angular.module('piwikApp.directive').directive('piwikFinishExperiment', piwikFinishExperiment);

    piwikFinishExperiment.$inject = ['experimentModel', 'piwik'];

    function piwikFinishExperiment(experimentModel, piwik) {

        return {
            restrict: 'A',
            link: function (scope, element, attrs) {
                if (!attrs.idexperiment) {
                    return;
                }

                var idExperiment = attrs.idexperiment;

                element.on('click', function () {

                    function doStop() {
                        experimentModel.finishExperiment(idExperiment).then(function (response) {
                            if (response.type === 'error') {
                                return;
                            }

                            piwik.helper.redirect();
                        });
                    }

                    piwikHelper.modalConfirm('#confirmFinishExperiment', {yes: doStop});
                });
            }
        };
    }
})();
