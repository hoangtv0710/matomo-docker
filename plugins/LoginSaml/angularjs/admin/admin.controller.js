/*!
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link    https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
angular.module('piwikApp').controller('LoginSamlAdminController', function ($scope, $attrs, piwikApi) {
    $scope.getSampleViewAttribute = function (config) {
        return getSampleAccessAttribute(config, config.saml_view_access_field, '1,2', '3,4');
    };

    $scope.getSampleAdminAttribute = function (config) {
        return getSampleAccessAttribute(config, config.saml_admin_access_field, 'all', 'all');
    };

    $scope.getSampleSuperuserAttribute = function (config) {
        return getSampleAccessAttribute(config, config.saml_superuser_access_field);
    };

    function getSampleAccessAttribute(config, accessField, firstValue, secondValue) {
        var result = accessField + ': ';

        if (config.instance_name) {
            result += config.instance_name;
        } else {
            result += window.location.hostname;
        }
        if (firstValue) {
            result += config.user_access_attribute_server_separator + firstValue;
        }

        result += config.user_access_attribute_server_specification_delimiter;

        if (config.instance_name) {
            result += 'piwikB';
        } else {
            result += 'anotherhost.com';
        }
        if (secondValue) {
            result += config.user_access_attribute_server_separator + secondValue;
        }

        return result;
    }                            
});