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
 piwik-archive-experiment
 idexperiment="{{ experiment.idexperiment }}">{{ 'AbTesting_ActionArchiveExperiment'|translate }}</a>
 */
(function () {
    angular.module('piwikApp.directive').directive('piwikArchiveExperiment', piwikArchiveExperiment);

    piwikArchiveExperiment.$inject = ['experimentModel', 'piwik'];

    function piwikArchiveExperiment(experimentModel, piwik) {

        return {
            restrict: 'A',
            link: function (scope, element, attrs) {
                if (!attrs.idexperiment) {
                    return;
                }

                var idExperiment = attrs.idexperiment;

                element.on('click', function () {

                    function doStop() {
                        experimentModel.archiveExperiment(idExperiment).then(function (response) {
                            if (response.type === 'error') {
                                return;
                            }

                            var UI = require('piwik/UI');
                            var notification = new UI.Notification();
                            notification.show(_pk_translate('AbTesting_ActionArchiveExperimentSuccess'), {context: 'success'});
                            broadcast.propagateNewPage('popover=&idExperiment=' + idExperiment + '&segment=', undefined, 'category=General_Visitors&subcategory=General_Overview');
                        });
                    }

                    piwikHelper.modalConfirm('#confirmArchiveExperiment', {yes: doStop});
                });
            }
        };
    }
})();
