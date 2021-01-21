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

var abTestControlInitialized = false;

function initAbTest() {
    var topControls = '.top_controls #abtestPeriod';
    var dateSelector = '#periodString';

    $(dateSelector).hide();
    $(topControls).remove();
    $('#abtestPeriod').insertAfter('#periodString');

    if (typeof initTopControls !== 'undefined' && initTopControls) {
        initTopControls();
    }

    if (!abTestControlInitialized) {
        abTestControlInitialized = true;
        var $rootScope = piwikHelper.getAngularDependency('$rootScope');
        $rootScope.$on('piwikPageChange', function () {
            var href = location.href;

            var subcategory = broadcast.getValueFromHash('subcategory', href);

            var clickIsNotOnAbTest = !href
                || (href.indexOf('&category=AbTesting_Experiments&subcategory=') == -1)
                || (subcategory && !/^\d+$/.test(String(subcategory)));

            if (clickIsNotOnAbTest) {
                $(dateSelector).show();
                $(topControls).remove();

                if (typeof initTopControls !== 'undefined' && initTopControls) {
                    initTopControls();
                }
            }
        });
    }
}
