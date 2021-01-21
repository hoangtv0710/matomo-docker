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

    /**
     * UI control that handles extra functionality for Actions datatables.
     *
     * @constructor
     */
    exports.AbTestDataTable = function (element) {
        this.parentAttributeParent = '';
        this.parentId = '';

        DataTable.call(this, element);
    };

    $.extend(exports.AbTestDataTable.prototype, dataTablePrototype, {

        handleSummaryRow: function (domElem) {

            function getMetadata($elem) {
                var metadata = $elem.attr('data-row-metadata');

                if (!metadata) {
                    return;
                }

                try {
                    metadata = JSON.parse(metadata);
                } catch (e) {
                    metadata = null;
                }

                return metadata;
            }

            var hasWinner = false;
            var hasSignificant = false;
            var hasLoser = false;

            // we override this method as we summary row won't be needed for this report. If we added new method
            // we would need to copy lots from dataTable.js
            $('tr[data-row-metadata]', domElem).each(function (index, elem) {
                var $elem = $(elem);
                var metadata = getMetadata($elem);

                if (metadata && metadata.is_winner) {
                    $elem.addClass('isWinner');
                    hasWinner = true;
                } else if (metadata && metadata.is_significant) {
                    $elem.addClass('isSignificant');
                    hasSignificant = true;
                } else if (metadata && metadata.is_loser) {
                    $elem.addClass('isLoser');
                    hasLoser = true;
                }
            });

            var $footerMessage = $('.datatableFooterMessage', domElem);

            if ($footerMessage.size() && $footerMessage.text()) {
                if (hasWinner) {
                    $footerMessage.addClass('alert alert-success');
                } else if (hasSignificant) {
                    $footerMessage.addClass('alert alert-warning');
                } else if (hasLoser) {
                    $footerMessage.addClass('alert alert-danger');
                }
            }
        },

    });

})(jQuery, require);
