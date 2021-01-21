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

(function ($) {
    function removeLogoPiwikTitle(){
        $('#logo').find('[title]').attr('title', '');
        $('.loginSection #piwik').remove();
        $('.loginSection #matomo').remove();
    }

    function removePiwikBrowserTitle()
    {
        var title = $('title').text();
        if (title) {
            title = (''+ title);
            title = $.trim(title);
            var index = title.lastIndexOf('Piwik');

            if (index === (title.length - 5)) {
                $('title').text(title.substring(0, index));
            }

            index = title.lastIndexOf('Matomo');

            if (index === (title.length - 6)) {
                $('title').text(title.substring(0, index));
            }
        }
    }

    $(document).ready(function() {
        removeLogoPiwikTitle();
        removePiwikBrowserTitle();

        if ('object' === typeof piwik && 'whiteLabelRemoveLinks' in piwik && piwik.whiteLabelRemoveLinks) {
            $('body').addClass('whiteLabelRemoveLinks');
        }
    });

    $(window).on('load', function () {
        removeLogoPiwikTitle();
    });

}(jQuery));