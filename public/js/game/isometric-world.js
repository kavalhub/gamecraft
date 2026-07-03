/**
 * Isometric world view — canvas overlay on game background.
 * Uses World API via WorldPanel / GameApi.
 */
(function () {
    'use strict';

    var ISO = window.IsoTileRender;
    if (!ISO) {
        console.error('[WorldView] iso-tile-render.js not loaded');
        ISO = {
            TILE_W: 128, TILE_H: 64, ISO_HW: 64, ISO_HH: 32,
            rawScreen: function (x, z, y) {
                y = y || 0;
                return { x: (x - z) * 64, y: (x + z) * 32 - y * 32 };
            },
            drawSprite: function (ctx, img, cx, cy, scale) {
                scale = scale || 1;
                var w = 128 * scale;
                var h = (img.naturalHeight || img.height) * (w / (img.naturalWidth || img.width));
                ctx.drawImage(img, cx - w / 2, cy + 32 * scale - h, w, h);
            },
            drawDiamond: function (ctx, cx, cy, scale, fill, stroke) {
                scale = scale || 1;
                var hw = 64 * scale;
                var hh = 32 * scale;
                ctx.beginPath();
                ctx.moveTo(cx, cy - hh);
                ctx.lineTo(cx + hw, cy);
                ctx.lineTo(cx, cy + hh);
                ctx.lineTo(cx - hw, cy);
                ctx.closePath();
                ctx.fillStyle = fill;
                ctx.fill();
                if (stroke) { ctx.strokeStyle = stroke; ctx.lineWidth = 1; ctx.stroke(); }
            },
        };
    }
    var TILE_W = ISO.TILE_W;
    var TILE_H = ISO.TILE_H;
    var ISO_HW = ISO.ISO_HW;
    var ISO_HH = ISO.ISO_HH;
    // Свои текстуры: положите grass_01.png … в /assets/world/ и включите true
    var USE_TEXTURED_GROUND = false;
    var WORLD_STEP_SIZE = 0.75;
    // Физические клавиши (ev.code) — работает при любой раскладке.
    // На изометрии «вверх» на экране = south (−Z), «вниз» = north (+Z).
    var MOVE_CODES = {
        ArrowUp: 'south',
        ArrowDown: 'north',
        KeyW: 'south',
        KeyS: 'north',
        ArrowLeft: 'west',
        ArrowRight: 'east',
        KeyA: 'west',
        KeyD: 'east',
    };

    window.WorldView = {
        canvas: null,
        ctx: null,
        zone: null,
        zoneSlug: null,
        state: null,
        nearbyPlayers: [],
        camera: { x: 0, y: 0 },
        display: { x: 0, y: 0, z: 0 },
        rafId: null,
        keyInterval: null,
        hintEl: null,
        grassTiles: [],
        grassTilesReady: false,
        tileCells: {},
        tileImages: {},
        worldStepSize: WORLD_STEP_SIZE,
        stepDeltas: { north: [0, 1], south: [0, -1], east: [1, 0], west: [-1, 0] },
        activeMoveDir: null,
        facing: 'south',
        moveAnim: null,
        animPhase: 0,
        avatarKey: 'mage',
        mouseRunActive: false,
        mouseRunInterval: null,
        mouseWorldTarget: null,

        init: function () {
            var bg = document.querySelector('.game-background');
            if (!bg || bg.dataset.worldViewBound) return;
            bg.dataset.worldViewBound = '1';

            this.canvas = document.createElement('canvas');
            this.canvas.id = 'worldIsoCanvas';
            this.canvas.className = 'world-iso-canvas';
            bg.appendChild(this.canvas);
            this.ctx = this.canvas.getContext('2d');

            this.hintEl = document.createElement('div');
            this.hintEl.className = 'world-iso-hint';
            this.hintEl.textContent = 'WASD или зажатая ЛКМ — бег · E / клик по NPC — взаимодействие';
            bg.appendChild(this.hintEl);

            this.resize();
            window.addEventListener('resize', this.resize.bind(this));

            this.canvas.addEventListener('mousedown', this.onMouseDown.bind(this));
            this.canvas.addEventListener('contextmenu', function (e) { e.preventDefault(); });
            document.addEventListener('mousemove', this.onMouseMove.bind(this));
            document.addEventListener('mouseup', this.onMouseUp.bind(this));
            document.addEventListener('keydown', this.onKeyDown.bind(this));
            document.addEventListener('keyup', this.onKeyUp.bind(this));

            this.loadGrassTiles();
            this.fetchWorldMeta();
            this.loop();
            if (window.MinimapView) MinimapView.init();
        },

        resize: function () {
            if (!this.canvas) return;
            var parent = this.canvas.parentElement;
            this.canvas.width = parent.clientWidth;
            this.canvas.height = parent.clientHeight;
            this.render();
        },

        loadGrassTiles: function () {
            if (!USE_TEXTURED_GROUND) return;
            var self = this;
            var files = ['grass_01.png', 'grass_02.png', 'grass_03.png'];
            var pending = files.length;

            files.forEach(function (file, index) {
                var img = new Image();
                img.onload = function () {
                    self.grassTiles[index] = img;
                    pending -= 1;
                    if (pending === 0) {
                        self.grassTilesReady = true;
                        self.render();
                    }
                };
                img.onerror = function () {
                    console.warn('[WorldView] grass tile failed:', file);
                    pending -= 1;
                };
                img.src = '/assets/world/' + file;
            });
        },

        fetchWorldMeta: function () {
            if (!window.GameApi) return;
            GameApi.fetch('/api/game/meta')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.world && data.world.step_size) {
                        WorldView.worldStepSize = data.world.step_size;
                    }
                })
                .catch(function () {});
        },

        onStepResult: function (state, portalUsed) {
            if (state) {
                this.state = state;
                if (this.moveAnim) {
                    this.moveAnim.toX = state.x;
                    this.moveAnim.toZ = state.z;
                } else {
                    this.display.x = state.x;
                    this.display.y = state.y;
                    this.display.z = state.z;
                }
            }
            if (portalUsed && portalUsed.to_zone && portalUsed.to_zone !== this.zoneSlug) {
                this.loadZone(portalUsed.to_zone);
            }
        },

        syncDisplayToState: function () {
            if (!this.state) return;
            this.moveAnim = null;
            this.display.x = this.state.x;
            this.display.y = this.state.y;
            this.display.z = this.state.z;
        },

        updateMovementAnim: function () {
            this.animPhase = performance.now() / 1000;
            if (!this.moveAnim) return;
            var a = this.moveAnim;
            var t = (performance.now() - a.start) / a.dur;
            if (t >= 1) {
                this.display.x = a.toX;
                this.display.z = a.toZ;
                this.moveAnim = null;
                return;
            }
            t = t * (2 - t);
            this.display.x = a.fromX + (a.toX - a.fromX) * t;
            this.display.z = a.fromZ + (a.toZ - a.fromZ) * t;
        },

        isMoving: function () {
            return !!this.activeMoveDir || !!this.moveAnim || this.mouseRunActive
                || (window.WorldPanel && WorldPanel._stepQueue && WorldPanel._stepQueue.length > 0);
        },

        isWalkableAt: function (x, z) {
            var self = this;
            var samples = ISO.footprintSamples(x, z);
            for (var i = 0; i < samples.length; i++) {
                var sx = samples[i][0];
                var sz = samples[i][1];
                var cell = ISO.worldToCell(sx, sz);
                var data = this.tileCells[this.cellKey(cell.x, cell.z)];
                if (data && data.walkable === false) {
                    return false;
                }
            }
            return true;
        },

        canStep: function (dir) {
            var d = this.stepDeltas[dir];
            if (!d || !this.state) return false;
            var s = this.worldStepSize;
            var nx = this.state.x + d[0] * s;
            var nz = this.state.z + d[1] * s;
            return this.isWalkableAt(nx, nz);
        },

        predictStep: function (dir) {
            var d = this.stepDeltas[dir];
            if (!d || !this.state) return false;
            if (!this.canStep(dir)) return false;
            var s = this.worldStepSize;
            var toX = this.state.x + d[0] * s;
            var toZ = this.state.z + d[1] * s;
            this.facing = dir;
            this.moveAnim = {
                fromX: this.display.x,
                fromZ: this.display.z,
                toX: toX,
                toZ: toZ,
                start: performance.now(),
                dur: 95,
            };
            this.state.x = toX;
            this.state.z = toZ;
            return true;
        },

        revertStep: function (dir) {
            var d = this.stepDeltas[dir];
            if (!d || !this.state) return;
            var s = this.worldStepSize;
            this.state.x -= d[0] * s;
            this.state.z -= d[1] * s;
            this.moveAnim = null;
            this.syncDisplayToState();
        },

        tileHalfExtents: function () {
            return { hw: ISO_HW, hh: ISO_HH };
        },

        getVisibleTileRange: function (zoneBounds) {
            var canvas = this.canvas;
            var margin = 6;
            var minX = Infinity;
            var maxX = -Infinity;
            var minZ = Infinity;
            var maxZ = -Infinity;
            var corners = [
                [0, 0],
                [canvas.width, 0],
                [0, canvas.height],
                [canvas.width, canvas.height],
            ];

            for (var i = 0; i < corners.length; i++) {
                var w = this.screenToWorld(corners[i][0], corners[i][1]);
                minX = Math.min(minX, w.x);
                maxX = Math.max(maxX, w.x);
                minZ = Math.min(minZ, w.z);
                maxZ = Math.max(maxZ, w.z);
            }

            return {
                minX: Math.max(zoneBounds.min_x, Math.floor(minX) - margin),
                maxX: Math.min(zoneBounds.max_x, Math.ceil(maxX) + margin),
                minZ: Math.max(zoneBounds.min_z, Math.floor(minZ) - margin),
                maxZ: Math.min(zoneBounds.max_z, Math.ceil(maxZ) + margin),
            };
        },

        grassVariantIndex: function (x, z) {
            return (Math.floor(x) + Math.floor(z)) % Math.max(this.grassTiles.length, 1);
        },

        onContext: function (data) {
            if (!data || !data.state) return;
            var slug = data.state.zone_slug;
            if (slug && slug !== this.zoneSlug) {
                this.loadZone(slug);
            }
            this.state = data.state;
            this.nearbyPlayers = data.nearby_players || [];
            if (!this._displayInit) {
                this.syncDisplayToState();
                this._displayInit = true;
            }
        },

        loadZone: function (slug) {
            var self = this;
            if (!window.GameApi) return;
            this.zoneSlug = slug;
            this.tileCells = {};
            GameApi.fetch('/api/world/zones/' + encodeURIComponent(slug))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    self.zone = data.zone || null;
                    self.render();
                })
                .catch(function (e) { console.warn('WorldView zone load:', e); });
            GameApi.fetch('/api/world/zones/' + encodeURIComponent(slug) + '/tiles')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    self.tileCells = (data.tiles && data.tiles.cells) || {};
                    self.preloadTileImages();
                    self.render();
                })
                .catch(function (e) { console.warn('WorldView tiles load:', e); });
        },

        cellKey: function (x, z) {
            return Math.floor(x) + ',' + Math.floor(z);
        },

        getTileCell: function (wx, wz) {
            return this.tileCells[this.cellKey(wx, wz)] || null;
        },

        tileGround: function (cell) {
            if (!cell) return null;
            return cell.ground || cell.sprite || null;
        },

        tileOverlay: function (cell) {
            if (!cell) return null;
            return cell.overlay || null;
        },

        preloadTileImages: function () {
            var self = this;
            Object.keys(this.tileCells).forEach(function (key) {
                var cell = self.tileCells[key];
                [self.tileGround(cell), self.tileOverlay(cell)].forEach(function (sp) {
                    if (sp) self.loadTileImage(sp);
                });
            });
        },

        loadTileImage: function (path) {
            if (this.tileImages[path]) return this.tileImages[path];
            var img = new Image();
            img.onload = function () { if (window.WorldView) WorldView.render(); };
            img.src = '/assets/' + String(path).replace(/^\//, '');
            this.tileImages[path] = img;
            return img;
        },

        rawScreen: function (x, z, y) {
            return ISO.rawScreen(x, z, y);
        },

        toScreen: function (x, z, y) {
            var p = this.rawScreen(x, z, y);
            return { x: p.x + this.camera.x, y: p.y + this.camera.y };
        },

        screenToWorld: function (sx, sy) {
            var rx = sx - this.camera.x;
            var ry = sy - this.camera.y;
            var x = (rx / (TILE_W / 2) + ry / (TILE_H / 2)) / 2;
            var z = (ry / (TILE_H / 2) - rx / (TILE_W / 2)) / 2;
            return { x: x, z: z };
        },

        updateCamera: function () {
            if (!this.canvas || !this.state) return;
            var p = this.rawScreen(this.display.x, this.display.z);
            this.camera.x = this.canvas.width * 0.5 - p.x;
            this.camera.y = this.canvas.height * 0.55 - p.y;
        },

        loop: function () {
            var self = this;
            self.updateMovementAnim();
            self.updateCamera();
            self.render();
            if (window.MinimapView) MinimapView.tick();
            self.rafId = requestAnimationFrame(function () { self.loop(); });
        },

        render: function () {
            var ctx = this.ctx;
            var canvas = this.canvas;
            if (!ctx || !canvas) return;

            ctx.clearRect(0, 0, canvas.width, canvas.height);

            var bounds = (this.zone && this.zone.bounds) || { min_x: -40, max_x: 40, min_z: -40, max_z: 40 };
            this.drawGround(bounds);

            var entities = [];

            if (this.zone && this.zone.portals) {
                this.zone.portals.forEach(function (p) {
                    entities.push({ kind: 'portal', x: p.x, z: p.z, label: '🌀', name: p.id, id: p.id, sort: p.x + p.z });
                });
            }
            if (this.zone && this.zone.interactables) {
                this.zone.interactables.forEach(function (it) {
                    var icon = '📦';
                    if (it.kind === 'npc') icon = '🧔';
                    else if (it.kind === 'station') icon = '🔨';
                    else if (it.kind === 'encounter') icon = '⚔️';
                    else if (it.kind === 'object') icon = '📬';
                    entities.push({
                        kind: 'interactable',
                        x: it.x,
                        z: it.z,
                        label: icon,
                        name: it.name || it.id,
                        id: it.id,
                        sort: it.x + it.z,
                    });
                });
            }

            var self = this;
            (this.nearbyPlayers || []).forEach(function (p) {
                if (self.state && p.character_uuid === window.GameState.characterUuid) return;
                entities.push({
                    kind: 'player',
                    x: p.x,
                    z: p.z,
                    label: p.avatar_icon || '👤',
                    name: p.character_name || 'Игрок',
                    sort: p.x + p.z,
                });
            });

            if (this.state) {
                entities.push({
                    kind: 'local',
                    x: this.display.x,
                    z: this.display.z,
                    label: this.getLocalAvatar(),
                    name: 'Вы',
                    sort: this.display.x + this.display.z + 0.01,
                });
            }

            entities.sort(function (a, b) { return a.sort - b.sort; });
            entities.forEach(function (e) { self.drawEntity(e); });
        },

        getLocalAvatar: function () {
            var el = document.getElementById('unitFramePortrait');
            return (el && el.textContent) ? el.textContent.trim() : '🧙';
        },

        drawGround: function (bounds) {
            var range = this.getVisibleTileRange(bounds);
            var tiles = [];
            for (var x = range.minX; x <= range.maxX; x++) {
                for (var z = range.minZ; z <= range.maxZ; z++) {
                    tiles.push({ x: x, z: z, sort: x + z });
                }
            }
            tiles.sort(function (a, b) { return a.sort - b.sort; });
            for (var i = 0; i < tiles.length; i++) {
                this.drawGroundTile(tiles[i].x, tiles[i].z);
            }

            if (this.zone && this.zone.name) {
                var ctx = this.ctx;
                var titlePos = this.toScreen(bounds.min_x + 4, bounds.min_z + 4);
                ctx.save();
                ctx.font = '600 14px Segoe UI, sans-serif';
                ctx.fillStyle = 'rgba(255,255,255,0.35)';
                ctx.fillText(this.zone.name, titlePos.x, titlePos.y - 20);
                ctx.restore();
            }
        },

        drawGroundTile: function (wx, wz) {
            var cell = this.getTileCell(wx, wz);
            var checker = (Math.floor(wx) + Math.floor(wz)) % 2;
            var base = checker ? '#3d6b3d' : '#356235';
            var edge = checker ? '#2d552d' : '#274a27';
            this.drawDiamond(wx, wz, base, edge);

            var ground = this.tileGround(cell);
            if (ground) {
                var gImg = this.loadTileImage(ground);
                if (gImg.complete && gImg.naturalWidth) {
                    this.drawTileSprite(wx, wz, gImg);
                }
            } else if (this.grassTilesReady && this.grassTiles.length && !cell) {
                this.drawGrassSprite(wx, wz, this.grassVariantIndex(wx, wz));
                return;
            }

            var overlay = this.tileOverlay(cell);
            if (overlay) {
                var oImg = this.loadTileImage(overlay);
                if (oImg.complete && oImg.naturalWidth) {
                    this.drawTileSprite(wx, wz, oImg);
                }
            }
        },

        drawTileSprite: function (wx, wz, img) {
            var c = this.toScreen(wx, wz);
            ISO.drawSprite(this.ctx, img, c.x, c.y, 1);
        },

        drawGrassSprite: function (wx, wz, variantIndex) {
            var img = this.grassTiles[variantIndex] || this.grassTiles[0];
            if (!img) return;
            var c = this.toScreen(wx, wz);
            this.ctx.save();
            this.ctx.imageSmoothingEnabled = true;
            ISO.drawSprite(this.ctx, img, c.x, c.y, 1);
            this.ctx.restore();
        },

        drawDiamond: function (wx, wz, fill, stroke) {
            var c = this.toScreen(wx, wz);
            ISO.drawDiamond(this.ctx, c.x, c.y, 1, fill, stroke);
        },

        drawEntity: function (e) {
            var ctx = this.ctx;
            var pos = this.toScreen(e.x, e.z, 0);

            if (e.kind === 'local' && window.IsoCharacter) {
                IsoCharacter.draw(ctx, pos.x, pos.y, {
                    facing: this.facing,
                    moving: this.isMoving(),
                    phase: this.animPhase,
                    colors: IsoCharacter.colorsFor(this.avatarKey),
                    isLocal: true,
                    label: e.label,
                    name: e.name,
                });
                return;
            }

            if (e.kind === 'player' && window.IsoCharacter) {
                IsoCharacter.draw(ctx, pos.x, pos.y, {
                    facing: 'south',
                    moving: false,
                    phase: this.animPhase,
                    colors: IsoCharacter.colorsFor('ranger'),
                    label: e.label,
                    name: e.name,
                });
                return;
            }

            var w = 28;
            var h = 36;

            ctx.save();
            if (e.kind === 'portal') {
                ctx.shadowColor = 'rgba(168,85,247,0.8)';
                ctx.shadowBlur = 12;
            }

            ctx.fillStyle = e.kind === 'portal' ? '#7c3aed' : '#4a5568';
            ctx.beginPath();
            ctx.ellipse(pos.x, pos.y - 8, w * 0.45, h * 0.22, 0, 0, Math.PI * 2);
            ctx.fill();

            ctx.font = (e.kind === 'portal' ? '22px' : '22px') + ' serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#fff';
            ctx.fillText(e.label, pos.x, pos.y - h + 4);

            ctx.font = '11px Segoe UI, sans-serif';
            ctx.fillStyle = 'rgba(255,255,255,0.85)';
            ctx.shadowBlur = 0;
            ctx.fillText(e.name, pos.x, pos.y + 6);
            ctx.restore();
        },

        findEntityAt: function (sx, sy) {
            var hit = null;
            var best = 999;
            var self = this;
            var list = [];

            if (this.zone && this.zone.interactables) {
                this.zone.interactables.forEach(function (it) {
                    list.push({ kind: 'interactable', x: it.x, z: it.z, id: it.id, name: it.name });
                });
            }

            list.forEach(function (e) {
                var p = self.toScreen(e.x, e.z);
                var d = Math.hypot(p.x - sx, p.y - sy - 10);
                if (d < 28 && d < best) {
                    best = d;
                    hit = e;
                }
            });
            return hit;
        },

        dirTowardWorld: function (tx, tz) {
            if (!this.state) return null;
            var dx = tx - this.state.x;
            var dz = tz - this.state.z;
            if (Math.hypot(dx, dz) < 0.8) return null;
            if (Math.abs(dx) >= Math.abs(dz)) {
                return dx > 0 ? 'east' : 'west';
            }
            return dz > 0 ? 'north' : 'south';
        },

        onMouseDown: function (ev) {
            if (ev.button !== 0 || !this.state || !window.WorldPanel) return;
            var rect = this.canvas.getBoundingClientRect();
            var sx = ev.clientX - rect.left;
            var sy = ev.clientY - rect.top;

            var hit = this.findEntityAt(sx, sy);
            if (hit && hit.id) {
                WorldPanel.interact(hit.id);
                return;
            }

            ev.preventDefault();
            this.startMouseRun(ev.clientX, ev.clientY);
        },

        onMouseMove: function (ev) {
            if (!this.mouseRunActive) return;
            this.updateMouseTarget(ev.clientX, ev.clientY);
        },

        onMouseUp: function (ev) {
            if (ev.type === 'mouseup' && ev.button !== 0) return;
            if (this.mouseRunActive) this.stopMouseRun();
        },

        updateMouseTarget: function (clientX, clientY) {
            if (!this.canvas) return;
            var rect = this.canvas.getBoundingClientRect();
            var sx = clientX - rect.left;
            var sy = clientY - rect.top;
            var world = this.screenToWorld(sx, sy);
            this.mouseWorldTarget = { x: world.x, z: world.z };
        },

        startMouseRun: function (clientX, clientY) {
            var self = this;
            this.stopKeyMove();
            this.mouseRunActive = true;
            this.updateMouseTarget(clientX, clientY);
            if (this.mouseRunInterval) return;
            this.tickMouseRun();
            this.mouseRunInterval = setInterval(function () {
                self.tickMouseRun();
            }, 80);
        },

        tickMouseRun: function () {
            if (!this.mouseRunActive || !this.mouseWorldTarget || !this.state) return;
            var dir = this.dirTowardWorld(this.mouseWorldTarget.x, this.mouseWorldTarget.z);
            if (!dir) return;
            this.activeMoveDir = dir;
            if (!this.canStep(dir)) {
                this.stopMouseRun();
                return;
            }
            this.doStep(dir, { silent: true });
        },

        stopMouseRun: function () {
            this.mouseRunActive = false;
            this.mouseWorldTarget = null;
            if (!this.keyInterval) {
                this.activeMoveDir = null;
            }
            if (this.mouseRunInterval) {
                clearInterval(this.mouseRunInterval);
                this.mouseRunInterval = null;
            }
            if (window.WorldPanel) WorldPanel._stepQueue = [];
        },

        walkToward: function (tx, tz) {
            var dir = this.dirTowardWorld(tx, tz);
            if (dir) this.doStep(dir);
        },

        onKeyDown: function (ev) {
            if (ev.target && (ev.target.tagName === 'INPUT' || ev.target.tagName === 'TEXTAREA')) return;
            var dir = MOVE_CODES[ev.code];
            if (dir) {
                ev.preventDefault();
                this.startKeyMove(dir);
            } else if (ev.code === 'KeyE') {
                ev.preventDefault();
                this.tryInteractNearest();
            }
        },

        onKeyUp: function (ev) {
            if (MOVE_CODES[ev.code]) {
                this.stopKeyMove();
            }
        },

        startKeyMove: function (dir) {
            var self = this;
            this.stopMouseRun();
            if (self.keyInterval) return;
            self.activeMoveDir = dir;
            self.doStep(dir);
            self.keyInterval = setInterval(function () {
                if (self.activeMoveDir === dir) {
                    self.doStep(dir);
                }
            }, 80);
        },

        stopKeyMove: function () {
            this.activeMoveDir = null;
            if (this.keyInterval) {
                clearInterval(this.keyInterval);
                this.keyInterval = null;
            }
            if (window.WorldPanel) WorldPanel._stepQueue = [];
        },

        doStep: function (dir, opts) {
            opts = opts || {};
            if (!window.WorldPanel || !this.state) return;
            if (!this.canStep(dir)) {
                if (!opts.silent && typeof showMsg === 'function') {
                    showMsg('Сюда нельзя пройти', 'error');
                }
                return;
            }
            if (WorldPanel._busy) {
                WorldPanel.queueStep(dir);
                return;
            }
            if (!this.predictStep(dir)) return;
            WorldPanel.step(dir, { predicted: true, direction: dir });
        },

        tryInteractNearest: function () {
            if (!this.zone || !this.state || !window.WorldPanel) return;
            var best = null;
            var bestDist = 999;
            (this.zone.interactables || []).forEach(function (it) {
                var d = Math.hypot(it.x - WorldView.state.x, it.z - WorldView.state.z);
                if (d < bestDist) {
                    bestDist = d;
                    best = it;
                }
            });
            if (best && bestDist <= 6) {
                WorldPanel.interact(best.id);
            } else if (typeof showMsg === 'function') {
                showMsg('Подойдите ближе к объекту', 'error');
            }
        },
    };

    window.MinimapView = {
        canvas: null,
        ctx: null,
        zone: null,
        zoneSlug: null,
        state: null,
        nearbyPlayers: [],
        display: { x: 0, z: 0 },
        pulse: 0,
        viewRadius: 32,

        init: function () {
            this.canvas = document.getElementById('minimapCanvas');
            if (!this.canvas || this.canvas.dataset.bound) return;
            this.canvas.dataset.bound = '1';
            this.ctx = this.canvas.getContext('2d');
        },

        onContext: function (data) {
            if (!data || !data.state) return;
            if (data.state.zone_slug !== this.zoneSlug) {
                this.loadZone(data.state.zone_slug);
            }
            this.state = data.state;
            this.nearbyPlayers = data.nearby_players || [];
        },

        loadZone: function (slug) {
            var self = this;
            this.zoneSlug = slug;
            if (!window.GameApi) return;
            GameApi.fetch('/api/world/zones/' + encodeURIComponent(slug))
                .then(function (res) { return res.json(); })
                .then(function (data) { self.zone = data.zone || null; })
                .catch(function () {});
        },

        worldToMap: function (x, z, size, centerX, centerZ, scale) {
            return {
                x: size / 2 + (x - centerX) * scale,
                y: size / 2 + (z - centerZ) * scale,
            };
        },

        mapScale: function (size) {
            var pad = 8;
            return (size / 2 - pad) / this.viewRadius;
        },

        tick: function () {
            if (window.WorldView && WorldView.display) {
                this.display.x += (WorldView.display.x - this.display.x) * 0.28;
                this.display.z += (WorldView.display.z - this.display.z) * 0.28;
            } else if (this.state) {
                this.display.x = this.state.x;
                this.display.z = this.state.z;
            }
            this.pulse += 0.07;
            this.render();
        },

        render: function () {
            var ctx = this.ctx;
            var canvas = this.canvas;
            if (!ctx || !canvas) return;
            var size = canvas.width;
            var cx = size / 2;
            var cy = size / 2;
            var radius = size / 2 - 2;

            ctx.clearRect(0, 0, size, size);
            ctx.save();
            ctx.beginPath();
            ctx.arc(cx, cy, radius, 0, Math.PI * 2);
            ctx.clip();

            var bg = ctx.createRadialGradient(cx * 0.6, cy * 0.5, 0, cx, cy, radius);
            bg.addColorStop(0, '#3a5a32');
            bg.addColorStop(1, '#1a2418');
            ctx.fillStyle = bg;
            ctx.fillRect(0, 0, size, size);

            var bounds = (this.zone && this.zone.bounds) || { min_x: -50, max_x: 50, min_z: -50, max_z: 50 };
            var px = this.display.x;
            var pz = this.display.z;
            var scale = this.mapScale(size);
            var tl = this.worldToMap(bounds.min_x, bounds.min_z, size, px, pz, scale);
            var br = this.worldToMap(bounds.max_x, bounds.max_z, size, px, pz, scale);
            ctx.fillStyle = 'rgba(53, 98, 53, 0.55)';
            ctx.fillRect(tl.x, tl.y, br.x - tl.x, br.y - tl.y);
            ctx.strokeStyle = 'rgba(212, 165, 116, 0.35)';
            ctx.lineWidth = 1;
            ctx.strokeRect(tl.x, tl.y, br.x - tl.x, br.y - tl.y);

            var self = this;
            (this.zone && this.zone.interactables || []).forEach(function (it) {
                var p = self.worldToMap(it.x, it.z, size, px, pz, scale);
                ctx.fillStyle = it.kind === 'npc' ? '#60a5fa' : (it.kind === 'encounter' ? '#f87171' : '#fbbf24');
                ctx.beginPath();
                ctx.arc(p.x, p.y, 2.5, 0, Math.PI * 2);
                ctx.fill();
            });

            (this.zone && this.zone.portals || []).forEach(function (p) {
                var pt = self.worldToMap(p.x, p.z, size, px, pz, scale);
                ctx.fillStyle = '#a78bfa';
                ctx.beginPath();
                ctx.arc(pt.x, pt.y, 3.5, 0, Math.PI * 2);
                ctx.fill();
            });

            (this.nearbyPlayers || []).forEach(function (p) {
                if (window.GameState && p.character_uuid === GameState.characterUuid) return;
                var pt = self.worldToMap(p.x, p.z, size, px, pz, scale);
                ctx.fillStyle = '#38bdf8';
                ctx.beginPath();
                ctx.arc(pt.x, pt.y, 3, 0, Math.PI * 2);
                ctx.fill();
            });

            var glow = 6 + Math.sin(this.pulse) * 2;
            ctx.fillStyle = 'rgba(102, 126, 234, 0.35)';
            ctx.beginPath();
            ctx.arc(cx, cy, glow, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#667eea';
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.arc(cx, cy, 4.5, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();

            ctx.restore();
        },
    };
})();
