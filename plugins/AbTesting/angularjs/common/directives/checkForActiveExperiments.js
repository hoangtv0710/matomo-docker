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
 * Only works with admin access
 * <div piwik-check-for-active-experiments></div>
 */
(function () {
    angular.module('piwikApp.directive').directive('piwikCheckForActiveExperiments', piwikCheckForActiveExperiments);

    piwikCheckForActiveExperiments.$inject = ['piwikApi', 'piwik'];

    function piwikCheckForActiveExperiments(piwikApi, piwik) {

        return {
            restrict: 'A',
            link: function (scope, element, attrs) {

                function isGettingStartedPage()
                {
                    var url = location.href;
                    if (url.indexOf('category=AbTesting_Experiments&subcategory=AbTesting_GettingStarted') !== -1) {
                        return true;
                    }
                    return false;
                }

                function checkForExperiment()
                {
                    if (!isGettingStartedPage()) {
                        return;
                    }

                    piwikApi.fetch({method: 'AbTesting.getActiveExperiments'}).then(function (experiments) {
                        if (!isGettingStartedPage()) {
                            return;
                        }

                        if (experiments && experiments.length && experiments[0] && experiments[0].idexperiment) {
                            piwik.broadcast.propagateNewPage('idSite=' + piwik.idSite, undefined, 'category=AbTesting_Experiments&subcategory=' + experiments[0].idexperiment);
                        }
                    }, function () {
                        // we ignore errors
                    });
                }

                var msInSecond = 1000;

                setTimeout(checkForExperiment, msInSecond);
                setTimeout(checkForExperiment, 10 * msInSecond);
                setTimeout(checkForExperiment, 60 * msInSecond);
                setTimeout(checkForExperiment, 300 * msInSecond);
                setTimeout(checkForExperiment, 600 * msInSecond);
                setTimeout(checkForExperiment, 3000 * msInSecond);
                setTimeout(checkForExperiment, 6000 * msInSecond);
            }
        };
    }
})();
