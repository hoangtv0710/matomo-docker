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
(function () {
    angular.module('piwikApp.filter').filter('readableExperimentStatus', readableExperimentStatus);

    function readableExperimentStatus() {

        return function(status, statusOptions) {
            if (!statusOptions) {
                return status;
            }

            for (var i = 0; i < statusOptions.length; i++) {
                if (status === statusOptions[i].value) {
                    return statusOptions[i].name;
                }
            }

            return status;
        };
    }
})();
