/**
 * Zone tile editor — paint sprites and walkability per cell.
 */
(function () {
    'use strict';

    var ISO = window.IsoTileRender;
    var TILE_W = ISO.TILE_W;
    var TILE_H = ISO.TILE_H;

    var token = localStorage.getItem('authToken');
    if (!token) {
        window.location.href = '/';
        return;
    }

    function apiFetch(url, options) {
        options = options || {};
        var headers = Object.assign({ 'Accept': 'application/json', 'Authorization': 'Bearer ' + token }, options.headers || {});
        if (options.body && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }
        return fetch(url, Object.assign({}, options, { headers: headers }));
    }

    function cellKey(x, z) {
        return x + ',' + z;
    }

    function snapCell(x, z) {
        return { x: Math.floor(x), z: Math.floor(z) };
    }

    var Editor = {
        canvas: null,
        ctx: null,
        zone: null,
        zoneSlug: null,
        zones: [],
        cells: {},
        sprites: [],
        folders: [],
        folderFilter: '',
        spriteSearch: '',
        selectedSprite: null,
        tool: 'ground',
        paintLayer: 'ground',
        showBlocked: true,
        camera: { x: 0, y: 0 },
        zoom: 1,
        imageCache: {},
        painting: false,
        panning: false,
        panStart: null,
        dirty: false,
        rafId: null,
        hoverCell: null,

        init: function () {
            this.canvas = document.getElementById('zoneEditorCanvas');
            this.ctx = this.canvas.getContext('2d');
            this.bindUi();
            this.resize();
            window.addEventListener('resize', this.resize.bind(this));
            this.canvas.addEventListener('mousedown', this.onMouseDown.bind(this));
            this.canvas.addEventListener('mousemove', this.onMouseMove.bind(this));
            this.canvas.addEventListener('mouseup', this.onMouseUp.bind(this));
            this.canvas.addEventListener('mouseleave', this.onMouseUp.bind(this));
            this.canvas.addEventListener('contextmenu', function (e) { e.preventDefault(); });
            this.canvas.addEventListener('wheel', this.onWheel.bind(this), { passive: false });
            document.addEventListener('keydown', this.onKeyDown.bind(this));
            document.addEventListener('keyup', this.onKeyUp.bind(this));
            this.canvas.addEventListener('dblclick', this.resetView.bind(this));
            this._spaceDown = false;

            var self = this;
            Promise.all([this.loadZones(), this.loadSprites()]).then(function () {
                var slug = window.ZONE_EDITOR_INITIAL_SLUG || (self.zones[0] && self.zones[0].slug) || 'craft_city';
                var sel = document.getElementById('zoneSelect');
                if (sel) sel.value = slug;
                self.bindBridge();
                return self.loadZone(slug);
            }).then(function () {
                self.loop();
            }).catch(function (e) {
                self.setStatus('Ошибка: ' + (e.message || e));
            });
        },

        bindUi: function () {
            var self = this;
            document.getElementById('zoneSelect').addEventListener('change', function (e) {
                if (self.dirty && !confirm('Есть несохранённые изменения. Переключить зону?')) {
                    e.target.value = self.zoneSlug;
                    return;
                }
                self.loadZone(e.target.value);
            });
            document.getElementById('saveBtn').addEventListener('click', function () { self.save(); });
            document.getElementById('playBtn').addEventListener('click', function () {
                window.location.href = '/play';
            });
            document.getElementById('openSpritePickerBtn').addEventListener('click', function () {
                window.open('/zone-editor/sprites', 'zoneEditorSprites', 'width=1100,height=860,menubar=no,toolbar=no,location=no,status=no');
            });
            document.getElementById('showBlocked').addEventListener('change', function (e) {
                self.showBlocked = e.target.checked;
            });
            document.querySelectorAll('.tool-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.tool-btn').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    self.tool = btn.dataset.tool;
                });
            });
            document.getElementById('applyBoundsBtn').addEventListener('click', function () { self.applyBounds(); });
            ['boundMinX', 'boundMaxX', 'boundMinZ', 'boundMaxZ'].forEach(function (id) {
                document.getElementById(id).addEventListener('input', function () { self.updateBoundsMeta(); });
            });
            document.getElementById('folderSelect').addEventListener('change', function (e) {
                self.folderFilter = e.target.value;
                self.renderSpriteGrid();
            });
            document.getElementById('spriteSearch').addEventListener('input', function (e) {
                self.spriteSearch = e.target.value.trim().toLowerCase();
                self.renderSpriteGrid();
            });
        },

        loadZones: function () {
            var self = this;
            return apiFetch('/api/world/zones').then(function (r) { return r.json(); }).then(function (data) {
                self.zones = data.zones || [];
                var sel = document.getElementById('zoneSelect');
                sel.innerHTML = '';
                self.zones.forEach(function (z) {
                    var opt = document.createElement('option');
                    opt.value = z.slug;
                    opt.textContent = z.name || z.slug;
                    sel.appendChild(opt);
                });
            });
        },

        loadSprites: function () {
            var self = this;
            return apiFetch('/api/world/sprites').then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (data) {
                    if (!r.ok) {
                        throw new Error(data.error || data.message || ('Не удалось загрузить спрайты (HTTP ' + r.status + ')'));
                    }
                    return data;
                });
            }).then(function (data) {
                self.sprites = data.sprites || [];
                self.folders = data.folders || [];
                self.renderFolderSelect();
                self.renderSpriteGrid();
                var preferred = self.folders.find(function (f) { return f.indexOf('Isometric') !== -1; });
                if (preferred) {
                    self.folderFilter = preferred;
                    document.getElementById('folderSelect').value = preferred;
                    self.renderSpriteGrid();
                }
                var visible = self.getFilteredSprites();
                if (visible.length) {
                    self.selectSprite(visible[0]);
                }
            });
        },

        renderFolderSelect: function () {
            var sel = document.getElementById('folderSelect');
            sel.innerHTML = '<option value="">Все папки (' + this.sprites.length + ')</option>';
            this.folders.forEach(function (folder) {
                var count = this.sprites.filter(function (s) { return s.folder === folder; }).length;
                var opt = document.createElement('option');
                opt.value = folder;
                opt.textContent = folder + ' (' + count + ')';
                sel.appendChild(opt);
            }, this);
        },

        bindBridge: function () {
            var self = this;
            if (!window.ZoneEditorBridge) return;
            ZoneEditorBridge.subscribe(function (msg) {
                if (msg.type === 'select-sprite' && msg.path) {
                    if (self.selectedSprite && self.selectedSprite.path === msg.path) return;
                    self.selectSpriteByPath(msg.path, true);
                }
                if (msg.type === 'picker-ready') {
                    if (self.selectedSprite) {
                        ZoneEditorBridge.publish({
                            type: 'select-sprite',
                            path: self.selectedSprite.path,
                            source: 'editor',
                        });
                    }
                }
            });
            ZoneEditorBridge.publish({ type: 'editor-ready' });
        },

        selectSpriteByPath: function (path, fromBridge) {
            var sp = this.sprites.find(function (s) { return s.path === path; });
            if (sp) this.selectSprite(sp, fromBridge === true);
        },

        getFilteredSprites: function () {
            var self = this;
            return this.sprites.filter(function (sp) {
                if (self.folderFilter && sp.folder !== self.folderFilter) return false;
                if (self.spriteSearch && sp.name.toLowerCase().indexOf(self.spriteSearch) === -1) return false;
                return true;
            });
        },

        renderSpriteGrid: function () {
            var grid = document.getElementById('spriteGrid');
            grid.innerHTML = '';
            var list = this.getFilteredSprites();
            if (!this.sprites.length) {
                grid.innerHTML = '<div class="empty-sprites">Нет PNG в <code>public/assets/</code>.<br>Добавьте изображения и обновите страницу.<br><br>Если папка не читается веб-сервером:<br><code>chmod -R a+rX public/assets/world/</code></div>';
                return;
            }
            if (!list.length) {
                grid.innerHTML = '<div class="empty-sprites">Ничего не найдено. Смените папку или поиск.</div>';
                return;
            }
            var self = this;
            list.forEach(function (sp) {
                var el = document.createElement('div');
                el.className = 'sprite-item' + (self.selectedSprite && self.selectedSprite.path === sp.path ? ' selected' : '');
                el.innerHTML = '<img src="' + sp.url + '" alt=""><span>' + sp.name + '</span>';
                el.addEventListener('click', function () { self.selectSprite(sp); });
                grid.appendChild(el);
            });
        },

        selectSprite: function (sp, fromBridge) {
            var self = this;
            this.selectedSprite = sp;
            if (this.tool !== 'ground' && this.tool !== 'overlay') {
                this.tool = 'ground';
            }
            document.querySelectorAll('.tool-btn').forEach(function (b) {
                b.classList.toggle('active', b.dataset.tool === self.tool);
            });
            this.renderSpriteGrid();
            if (!fromBridge && window.ZoneEditorBridge) {
                ZoneEditorBridge.publish({
                    type: 'select-sprite',
                    path: sp.path,
                    source: 'editor',
                });
            }
        },

        loadZone: function (slug) {
            var self = this;
            this.zoneSlug = slug;
            this.dirty = false;
            this.setStatus('Загрузка зоны…');
            return apiFetch('/api/world/zones/' + encodeURIComponent(slug) + '/tiles')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    return apiFetch('/api/world/zones/' + encodeURIComponent(slug)).then(function (r2) {
                        return r2.json().then(function (zdata) {
                            return { tiles: data.tiles, zone: zdata.zone };
                        });
                    });
                })
                .then(function (bundle) {
                    self.zone = bundle.zone;
                    self.cells = Object.assign({}, (bundle.tiles && bundle.tiles.cells) || {});
                    self.normalizeCells();
                    document.getElementById('zoneTitle').textContent = self.zone.name || slug;
                    self.syncBoundsForm();
                    self.centerCamera();
                    self.preloadCellImages();
                    self.setStatus('Готово · клеток: ' + Object.keys(self.cells).length);
                });
        },

        syncBoundsForm: function () {
            var b = (this.zone && this.zone.bounds) || { min_x: -50, max_x: 50, min_z: -50, max_z: 50 };
            document.getElementById('boundMinX').value = b.min_x;
            document.getElementById('boundMaxX').value = b.max_x;
            document.getElementById('boundMinZ').value = b.min_z;
            document.getElementById('boundMaxZ').value = b.max_z;
            this.updateBoundsMeta();
        },

        updateBoundsMeta: function () {
            var minX = parseFloat(document.getElementById('boundMinX').value);
            var maxX = parseFloat(document.getElementById('boundMaxX').value);
            var minZ = parseFloat(document.getElementById('boundMinZ').value);
            var maxZ = parseFloat(document.getElementById('boundMaxZ').value);
            if ([minX, maxX, minZ, maxZ].some(function (v) { return isNaN(v); })) {
                document.getElementById('boundsMeta').textContent = '—';
                return;
            }
            var w = maxX - minX + 1;
            var h = maxZ - minZ + 1;
            document.getElementById('boundsMeta').textContent = w + ' × ' + h + ' клеток';
        },

        applyBounds: function () {
            var self = this;
            var bounds = {
                min_x: parseFloat(document.getElementById('boundMinX').value),
                max_x: parseFloat(document.getElementById('boundMaxX').value),
                min_z: parseFloat(document.getElementById('boundMinZ').value),
                max_z: parseFloat(document.getElementById('boundMaxZ').value),
            };
            this.setStatus('Сохранение границ…');
            apiFetch('/api/world/zones/' + encodeURIComponent(this.zoneSlug), {
                method: 'PUT',
                body: JSON.stringify({ bounds: bounds }),
            }).then(function (r) {
                return r.json().then(function (d) { return { ok: r.ok, data: d }; });
            }).then(function (res) {
                if (!res.ok) throw new Error(res.data.error || res.data.message || 'Ошибка');
                self.zone.bounds = res.data.zone.bounds;
                self.centerCamera();
                self.showMsg('Размер зоны обновлён', 'ok');
                self.setStatus('Границы сохранены');
            }).catch(function (e) {
                self.showMsg(e.message, 'err');
                self.setStatus('Ошибка сохранения границ');
            });
        },

        preloadCellImages: function () {
            var self = this;
            var paths = {};
            Object.keys(this.cells).forEach(function (key) {
                var cell = self.cells[key];
                var paths = [self.cellGround(cell), self.cellOverlay(cell)];
                paths.forEach(function (sp) {
                    if (sp) self.loadImage(sp);
                });
            });
        },

        loadImage: function (path) {
            if (this.imageCache[path]) return this.imageCache[path];
            var self = this;
            var img = new Image();
            img.onload = function () { self.render(); };
            img.src = '/assets/' + path.replace(/^\//, '');
            this.imageCache[path] = img;
            return img;
        },

        centerCamera: function () {
            if (!this.zone || !this.zone.bounds) return;
            var b = this.zone.bounds;
            var cx = (b.min_x + b.max_x) / 2;
            var cz = (b.min_z + b.max_z) / 2;
            var p = this.rawScreen(cx, cz);
            this.camera.x = this.canvas.width * 0.5 - p.x * this.zoom;
            this.camera.y = this.canvas.height * 0.5 - p.y * this.zoom;
        },

        save: function () {
            var self = this;
            this.setStatus('Сохранение…');
            var payload = { cells: this.cells || {} };
            apiFetch('/api/world/zones/' + encodeURIComponent(this.zoneSlug) + '/tiles', {
                method: 'PUT',
                body: JSON.stringify(payload),
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                .then(function (res) {
                    if (!res.ok) throw new Error(res.data.error || res.data.message || (res.data.errors && res.data.errors.cells && res.data.errors.cells[0]) || 'Ошибка сохранения');
                    self.cells = Object.assign({}, (res.data.tiles && res.data.tiles.cells) || {});
                    self.dirty = false;
                    self.showMsg('Сохранено', 'ok');
                    self.setStatus('Сохранено · клеток: ' + Object.keys(self.cells).length);
                })
                .catch(function (e) {
                    self.showMsg(e.message, 'err');
                    self.setStatus('Ошибка сохранения');
                });
        },

        showMsg: function (text, kind) {
            var el = document.getElementById('editorMsg');
            el.textContent = text;
            el.className = 'msg show ' + (kind || '');
            setTimeout(function () { el.classList.remove('show'); }, 3000);
        },

        setStatus: function (text) {
            document.getElementById('editorStatus').textContent = text;
        },

        getCell: function (x, z) {
            return this.cells[cellKey(x, z)] || null;
        },

        cellGround: function (cell) {
            if (!cell) return null;
            return cell.ground || cell.sprite || null;
        },

        cellOverlay: function (cell) {
            if (!cell) return null;
            return cell.overlay || null;
        },

        isCellEmpty: function (cell) {
            if (!cell) return true;
            if (this.cellGround(cell) || this.cellOverlay(cell)) return false;
            return cell.walkable !== false;
        },

        normalizeCells: function () {
            var self = this;
            Object.keys(this.cells).forEach(function (key) {
                var cell = self.cells[key];
                if (cell.sprite && !cell.ground) {
                    cell.ground = cell.sprite;
                    delete cell.sprite;
                }
            });
        },

        applyTool: function (wx, wz, rightClick) {
            var c = snapCell(wx, wz);
            var key = cellKey(c.x, c.z);
            var b = this.zone && this.zone.bounds;
            if (b) {
                if (c.x < b.min_x || c.x > b.max_x || c.z < b.min_z || c.z > b.max_z) return;
            }

            if (rightClick) {
                var cur = this.cells[key] || {};
                var walkable = cur.walkable === false ? true : false;
                this.cells[key] = Object.assign({}, cur, { walkable: walkable });
                if (this.isCellEmpty(this.cells[key])) {
                    delete this.cells[key];
                }
                this.dirty = true;
                this.updateCellInfo(c.x, c.z);
                return;
            }

            if (this.tool === 'erase') {
                var existing = this.cells[key];
                if (existing && existing.overlay) {
                    delete existing.overlay;
                    if (this.isCellEmpty(existing)) {
                        delete this.cells[key];
                    }
                } else if (existing) {
                    delete existing.ground;
                    delete existing.sprite;
                    if (this.isCellEmpty(existing)) {
                        delete this.cells[key];
                    }
                }
                this.dirty = true;
                this.updateCellInfo(c.x, c.z);
                return;
            }

            if (this.tool === 'block') {
                var blocked = this.cells[key] || {};
                this.cells[key] = Object.assign({}, blocked, { walkable: false });
                this.dirty = true;
                this.updateCellInfo(c.x, c.z);
                return;
            }

            if (this.tool === 'ground' || this.tool === 'overlay') {
                if (!this.selectedSprite) {
                    this.showMsg('Выберите спрайт слева', 'err');
                    return;
                }
                var prev = this.cells[key] || {};
                var next = Object.assign({}, prev, {
                    walkable: prev.walkable === false ? false : true,
                });
                if (this.tool === 'ground') {
                    next.ground = this.selectedSprite.path;
                    delete next.sprite;
                } else {
                    next.overlay = this.selectedSprite.path;
                }
                this.cells[key] = next;
                this.loadImage(this.selectedSprite.path);
                this.dirty = true;
                this.updateCellInfo(c.x, c.z);
            }
        },

        updateCellInfo: function (x, z) {
            var cell = this.getCell(x, z);
            var info = 'Клетка: ' + x + ', ' + z;
            if (cell) {
                var g = this.cellGround(cell);
                var o = this.cellOverlay(cell);
                if (g) info += ' · фон: ' + g.split('/').pop();
                if (o) info += ' · объект: ' + o.split('/').pop();
                info += cell.walkable === false ? ' · 🚫' : ' · ✓';
            }
            document.getElementById('cellInfo').textContent = info;
        },

        rawScreen: function (x, z) {
            return ISO.rawScreen(x, z, 0);
        },

        toScreen: function (x, z) {
            var p = this.rawScreen(x, z);
            return {
                x: p.x * this.zoom + this.camera.x,
                y: p.y * this.zoom + this.camera.y,
            };
        },

        screenToWorld: function (sx, sy) {
            return ISO.screenToWorld(sx, sy, this.camera, this.zoom);
        },

        onMouseDown: function (e) {
            if (e.button === 1 || (e.button === 0 && this._spaceDown)) {
                this.panning = true;
                this.panStart = { x: e.clientX, y: e.clientY, camX: this.camera.x, camY: this.camera.y };
                return;
            }
            if (e.button === 2) {
                var w = this.screenToWorld(e.offsetX, e.offsetY);
                this.applyTool(w.x, w.z, true);
                return;
            }
            if (e.button === 0) {
                this.painting = true;
                var w2 = this.screenToWorld(e.offsetX, e.offsetY);
                this.applyTool(w2.x, w2.z, false);
            }
        },

        onMouseMove: function (e) {
            var w = this.screenToWorld(e.offsetX, e.offsetY);
            var c = snapCell(w.x, w.z);
            this.hoverCell = c;
            this.updateCellInfo(c.x, c.z);

            if (this.panning && this.panStart) {
                this.camera.x = this.panStart.camX + (e.clientX - this.panStart.x);
                this.camera.y = this.panStart.camY + (e.clientY - this.panStart.y);
                return;
            }
            if (this.painting) {
                this.applyTool(w.x, w.z, false);
            }
        },

        onMouseUp: function () {
            this.painting = false;
            this.panning = false;
            this.panStart = null;
        },

        onWheel: function (e) {
            e.preventDefault();
            var sx = e.offsetX;
            var sy = e.offsetY;
            var world = this.screenToWorld(sx, sy);
            var factor = e.deltaY > 0 ? 0.9 : 1.1;
            this.zoom = Math.min(3, Math.max(0.35, this.zoom * factor));
            var p = this.rawScreen(world.x, world.z);
            this.camera.x = sx - p.x * this.zoom;
            this.camera.y = sy - p.y * this.zoom;
        },

        onKeyDown: function (e) {
            if (e.code === 'Space') {
                e.preventDefault();
                this._spaceDown = true;
                this.canvas.style.cursor = 'grab';
            }
        },

        onKeyUp: function (e) {
            if (e.code === 'Space') {
                this._spaceDown = false;
                this.canvas.style.cursor = 'crosshair';
            }
        },

        resize: function () {
            var wrap = this.canvas.parentElement;
            this.canvas.width = wrap.clientWidth;
            this.canvas.height = wrap.clientHeight;
        },

        getVisibleRange: function (bounds) {
            var margin = 4;
            var corners = [[0, 0], [this.canvas.width, 0], [0, this.canvas.height], [this.canvas.width, this.canvas.height]];
            var minX = Infinity, maxX = -Infinity, minZ = Infinity, maxZ = -Infinity;
            for (var i = 0; i < corners.length; i++) {
                var w = this.screenToWorld(corners[i][0], corners[i][1]);
                minX = Math.min(minX, w.x);
                maxX = Math.max(maxX, w.x);
                minZ = Math.min(minZ, w.z);
                maxZ = Math.max(maxZ, w.z);
            }
            return {
                minX: Math.max(bounds.min_x, Math.floor(minX) - margin),
                maxX: Math.min(bounds.max_x, Math.ceil(maxX) + margin),
                minZ: Math.max(bounds.min_z, Math.floor(minZ) - margin),
                maxZ: Math.min(bounds.max_z, Math.ceil(maxZ) + margin),
            };
        },

        resetView: function () {
            this.zoom = 1;
            this.centerCamera();
        },

        drawDiamond: function (wx, wz, fill, stroke) {
            var c = this.toScreen(wx, wz);
            ISO.drawDiamond(this.ctx, c.x, c.y, this.zoom, fill, stroke);
        },

        drawCellSprite: function (x, z, spritePath) {
            if (!spritePath) return;
            var img = this.loadImage(spritePath);
            if (img.complete && img.naturalWidth) {
                var c = this.toScreen(x, z);
                ISO.drawSprite(this.ctx, img, c.x, c.y, this.zoom);
            }
        },

        drawCell: function (x, z) {
            var cell = this.getCell(x, z);
            var checker = (x + z) % 2;
            var base = checker ? '#3d6b3d' : '#356235';
            this.drawDiamond(x, z, base, '#274a27');

            this.drawCellSprite(x, z, this.cellGround(cell));
            this.drawCellSprite(x, z, this.cellOverlay(cell));

            if (this.showBlocked && cell && cell.walkable === false) {
                var p = this.toScreen(x, z);
                var hw = ISO.ISO_HW * this.zoom;
                var hh = ISO.ISO_HH * this.zoom;
                this.ctx.save();
                this.ctx.beginPath();
                this.ctx.moveTo(p.x, p.y - hh);
                this.ctx.lineTo(p.x + hw, p.y);
                this.ctx.lineTo(p.x, p.y + hh);
                this.ctx.lineTo(p.x - hw, p.y);
                this.ctx.closePath();
                this.ctx.fillStyle = 'rgba(220, 38, 38, 0.45)';
                this.ctx.fill();
                this.ctx.strokeStyle = 'rgba(248, 113, 113, 0.8)';
                this.ctx.lineWidth = 1.5;
                this.ctx.stroke();
                this.ctx.restore();
            }
        },

        drawHoverPreview: function () {
            if (!this.hoverCell || !this.selectedSprite) return;
            if (this.tool !== 'ground' && this.tool !== 'overlay') return;
            var b = this.zone && this.zone.bounds;
            if (b) {
                if (this.hoverCell.x < b.min_x || this.hoverCell.x > b.max_x
                    || this.hoverCell.z < b.min_z || this.hoverCell.z > b.max_z) return;
            }
            var img = this.loadImage(this.selectedSprite.path);
            if (!img.complete || !img.naturalWidth) return;
            var c = this.toScreen(this.hoverCell.x, this.hoverCell.z);
            this.ctx.save();
            this.ctx.globalAlpha = 0.55;
            ISO.drawSprite(this.ctx, img, c.x, c.y, this.zoom);
            this.ctx.restore();
            var p = this.toScreen(this.hoverCell.x, this.hoverCell.z);
            ISO.drawDiamond(this.ctx, p.x, p.y, this.zoom, 'rgba(255,255,255,0.08)', 'rgba(255,255,255,0.35)');
        },

        drawMarkers: function () {
            var self = this;
            var drawMarker = function (x, z, color, label) {
                var p = self.toScreen(x, z);
                self.ctx.fillStyle = color;
                self.ctx.beginPath();
                self.ctx.arc(p.x, p.y - 8 * self.zoom, 5 * self.zoom, 0, Math.PI * 2);
                self.ctx.fill();
                self.ctx.font = (11 * self.zoom) + 'px sans-serif';
                self.ctx.fillStyle = '#fff';
                self.ctx.fillText(label, p.x + 8 * self.zoom, p.y - 10 * self.zoom);
            };
            (this.zone.interactables || []).forEach(function (it) {
                drawMarker(it.x, it.z, '#60a5fa', it.name || it.id);
            });
            (this.zone.portals || []).forEach(function (p) {
                drawMarker(p.x, p.z, '#a78bfa', p.id);
            });
        },

        render: function () {
            var ctx = this.ctx;
            if (!ctx || !this.zone) return;
            ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

            var bounds = this.zone.bounds || { min_x: -50, max_x: 50, min_z: -50, max_z: 50 };
            var range = this.getVisibleRange(bounds);
            if (range.minX > range.maxX || range.minZ > range.maxZ) {
                this.resetView();
                range = this.getVisibleRange(bounds);
            }
            var tileList = [];
            for (var x = range.minX; x <= range.maxX; x++) {
                for (var z = range.minZ; z <= range.maxZ; z++) {
                    tileList.push({ x: x, z: z, sort: x + z });
                }
            }
            tileList.sort(function (a, b) { return a.sort - b.sort; });
            for (var i = 0; i < tileList.length; i++) {
                this.drawCell(tileList[i].x, tileList[i].z);
            }

            var b = bounds;
            var tl = this.toScreen(b.min_x, b.min_z);
            var br = this.toScreen(b.max_x, b.max_z);
            var bl = this.toScreen(b.min_x, b.max_z);
            var tr = this.toScreen(b.max_x, b.min_z);
            this.ctx.save();
            this.ctx.strokeStyle = 'rgba(212, 165, 116, 0.85)';
            this.ctx.lineWidth = 2;
            this.ctx.setLineDash([6, 4]);
            this.ctx.beginPath();
            this.ctx.moveTo(tl.x, tl.y - ISO.ISO_HH * this.zoom);
            this.ctx.lineTo(tr.x + ISO.ISO_HW * this.zoom, tr.y);
            this.ctx.lineTo(br.x, br.y + ISO.ISO_HH * this.zoom);
            this.ctx.lineTo(bl.x - ISO.ISO_HW * this.zoom, bl.y);
            this.ctx.closePath();
            this.ctx.stroke();
            this.ctx.restore();

            this.drawHoverPreview();
            this.drawMarkers();
        },

        loop: function () {
            var self = this;
            this.render();
            this.rafId = requestAnimationFrame(function () { self.loop(); });
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        Editor.init();
    });
})();
