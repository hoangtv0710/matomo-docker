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
 * <div piwik-experiment-edit-schedule>
 */
(function () {
    angular.module('piwikApp').directive('piwikExperimentEditSchedule', piwikExperimentEditSchedule);

    piwikExperimentEditSchedule.$inject = ['piwik', '$timeout'];

    function piwikExperimentEditSchedule(piwik, $timeout){

        return {
            restrict: 'A',
            templateUrl: 'plugins/AbTesting/angularjs/manage/edit/schedule.directive.html?cb=' + piwik.cacheBuster,
            link: function (scope) {

                scope.editExperiment.onAnyDateChange = function () {
                    var experiment = scope.editExperiment.experiment;
                    if (experiment.start_date_date) {
                        if (!experiment.start_date_time) {
                            experiment.start_date_time = '00:00:00';
                        }

                        experiment.start_date = experiment.start_date_date + ' ' + experiment.start_date_time;

                    } else {
                        experiment.start_date = null;
                    }

                    if (experiment.end_date_date) {
                        if (!experiment.end_date_time) {
                            experiment.end_date_time = '23:59:59';
                        }

                        experiment.end_date = experiment.end_date_date + ' ' + experiment.end_date_time;
                    } else {
                        experiment.end_date = null;
                    }
                };

                var options1 = piwik.getBaseDatePickerOptions(null);
                delete options1.maxDate;
                options1.minDate = new Date();
                var options2 = angular.copy(options1);

                $timeout(function () {
                    $( ".experimentStartDateInput" ).datepicker(options1);
                    $( ".experimentEndDateInput" ).datepicker(options2);
                    $('.experimentStartTimeInput').timepicker({timeFormat: 'H:i:s'});
                    $('.experimentEndTimeInput').timepicker({timeFormat: 'H:i:s'});
                });
            }
        };
    }
})();