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
        DataTable = exports.DataTable,
        dataTablePrototype = DataTable.prototype;

    exports.CohortsDataTable = function (element) {
        this.parentId = '';
        DataTable.call(this, element);
    };

    $.extend(exports.CohortsDataTable.prototype, dataTablePrototype, {
        postBindEventsAndApplyStyleHook: function (domElem) {
            this._addColumnsPicker(domElem);
        },

        _addColumnsPicker: function (domElem) {
            var self = this;

            var $select = $('<select>').attr('class', 'cohorts-metric-picker');

            this.props.selectable_metrics.forEach(function (metric) {
                var $option = $('<option>').attr('value', metric.column);
                $option.text(metric.translation);
                if (metric.column === self.param.metric) {
                    $option.attr('selected', 'selected');
                }
                $select.append($option);
            });

            domElem.append($select);

            $select.on('change', function () {
                var selectedColumn = $select.val();

                self.param['metric'] = selectedColumn;
                self.reloadAjaxDataTable();

                // inform dashboard widget about changed parameters (to be restored on reload)
                var UI = require('piwik/UI');
                var params = {metric: selectedColumn};

                var tableNode = $('#' + self.workingDivId);
                UI.DataTable.prototype.notifyWidgetParametersChange(tableNode, params);
            });

            $select.material_select();
        },
    });

})(jQuery, require);
