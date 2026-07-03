/**
 * Shared isometric tile math and Kenney-style sprite anchoring.
 * Kenney Isometric (256×512): bottom-center of sprite = bottom tip of cell diamond.
 */
(function () {
    'use strict';

    var TILE_W = 128;
    var TILE_H = 64;
    var ISO_HW = TILE_W / 2;
    var ISO_HH = TILE_H / 2;

    window.IsoTileRender = {
        TILE_W: TILE_W,
        TILE_H: TILE_H,
        ISO_HW: ISO_HW,
        ISO_HH: ISO_HH,

        rawScreen: function (x, z, y) {
            y = y || 0;
            return {
                x: (x - z) * ISO_HW,
                y: (x + z) * ISO_HH - y * ISO_HH,
            };
        },

        screenToWorld: function (sx, sy, camera, zoom) {
            zoom = zoom || 1;
            var rx = (sx - camera.x) / zoom;
            var ry = (sy - camera.y) / zoom;
            return {
                x: (rx / ISO_HW + ry / ISO_HH) / 2,
                z: (ry / ISO_HH - rx / ISO_HW) / 2,
            };
        },

        /** Which tile cell contains world point (diamond grid, centers at integers). */
        worldToCell: function (px, pz) {
            var u = px - pz;
            var v = px + pz;
            var cx = Math.round((v + u) / 2);
            var cz = Math.round((v - u) / 2);
            if (this.pointInCell(px, pz, cx, cz)) {
                return { x: cx, z: cz };
            }
            var neighbors = [
                [1, 0], [-1, 0], [0, 1], [0, -1],
                [1, 1], [-1, -1], [1, -1], [-1, 1],
            ];
            for (var i = 0; i < neighbors.length; i++) {
                var tx = cx + neighbors[i][0];
                var tz = cz + neighbors[i][1];
                if (this.pointInCell(px, pz, tx, tz)) {
                    return { x: tx, z: tz };
                }
            }
            return { x: cx, z: cz };
        },

        pointInCell: function (px, pz, cx, cz, margin) {
            margin = margin == null ? 1 : margin;
            var u = px - pz;
            var v = px + pz;
            var u0 = cx - cz;
            var v0 = cx + cz;
            return Math.abs(u - u0) <= margin && Math.abs(v - v0) <= margin;
        },

        /** Sample points around feet — catches visual overlap with neighbor tiles. */
        footprintSamples: function (px, pz, radius) {
            radius = radius == null ? 0.42 : radius;
            return [
                [px, pz],
                [px + radius, pz],
                [px - radius, pz],
                [px, pz + radius],
                [px, pz - radius],
                [px + radius * 0.7, pz + radius * 0.7],
                [px - radius * 0.7, pz + radius * 0.7],
                [px + radius * 0.7, pz - radius * 0.7],
                [px - radius * 0.7, pz - radius * 0.7],
            ];
        },

        /** Draw sprite anchored at bottom-center of the isometric cell. */
        drawSprite: function (ctx, img, centerX, centerY, scale) {
            scale = scale || 1;
            if (!img || !(img.naturalWidth || img.width)) return;
            var nw = img.naturalWidth || img.width;
            var nh = img.naturalHeight || img.height;
            var w = TILE_W * scale;
            var h = nh * (w / nw);
            var hh = ISO_HH * scale;
            ctx.drawImage(img, centerX - w / 2, centerY + hh - h, w, h);
        },

        drawDiamond: function (ctx, centerX, centerY, scale, fill, stroke) {
            scale = scale || 1;
            var hw = ISO_HW * scale;
            var hh = ISO_HH * scale;
            ctx.beginPath();
            ctx.moveTo(centerX, centerY - hh);
            ctx.lineTo(centerX + hw, centerY);
            ctx.lineTo(centerX, centerY + hh);
            ctx.lineTo(centerX - hw, centerY);
            ctx.closePath();
            ctx.fillStyle = fill;
            ctx.fill();
            if (stroke) {
                ctx.strokeStyle = stroke;
                ctx.lineWidth = Math.max(1, scale);
                ctx.stroke();
            }
        },
    };
})();
