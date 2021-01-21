/* A polyfill for browsers that don't support ligatures. */
/* The script tag referring to this file must be placed before the ending body tag. */

/* To provide support for elements dynamically added, this script adds
   method 'icomoonLiga' to the window object. You can pass element references to this method.
*/
(function () {
    'use strict';
    function supportsProperty(p) {
        var prefixes = ['Webkit', 'Moz', 'O', 'ms'],
            i,
            div = document.createElement('div'),
            ret = p in div.style;
        if (!ret) {
            p = p.charAt(0).toUpperCase() + p.substr(1);
            for (i = 0; i < prefixes.length; i += 1) {
                ret = prefixes[i] + p in div.style;
                if (ret) {
                    break;
                }
            }
        }
        return ret;
    }
    var icons;
    if (!supportsProperty('fontFeatureSettings')) {
        icons = {
            'history': '&#xe94d;',
            'time': '&#xe94d;',
            'clock': '&#xe94e;',
            'time2': '&#xe94e;',
            'clock2': '&#xe94f;',
            'time3': '&#xe94f;',
            'alarm': '&#xe950;',
            'time4': '&#xe950;',
            'stopwatch': '&#xe952;',
            'time5': '&#xe952;',
            'calendar': '&#xe953;',
            'date': '&#xe953;',
            'drawer': '&#xe95c;',
            'box': '&#xe95c;',
            'drawer2': '&#xe95d;',
            'box2': '&#xe95d;',
            'box-add': '&#xe95e;',
            'box3': '&#xe95e;',
            'box-remove': '&#xe95f;',
            'box4': '&#xe95f;',
            'lab': '&#xe9aa;',
            'beta': '&#xe9aa;',
            'play2': '&#xea15;',
            'player': '&#xea15;',
            'pause': '&#xea16;',
            'player2': '&#xea16;',
            'stop': '&#xea17;',
            'player3': '&#xea17;',
            'play3': '&#xea1c;',
            'player8': '&#xea1c;',
            'pause2': '&#xea1d;',
            'player9': '&#xea1d;',
            'stop2': '&#xea1e;',
            'player10': '&#xea1e;',
            'table': '&#xea70;',
            'wysiwyg18': '&#xea70;',
            'table2': '&#xea71;',
            'wysiwyg19': '&#xea71;',
          '0': 0
        };
        delete icons['0'];
        window.icomoonLiga = function (els) {
            var classes,
                el,
                i,
                innerHTML,
                key;
            els = els || document.getElementsByTagName('*');
            if (!els.length) {
                els = [els];
            }
            for (i = 0; ; i += 1) {
                el = els[i];
                if (!el) {
                    break;
                }
                classes = el.className;
                if (/abtestingicon-/.test(classes)) {
                    innerHTML = el.innerHTML;
                    if (innerHTML && innerHTML.length > 1) {
                        for (key in icons) {
                            if (icons.hasOwnProperty(key)) {
                                innerHTML = innerHTML.replace(new RegExp(key, 'g'), icons[key]);
                            }
                        }
                        el.innerHTML = innerHTML;
                    }
                }
            }
        };
        window.icomoonLiga();
    }
}());
