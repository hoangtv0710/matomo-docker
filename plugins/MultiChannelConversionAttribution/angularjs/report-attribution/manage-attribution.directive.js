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
 * <div piwik-report-multiattribution>
 */
(function () {
    angular.module('piwikApp').directive('piwikReportMultiattribution', piwikReportMultiattribution);

    piwikReportMultiattribution.$inject = [];

    function piwikReportMultiattribution(){

        return {
            restrict: 'A',
            compile: function (element, attrs) {

                return function (scope, element, attrs, controller) {

                    controller.onReportChange = function () {
                        var dataTable = element.find('.attributionReport .dataTable:first').data('uiControlObject');
                        if (dataTable && dataTable.param) {
                            dataTable.param.idGoal = this.idGoal;
                            dataTable.param.numDaysPriorToConversion = this.daysPriorToConversion;
                            dataTable.param.attributionModels = this.model1 + ',' + this.model2 + ',' + this.model3;
                            dataTable.reloadAjaxDataTable();
                        }
                    };
                };
            },
            controllerAs: 'reportMultiAttrCtrl',
            controller: function () {

            }
        };
    }
})();