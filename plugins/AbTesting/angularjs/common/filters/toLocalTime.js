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
    angular.module('piwikApp.filter').filter('toLocalTime', toLocalTime);

    function toLocalTime() {

        // expected iso date in utc eg '2014-10-29 00:00:00' or '2014/10/29 00:00:00'
        return function(dateTime, format) {
            if (!dateTime) {
                return;
            }

            if ('undefined' === typeof format) {
                format = false;
            }

            var isoDate = dateTime;

            if (isoDate) {
                isoDate = (isoDate + '').replace(/-/g, '/');

                try {
                    var result = new Date(isoDate + ' UTC');

                    if (format) {
                        return result.toLocaleString();
                    }

                    return result;
                } catch (e) {
                    try {
                        var result = Date.parse(isoDate + ' UTC');
                        result = new Date(result);

                        if (format) {
                            return result.toLocaleString();
                        }

                        return result;
                    } catch (ex) {

                        // eg phantomjs etc
                        var datePart = isoDate.substr(0, 10);
                        var timePart = isoDate.substr(11);

                        var dateParts = datePart.split('/');
                        var timeParts = timePart.split(':');
                        if (dateParts.length === 3 && timeParts.length === 3) {
                            var result = new Date(dateParts[0], dateParts[1] - 1, dateParts[2], timeParts[0], timeParts[1], timeParts[2]);
                            var newTime = result.getTime() + (result.getTimezoneOffset() * 60000);
                            result = new Date(newTime);

                            if (format) {
                                return result.toLocaleString();
                            }

                            return result;
                        }


                    }
                }
            }

            if (format) {
                return '';
            }
        };
    }
})();
