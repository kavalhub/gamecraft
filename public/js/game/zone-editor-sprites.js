/**
 * Large sprite picker window — syncs selection to zone editor via ZoneEditorBridge.
 */
(function () {
    'use strict';

    var token = localStorage.getItem('authToken');
    if (!token) {
        window.location.href = gameUrl('/');
        return;
    }

    function apiFetch(url) {
        return fetch(gameUrl(url), {
            headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                if (!r.ok) throw new Error(data.error || data.message || ('HTTP ' + r.status));
                return data;
            });
        });
    }

    var Picker = {
        sprites: [],
        folders: [],
        folderFilter: '',
        spriteSearch: '',
        selectedPath: null,
        applyingExternal: false,

        init: function () {
            var self = this;
            document.getElementById('folderSelect').addEventListener('change', function (e) {
                self.folderFilter = e.target.value;
                self.renderGrid();
            });
            document.getElementById('spriteSearch').addEventListener('input', function (e) {
                self.spriteSearch = e.target.value.trim().toLowerCase();
                self.renderGrid();
            });

            if (window.ZoneEditorBridge) {
                ZoneEditorBridge.subscribe(function (msg) {
                    if (msg.type === 'select-sprite' && msg.path) {
                        self.applyExternalSelection(msg.path);
                    }
                    if (msg.type === 'editor-ready') {
                        self.setBridgeOnline(true);
                    }
                });
                ZoneEditorBridge.publish({ type: 'picker-ready' });
            }

            this.loadSprites().catch(function (e) {
                document.getElementById('spriteGrid').innerHTML =
                    '<div class="empty-msg">Ошибка: ' + (e.message || e) + '</div>';
            });
        },

        setBridgeOnline: function (on) {
            var el = document.querySelector('#bridgeStatus .status-dot');
            if (el) el.classList.toggle('off', !on);
        },

        loadSprites: function () {
            var self = this;
            return apiFetch('/api/world/sprites').then(function (data) {
                self.sprites = data.sprites || [];
                self.folders = data.folders || [];
                self.renderFolderSelect();
                var preferred = self.folders.find(function (f) { return f.indexOf('Isometric') !== -1; });
                if (preferred) {
                    self.folderFilter = preferred;
                    document.getElementById('folderSelect').value = preferred;
                }
                self.renderGrid();
                var visible = self.getFilteredSprites();
                if (visible.length) self.selectLocal(visible[0], false);
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

        getFilteredSprites: function () {
            var self = this;
            return this.sprites.filter(function (sp) {
                if (self.folderFilter && sp.folder !== self.folderFilter) return false;
                if (self.spriteSearch && sp.name.toLowerCase().indexOf(self.spriteSearch) === -1) return false;
                return true;
            });
        },

        findByPath: function (path) {
            return this.sprites.find(function (s) { return s.path === path; }) || null;
        },

        renderGrid: function () {
            var grid = document.getElementById('spriteGrid');
            grid.innerHTML = '';
            var list = this.getFilteredSprites();
            if (!this.sprites.length) {
                grid.innerHTML = '<div class="empty-msg">Нет PNG в public/assets/</div>';
                return;
            }
            if (!list.length) {
                grid.innerHTML = '<div class="empty-msg">Ничего не найдено</div>';
                return;
            }
            var self = this;
            list.forEach(function (sp) {
                var el = document.createElement('div');
                el.className = 'picker-item' + (self.selectedPath === sp.path ? ' selected' : '');
                el.title = sp.path;
                el.innerHTML = '<img src="' + sp.url + '" alt=""><span>' + sp.name + '</span>';
                el.addEventListener('click', function () { self.pick(sp); });
                grid.appendChild(el);
            });
        },

        updatePreview: function (sp) {
            var img = document.getElementById('previewImg');
            var empty = document.getElementById('previewEmpty');
            var meta = document.getElementById('previewMeta');
            if (!sp) {
                img.style.display = 'none';
                empty.style.display = 'block';
                meta.innerHTML = '';
                return;
            }
            img.src = sp.url;
            img.alt = sp.name;
            img.style.display = 'block';
            empty.style.display = 'none';
            meta.innerHTML = '<strong>' + sp.name + '</strong><div class="path">' + sp.path + '</div>';
        },

        selectLocal: function (sp, broadcast) {
            if (!sp) return;
            this.selectedPath = sp.path;
            this.renderGrid();
            this.updatePreview(sp);
            if (broadcast !== false && window.ZoneEditorBridge) {
                ZoneEditorBridge.publish({ type: 'select-sprite', path: sp.path, source: 'picker' });
            }
        },

        pick: function (sp) {
            this.selectLocal(sp, true);
            this.setBridgeOnline(true);
        },

        applyExternalSelection: function (path) {
            if (this.applyingExternal || this.selectedPath === path) return;
            var sp = this.findByPath(path);
            if (!sp) return;
            this.applyingExternal = true;
            this.selectLocal(sp, false);
            this.applyingExternal = false;
            this.setBridgeOnline(true);
        },
    };

    document.addEventListener('DOMContentLoaded', function () { Picker.init(); });
})();
