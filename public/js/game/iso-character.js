/**
 * Procedural isometric character with walk cycle (no external sprites required).
 */
(function () {
    'use strict';

    var AVATAR_COLORS = {
        warrior: { body: '#c0392b', trim: '#922b21' },
        mage: { body: '#667eea', trim: '#434190' },
        ranger: { body: '#38a169', trim: '#276749' },
        rogue: { body: '#4a5568', trim: '#2d3748' },
        cleric: { body: '#ecc94b', trim: '#b7791f' },
        dwarf: { body: '#dd6b20', trim: '#9c4221' },
        elf: { body: '#48bb78', trim: '#2f855a' },
        knight: { body: '#718096', trim: '#4a5568' },
    };

    window.IsoCharacter = {
        colorsFor: function (avatarKey) {
            return AVATAR_COLORS[avatarKey] || AVATAR_COLORS.mage;
        },

        /** @param {CanvasRenderingContext2D} ctx */
        draw: function (ctx, sx, sy, opts) {
            opts = opts || {};
            var facing = opts.facing || 'south';
            var moving = !!opts.moving;
            var phase = opts.phase || 0;
            var colors = opts.colors || AVATAR_COLORS.mage;
            var isLocal = !!opts.isLocal;
            var label = opts.label || '';
            var name = opts.name || '';

            var bob = moving ? Math.sin(phase * 14) * 2.5 : 0;
            var legSwing = moving ? Math.sin(phase * 14) * 5 : 0;
            var baseY = sy - 2 + bob;
            var lean = this.leanForFacing(facing);

            ctx.save();

            if (isLocal) {
                ctx.shadowColor = 'rgba(102,126,234,0.75)';
                ctx.shadowBlur = 12;
            }

            ctx.fillStyle = 'rgba(0,0,0,0.28)';
            ctx.beginPath();
            ctx.ellipse(sx, sy + 2, 14, 6, 0, 0, Math.PI * 2);
            ctx.fill();

            ctx.shadowBlur = 0;
            this.drawLegs(ctx, sx, baseY, lean, legSwing, colors.trim);
            this.drawTorso(ctx, sx + lean.x * 0.3, baseY - 14, lean, colors);
            this.drawHead(ctx, sx + lean.x * 0.4, baseY - 28, colors, label);

            if (name) {
                ctx.font = '11px Segoe UI, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillStyle = 'rgba(255,255,255,0.9)';
                ctx.fillText(name, sx, sy + 14);
            }

            ctx.restore();
        },

        leanForFacing: function (facing) {
            switch (facing) {
                case 'north': return { x: 4, y: -3 };
                case 'south': return { x: -4, y: 3 };
                case 'east': return { x: 5, y: 2 };
                case 'west': return { x: -5, y: -2 };
                default: return { x: 0, y: 0 };
            }
        },

        drawLegs: function (ctx, x, y, lean, swing, color) {
            ctx.strokeStyle = color;
            ctx.lineWidth = 3.5;
            ctx.lineCap = 'round';
            var lx = x + lean.x * 0.2;
            ctx.beginPath();
            ctx.moveTo(lx - 4, y - 4);
            ctx.lineTo(lx - 6 - swing, y + 2);
            ctx.moveTo(lx + 4, y - 4);
            ctx.lineTo(lx + 6 + swing, y + 2);
            ctx.stroke();
        },

        drawTorso: function (ctx, x, y, lean, colors) {
            ctx.fillStyle = colors.body;
            ctx.strokeStyle = colors.trim;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.roundRect(x - 9 + lean.x, y - 10 + lean.y, 18, 16, 5);
            ctx.fill();
            ctx.stroke();
            ctx.fillStyle = 'rgba(255,255,255,0.15)';
            ctx.beginPath();
            ctx.roundRect(x - 6 + lean.x, y - 6 + lean.y, 6, 8, 2);
            ctx.fill();
        },

        drawHead: function (ctx, x, y, colors, label) {
            ctx.fillStyle = '#f6e0c8';
            ctx.strokeStyle = colors.trim;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.arc(x, y, 8, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            ctx.fillStyle = colors.body;
            ctx.beginPath();
            ctx.arc(x, y - 2, 8, Math.PI, Math.PI * 2);
            ctx.fill();
            if (label) {
                ctx.font = '11px serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(label, x, y + 1);
            }
        },
    };
})();
