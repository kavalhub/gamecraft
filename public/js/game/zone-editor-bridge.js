/**
 * Cross-window sync for zone editor ↔ sprite picker (same origin, any tab/window).
 */
(function () {
    'use strict';

    var CHANNEL_NAME = 'craft-mir-zone-editor';
    var STORAGE_KEY = 'zoneEditorBridge';

    var channel = null;
    try {
        if (typeof BroadcastChannel !== 'undefined') {
            channel = new BroadcastChannel(CHANNEL_NAME);
        }
    } catch (e) {
        channel = null;
    }

    function parseMessage(raw) {
        if (!raw) return null;
        if (typeof raw === 'object' && raw.type) return raw;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    window.ZoneEditorBridge = {
        publish: function (message) {
            if (!message || !message.type) return;
            var payload = Object.assign({ at: Date.now() }, message);

            if (channel) {
                try {
                    channel.postMessage(payload);
                } catch (e) { /* ignore */ }
            }

            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
            } catch (e) { /* ignore */ }
        },

        subscribe: function (handler) {
            if (typeof handler !== 'function') return function () {};

            var onMessage = function (raw) {
                var msg = parseMessage(raw);
                if (msg) handler(msg);
            };

            if (channel) {
                channel.onmessage = function (e) { onMessage(e.data); };
            }

            window.addEventListener('storage', function (e) {
                if (e.key === STORAGE_KEY && e.newValue) {
                    onMessage(e.newValue);
                }
            });

            window.addEventListener('message', function (e) {
                if (e.origin !== window.location.origin) return;
                var data = e.data;
                if (data && data.channel === CHANNEL_NAME) {
                    onMessage(data.payload);
                }
            });

            return function () {
                if (channel) channel.onmessage = null;
            };
        },
    };
})();
