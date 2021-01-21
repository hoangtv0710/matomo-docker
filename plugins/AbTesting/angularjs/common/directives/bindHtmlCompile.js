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
 * Use this very wisely only with super trusted input re security.
 *
 * <span piwikab-bind-html-compile="<a ng-href='...'></a>"></span>
 */
(function () {
    angular.module('piwikApp.directive').directive('piwikabBindHtmlCompile', piwikabBindHtmlCompile);

    piwikabBindHtmlCompile.$inject = ['$compile'];

    function piwikabBindHtmlCompile($compile) {

        return {
            restrict: 'A',
            link: function (scope, element, attrs) {
                scope.$watch(function () {
                    return scope.$eval(attrs.piwikabBindHtmlCompile);
                }, function (value) {
                    element.html(value);
                    $compile(element.contents())(scope);
                });
            }
        };
    }
})();
