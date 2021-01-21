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

(function ($, require) {

    var exports = require('piwik/UI'),
        DataTable = exports.ActionsDataTable,
        dataTablePrototype = DataTable.prototype;

    /**
     * UI control that handles extra functionality for Attribution datatables.
     *
     * @constructor
     */
    exports.AttributionDataTable = function (element) {
        DataTable.call(this, element);
    };

    $.extend(exports.AttributionDataTable.prototype, dataTablePrototype, {

        postBindEventsAndApplyStyleHook: function (domElem) {
            var rows = domElem.find('table.dataTable:first tr');
            var numColumns = 0;

            function convertTexToSpan(replaceTextNode) {
                var spanElement = document.createElement('span');
                spanElement.setAttribute('class', 'actualLabelContent');
                var newTextNode = document.createTextNode(replaceTextNode.textContent);
                spanElement.appendChild(newTextNode);
                if (replaceTextNode.parentNode) {
                    replaceTextNode.parentNode.replaceChild(spanElement, replaceTextNode);
                }
            }

            rows.each(function (i, row) {
                $(row).find('td,th').each(function (index, column) {
                    if (index === 0) {
                        return; // never do it for label, not really needed but still adding it
                    }

                    if (index % 2 == 1) {
                        $(column).addClass('attributionOdd');
                    }
                });
            });

            for (var i = 3; i <= 7; i++) {
                var selectors = ['.column-suffix'] // '.actualLabelContent'
                for (var j = 0; j < selectors.length; j++) {
                    var width = 0;
                    var $columns = domElem.find('td:nth-child(' + i + ') ' + selectors[j]);
                    $columns.each(function (index, label) {
                        var lableWidth = $(label).width();
                        if (lableWidth > width) {
                            width = lableWidth;
                        }
                    });
                    if (width) {
                        $columns.css({width: width + 'px', display: 'inline-block'});
                    }
                }

            }

        }
    });

})(jQuery, require);
