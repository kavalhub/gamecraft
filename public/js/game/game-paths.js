/**
 * Base path helpers for /gamecraft subpath deployment.
 */
(function () {
    'use strict';

    window.GAME_BASE = window.GAME_BASE || '';

    window.gameUrl = function (path) {
        var base = window.GAME_BASE || '';
        if (!path) return base || '/';
        if (path.indexOf('http://') === 0 || path.indexOf('https://') === 0) {
            return path;
        }
        return base + (path.charAt(0) === '/' ? path : '/' + path);
    };
})();
