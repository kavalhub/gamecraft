/**
 * Game item UI (без ES modules — совместимость с Opera и др.)
 */
(function () {
    'use strict';

    function pickDisplayStats(itemStats, baseStats) {
        if (itemStats && typeof itemStats === 'object' && Object.keys(itemStats).length > 0) {
            return itemStats;
        }
        if (baseStats && typeof baseStats === 'object') {
            return baseStats;
        }
        return {};
    }

    function normalizeDescriptor(raw) {
        let stage = raw.stage;
        if (stage === undefined || stage === null || stage === '') {
            if (raw.is_resource || raw.template_type === 'material') {
                stage = '';
            } else if (raw.template_type === 'blueprint') {
                stage = 'blueprint';
            } else if (raw.template_slug && String(raw.template_slug).indexOf('recipe_') === 0) {
                stage = 'blueprint';
            } else {
                stage = 'item';
            }
        }

        const quantity = parseInt(raw.quantity, 10) || 1;

        return {
            uuid: raw.uuid || null,
            template_slug: raw.template_slug || '',
            name: raw.name || raw.template_name || raw.template_slug || 'Предмет',
            icon: raw.icon || raw.template_icon || (stage === 'blueprint' ? '📜' : '📦'),
            description: raw.description || raw.template_description || '',
            quantity: quantity,
            max_stack: raw.max_stack != null ? raw.max_stack : null,
            stage: stage,
            stats: pickDisplayStats(raw.stats, raw.base_stats),
            recipe_slug: raw.recipe_slug || '',
            slot_type: raw.slot_type || '',
            locked: Boolean(raw.locked),
            slot_uuid: raw.slot_uuid || '',
        };
    }

    function escapeAttr(value) {
        return String(value != null ? value : '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function descriptorDataAttrs(descriptor) {
        const d = normalizeDescriptor(descriptor);
        return {
            'data-item-uuid': d.uuid || '',
            'data-name': d.name,
            'data-stage': d.stage || '',
            'data-template-slug': d.template_slug,
            'data-quantity': String(d.quantity),
            'data-description': d.description,
            'data-stats': JSON.stringify(d.stats || {}),
            'data-max-stack': d.max_stack != null ? String(d.max_stack) : '',
            'data-icon': d.icon,
            'data-recipe-slug': d.recipe_slug || '',
            'data-slot-type': d.slot_type || '',
            'data-locked': d.locked ? '1' : '0',
        };
    }

    function attrsToString(attrs) {
        return Object.keys(attrs).map(function (key) {
            return key + '="' + escapeAttr(attrs[key]) + '"';
        }).join(' ');
    }

    function readDescriptorFromElement(el) {
        var stats = {};
        try {
            stats = JSON.parse(el.dataset.stats || '{}');
        } catch (_) {
            stats = {};
        }

        return normalizeDescriptor({
            uuid: el.dataset.itemUuid || null,
            template_slug: el.dataset.templateSlug || '',
            name: el.dataset.name,
            icon: el.dataset.icon,
            description: el.dataset.description,
            quantity: el.dataset.quantity,
            max_stack: el.dataset.maxStack ? parseInt(el.dataset.maxStack, 10) : null,
            recipe_slug: el.dataset.recipeSlug || '',
            stage: el.dataset.stage,
            slot_type: el.dataset.slotType || '',
            locked: el.dataset.locked === '1',
            is_resource: !el.dataset.recipeSlug && Boolean(el.dataset.templateSlug) && el.dataset.stage === '',
        });
    }

    var STAT_LABELS = {
        damage: 'Урон',
        attack: 'Атака',
        defense: 'Защита',
        health: 'Здоровье',
        durability: 'Прочность',
        level: 'Уровень',
        weight: 'Вес',
        value: 'Ценность',
        strength: 'Сила',
        attack_speed: 'Скорость атаки',
        crit_chance: 'Крит',
    };

    function formatStatValue(value) {
        if (value === null || value === undefined) return '';
        if (typeof value === 'object') {
            if ('min' in value && 'max' in value) {
                return value.min + '–' + value.max;
            }
            return JSON.stringify(value);
        }
        return String(value);
    }

    function buildTooltipHtml(descriptor) {
        var d = normalizeDescriptor(descriptor);
        var stage = d.stage || '';
        var quantity = d.quantity || 1;

        var type = 'Предмет';
        if (stage === 'blueprint') type = 'Чертёж';
        else if (stage === 'item') type = 'Предмет';
        else if (quantity > 1 || stage === '') type = 'Ресурс';

        var html = '<div class="tooltip-header">' +
            '<div class="tooltip-icon">' + d.icon + '</div>' +
            '<div><div class="tooltip-name">' + d.name + '</div>' +
            '<div class="tooltip-type">' + type + '</div></div></div>';

        if (d.description) {
            html += '<div class="tooltip-description">' + d.description + '</div>';
        }

        var statEntries = Object.keys(d.stats || {}).filter(function (key) {
            var v = d.stats[key];
            return v != null && v !== '';
        });

        if (statEntries.length > 0) {
            html += '<div class="tooltip-stats">';
            statEntries.forEach(function (key) {
                var label = STAT_LABELS[key] || key;
                html += '<div class="tooltip-stat"><span class="tooltip-stat-label">' + label +
                    ':</span><span class="tooltip-stat-value">' + formatStatValue(d.stats[key]) + '</span></div>';
            });
            html += '</div>';
        }

        if (quantity > 1) {
            html += '<div class="tooltip-quantity">Количество: ' + quantity + '</div>';
        }

        return html;
    }

    var GameItemTooltip = {
        _el: null,
        init: function () {
            this._el = document.getElementById('itemTooltip');
        },
        show: function (e, descriptor) {
            if (!this._el) this.init();
            if (!this._el) return;
            this._el.innerHTML = buildTooltipHtml(descriptor);
            this._el.classList.add('visible');
            this.move(e);
        },
        showPlain: function (e, label, icon) {
            if (!this._el) this.init();
            if (!this._el) return;
            this._el.innerHTML = '<div class="tooltip-header">' +
                '<div class="tooltip-icon">' + (icon || 'ℹ️') + '</div>' +
                '<div><div class="tooltip-name">' + label + '</div></div></div>';
            this._el.classList.add('visible');
            this.move(e);
        },
        hide: function () {
            if (!this._el) this.init();
            if (this._el) this._el.classList.remove('visible');
        },
        move: function (e) {
            if (!this._el || !this._el.classList.contains('visible')) return;
            var offsetX = 15;
            var offsetY = 15;
            var x = e.clientX + offsetX;
            var y = e.clientY + offsetY;
            var rect = this._el.getBoundingClientRect();
            if (x + rect.width > window.innerWidth) x = e.clientX - rect.width - offsetX;
            if (y + rect.height > window.innerHeight) y = e.clientY - rect.height - offsetY;
            this._el.style.left = x + 'px';
            this._el.style.top = y + 'px';
        },
    };

    var GameItemPreview = {
        open: function (descriptor) {
            GameItemTooltip.hide();
            var win = document.getElementById('window-item-preview');
            if (!win) return;
            var d = normalizeDescriptor(descriptor);
            var titleEl = document.getElementById('itemPreviewTitle');
            var body = document.getElementById('itemPreviewBody');
            if (titleEl) titleEl.textContent = d.name;
            if (body) body.innerHTML = buildTooltipHtml(d);
            win.classList.add('active');
            if (window.WindowManager) {
                win.style.zIndex = ++WindowManager.zIndex;
                WindowManager.activeWindow = 'item-preview';
            }
            if (!win.style.left) {
                win.style.left = '50%';
                win.style.top = '50%';
                win.style.transform = 'translate(-50%, -50%)';
            }
        },
        close: function () {
            var win = document.getElementById('window-item-preview');
            if (win) win.classList.remove('active');
        },
    };

    var GameItemDetailModal = {
        open: function (descriptor) {
            GameItemPreview.open(descriptor);
        },
        close: function () {
            GameItemPreview.close();
        },
    };

    var templateCache = new Map();

    var GameItemPresenter = {
        templateCache: templateCache,
        loadTemplateCache: function () {
            var self = this;
            if (templateCache.size > 0) return Promise.resolve();
            if (!window.GameApi) return Promise.resolve();
            return window.GameApi.fetch('/api/templates')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    (data.templates || []).forEach(function (t) {
                        templateCache.set(t.slug, t);
                    });
                })
                .catch(function (e) {
                    console.warn('Template cache load failed', e);
                });
        },
        descriptorFromSlug: function (templateSlug, quantity) {
            quantity = quantity || 1;
            var t = templateCache.get(templateSlug);
            if (!t) {
                return normalizeDescriptor({ template_slug: templateSlug, name: templateSlug, quantity: quantity });
            }
            return normalizeDescriptor({
                template_slug: t.slug,
                name: t.name,
                icon: t.icon,
                description: t.description,
                max_stack: t.max_stack,
                base_stats: t.base_stats || {},
                stage: t.type === 'blueprint' ? 'blueprint' : (t.type === 'material' ? '' : 'item'),
                quantity: quantity,
            });
        },
        renderIcon: function (descriptor, extraClass) {
            extraClass = extraClass || '';
            var d = normalizeDescriptor(descriptor);
            var attrs = attrsToString(descriptorDataAttrs(d));
            var qty = d.quantity > 1 ? '<div class="item-qty">x' + d.quantity + '</div>' : '';
            var lockedClass = d.locked ? ' storage-slot-item--locked' : '';
            return '<div class="item game-item-interactive ' + extraClass + lockedClass + '" ' + attrs + '>' +
                '<div class="item-icon">' + d.icon + '</div>' + qty + '</div>';
        },
        renderLink: function (descriptor, extraClass) {
            extraClass = extraClass || '';
            var d = normalizeDescriptor(descriptor);
            var attrs = attrsToString(descriptorDataAttrs(d));
            var qtySuffix = d.quantity > 1 ? ' ×' + d.quantity : '';
            return '<span class="game-item-link game-item-interactive ' + extraClass + '" ' + attrs + '>' +
                d.name + qtySuffix + '</span>';
        },
        applyItemInteractions: function (container) {
            if (!container) return;
            var self = this;
            container.querySelectorAll('.game-item-interactive').forEach(function (el) {
                if (el.dataset.itemBound === '1') return;
                el.dataset.itemBound = '1';
                el.addEventListener('mouseenter', function (e) {
                    GameItemTooltip.show(e, readDescriptorFromElement(el));
                });
                el.addEventListener('mouseleave', function () { GameItemTooltip.hide(); });
                el.addEventListener('mousemove', function (e) { GameItemTooltip.move(e); });
                el.addEventListener('contextmenu', function () { GameItemTooltip.hide(); });
                el.addEventListener('click', function (e) {
                    if (e.detail !== 1) return;
                    clearTimeout(el._itemClickTimer);
                    el._itemClickTimer = setTimeout(function () {
                        GameItemPreview.open(readDescriptorFromElement(el));
                    }, 250);
                });
                el.addEventListener('dblclick', function () {
                    clearTimeout(el._itemClickTimer);
                    GameItemTooltip.hide();
                });
            });
        },
    };

    if (!document.getElementById('game-item-ui-styles')) {
        var style = document.createElement('style');
        style.id = 'game-item-ui-styles';
        style.textContent = '.game-item-link{color:#a5b4fc;cursor:pointer;text-decoration:underline;text-decoration-style:dotted}' +
            '.game-item-link:hover{color:#c4b5fd}';
        document.head.appendChild(style);
    }

    window.GameItemPresenter = GameItemPresenter;
    window.GameItemTooltip = GameItemTooltip;
    window.GameItemPreview = GameItemPreview;
    window.GameItemDetailModal = GameItemDetailModal;
    window.normalizeItemDescriptor = normalizeDescriptor;
    window.readItemDescriptorFromElement = readDescriptorFromElement;
    window.bindItemTooltips = function (container) {
        GameItemPresenter.applyItemInteractions(container);
    };
    window.hideItemTooltip = function () { GameItemTooltip.hide(); };
    window.moveItemTooltip = function (e) { GameItemTooltip.move(e); };
})();

/**
 * Unified storage UI: StorageGrid, DragEngine, StorageManager
 */
(function () {
    'use strict';

    function slotOccupant(slot) {
        return slot.item || slot.resource || null;
    }

    function occupantDescriptor(slot) {
        var occ = slotOccupant(slot);
        if (!occ) return null;
        return occ;
    }

    function isOccupantLocked(slot) {
        var occ = slotOccupant(slot);
        return Boolean(occ && occ.locked);
    }

    function storageSlotClasses(slot, options) {
        options = options || {};
        var occ = occupantDescriptor(slot);
        var locked = isOccupantLocked(slot);
        var emptyClass = occ ? '' : ' storage-slot--empty';
        var lockedClass = locked ? ' storage-slot--locked' : '';
        var readonlyClass = options.readonly ? ' storage-slot--readonly' : '';
        var dragClass = options.draggable && !locked && !options.readonly ? ' storage-slot--draggable' : '';
        return emptyClass + lockedClass + readonlyClass + dragClass;
    }

    var SpecialSlotsBar = {
        render: function (storageData, options) {
            options = options || {};
            var slots = (storageData.special_slots || []).filter(function (slot) {
                return !slot.hidden;
            });
            if (!slots.length) return '';

            var html = '<div class="special-slots-bar">';
            slots.forEach(function (slot) {
                var occ = occupantDescriptor(slot);
                var classes = storageSlotClasses(slot, { draggable: options.draggable !== false });
                html += '<div class="storage-slot special-slot' + classes + '" ' +
                    'data-slot-uuid="' + slot.uuid + '" ' +
                    'data-slot-kind="regular" data-readonly="0">';
                if (occ && window.GameItemPresenter) {
                    html += GameItemPresenter.renderIcon(occ, 'storage-slot-item');
                }
                html += '</div>';
            });
            html += '</div>';
            return html;
        }
    };

    var GoldChip = {
        render: function (storageData, goldAmount) {
            var goldSlot = (storageData.special_slots || []).find(function (s) {
                return s.slot_type === 'gold';
            });
            if (!goldSlot) {
                return '<div class="gold" id="playerGold">💰 ' + (goldAmount || 0) + '</div>';
            }
            var occ = goldSlot.resource;
            var dragClass = occ ? ' storage-slot--draggable gold-chip--draggable' : '';
            var inner = '';
            if (occ && window.GameItemPresenter) {
                inner = GameItemPresenter.renderIcon(occ, 'storage-slot-item gold-chip-item');
            }
            return '<div class="gold gold-chip' + dragClass + '" id="playerGold" ' +
                'data-slot-uuid="' + goldSlot.uuid + '" data-slot-kind="regular" data-readonly="0">' +
                '💰 ' + (goldAmount != null ? goldAmount : (occ ? occ.quantity : 0)) +
                inner + '</div>';
        },

        mount: function (container, storageData, goldAmount) {
            if (!container) return;
            container.outerHTML = this.render(storageData, goldAmount);
        }
    };

    function getSlotSize() {
        if (window.GameSettings && typeof window.GameSettings.getSlotSize === 'function') {
            return window.GameSettings.getSlotSize();
        }
        return parseInt(getComputedStyle(document.documentElement).getPropertyValue('--slot-size'), 10) || 44;
    }

    function getSlotGap() {
        return 5;
    }

    var StorageGrid = {
        render: function (storageData, options) {
            options = options || {};
            var cols = storageData.cols || 4;
            var draggable = options.draggable !== false;
            var readonly = options.readonly === true;
            var gridId = options.gridId || ('storage-grid-' + (storageData.uuid || 'trade'));
            var slotList = storageData.grid_slots || storageData.slots || [];
            var cellSize = options.compact !== false ? 'var(--slot-size,44px)' : '1fr';

            var html = '';
            if (options.showSpecialBar !== false && storageData.storage_type === 'inventory') {
                html += SpecialSlotsBar.render(storageData, options);
            }
            html += '<div class="storage-grid" id="' + gridId + '" data-storage-uuid="' + (storageData.uuid || '') + '" data-storage-type="' + (storageData.storage_type || '') + '" style="grid-template-columns:repeat(' + cols + ',' + cellSize + ');gap:' + getSlotGap() + 'px">';

            slotList.forEach(function (slot) {
                var occ = occupantDescriptor(slot);
                var classes = storageSlotClasses(slot, { draggable: draggable, readonly: readonly });

                html += '<div class="storage-slot' + classes + '" ' +
                    'data-slot-uuid="' + slot.uuid + '" ' +
                    'data-slot-kind="' + (slot.kind || 'regular') + '" ' +
                    'data-readonly="' + (readonly ? '1' : '0') + '">';

                if (occ && window.GameItemPresenter) {
                    html += GameItemPresenter.renderIcon(occ, 'storage-slot-item');
                }

                html += '</div>';
            });

            html += '</div>';
            return html;
        },

        mount: function (container, storageData, options) {
            if (!container) return null;
            container.innerHTML = this.render(storageData, options);
            if (window.bindItemTooltips) {
                window.bindItemTooltips(container);
            }
            if (window.DragEngine) {
                DragEngine.registerGrid(container);
            }
            return container.querySelector('.storage-grid');
        }
    };

    var StorageManager = {
        characterUuid: null,
        layout: null,
        inventoryStorage: null,
        equipmentStorage: null,
        characterStats: null,
        myTradeSlots: null,
        partnerTradeSlots: null,

        load: function (characterUuid, include) {
            var self = this;
            include = include || 'inventory';
            self.characterUuid = characterUuid;

            if (!window.GameApi) {
                return Promise.reject(new Error('GameApi unavailable'));
            }

            return window.GameApi.fetch('/api/storage/' + characterUuid + '?include=' + include)
                .then(function (res) {
                    if (!res.ok) {
                        return res.json().then(function (err) {
                            throw new Error(err.error || 'Ошибка загрузки хранилища');
                        });
                    }
                    return res.json();
                })
                .then(function (data) {
                    self.layout = data;
                    self.inventoryStorage = (data.storages || []).find(function (s) {
                        return s.storage_type === 'inventory';
                    }) || null;
                    self.equipmentStorage = (data.storages || []).find(function (s) {
                        return s.storage_type === 'equipment';
                    }) || null;
                    self.characterStats = data.character_stats || null;
                    self.myTradeSlots = data.my_trade_slots || null;
                    self.partnerTradeSlots = data.partner_trade_slots || null;
                    self.syncGameStateInventory();
                    return data;
                });
        },

        syncGameStateInventory: function () {
            if (!this.inventoryStorage || !window.GameState) return;
            var items = [];
            var allSlots = (this.inventoryStorage.grid_slots || this.inventoryStorage.slots || [])
                .concat(this.inventoryStorage.special_slots || []);
            allSlots.forEach(function (slot) {
                if (slot.resource) items.push(slot.resource);
                if (slot.item) items.push(slot.item);
            });
            window.GameState.inventory = items;
        },

        move: function (fromSlotUuid, toSlotUuid, quantity) {
            var self = this;
            var body = {
                from_slot_uuid: fromSlotUuid,
                to_slot_uuid: toSlotUuid
            };
            if (quantity != null) body.quantity = quantity;

            return window.GameApi.fetch('/api/storage/' + self.characterUuid + '/move', {
                method: 'POST',
                body: JSON.stringify(body)
            }).then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) {
                        throw new Error(data.error || 'Ошибка перемещения');
                    }
                    if (data.layout) {
                        self.layout = data.layout;
                        self.inventoryStorage = (data.layout.storages || []).find(function (s) {
                            return s.storage_type === 'inventory';
                        }) || self.inventoryStorage;
                        self.myTradeSlots = data.layout.my_trade_slots || self.myTradeSlots;
                        self.partnerTradeSlots = data.layout.partner_trade_slots || self.partnerTradeSlots;
                        self.syncGameStateInventory();
                if (data.layout.gold != null) {
                    var goldEl = document.getElementById('playerGold');
                    if (goldEl && window.GoldChip && StorageManager.inventoryStorage) {
                        GoldChip.mount(goldEl, StorageManager.inventoryStorage, data.layout.gold);
                    } else if (goldEl) {
                        goldEl.textContent = '💰 ' + data.layout.gold;
                    }
                }
            }
                    return data;
                });
            });
        },

        getGold: function () {
            if (this.layout && this.layout.gold != null) {
                return this.layout.gold;
            }
            if (!this.inventoryStorage) return 0;
            var gold = 0;
            (this.inventoryStorage.slots || []).forEach(function (slot) {
                if (slot.resource && slot.resource.template_slug === 'gold') {
                    gold += slot.resource.quantity || 0;
                }
            });
            return gold;
        }
    };

    var DRAG_THRESHOLD = 6;

    var DragEngine = {
        active: null,
        ghost: null,

        init: function () {
            var self = this;
            document.addEventListener('pointermove', function (e) { self.onPointerMove(e); });
            document.addEventListener('pointerup', function (e) { self.onPointerUp(e); });
            document.addEventListener('pointerdown', function (e) {
                var slot = e.target.closest('.storage-slot--draggable, .gold-chip--draggable');
                if (!slot || slot.dataset.readonly === '1') return;
                var occ = slot.querySelector('.game-item-interactive');
                if (!occ && !slot.classList.contains('gold-chip--draggable')) return;
                self.onPointerDown(e, slot, occ || slot);
            });
        },

        registerGrid: function (container) {
            // Grids use delegated pointerdown on document
        },

        onPointerDown: function (e, slot, occEl) {
            if (e.button !== 0) return;
            if (occEl && (occEl.dataset.locked === '1' || occEl.classList.contains('storage-slot-item--locked'))) {
                return;
            }
            if (slot && slot.classList.contains('storage-slot--locked')) {
                return;
            }
            var descriptor = {};
            if (occEl && occEl.classList && occEl.classList.contains('game-item-interactive') && window.normalizeItemDescriptor) {
                descriptor = window.normalizeItemDescriptor({
                    uuid: occEl.dataset.itemUuid,
                    name: occEl.dataset.name,
                    stage: occEl.dataset.stage,
                    template_slug: occEl.dataset.templateSlug,
                    recipe_slug: occEl.dataset.recipeSlug,
                    quantity: occEl.dataset.quantity,
                    max_stack: occEl.dataset.maxStack,
                    icon: occEl.dataset.icon
                });
            } else if (slot.classList.contains('gold-chip--draggable')) {
                var goldQty = parseInt((document.getElementById('playerGold') || {}).textContent.replace(/\D/g, ''), 10) || 0;
                descriptor = {
                    uuid: '',
                    name: 'Золото',
                    template_slug: 'gold',
                    quantity: goldQty,
                    max_stack: null,
                    icon: '💰'
                };
                var goldItem = slot.querySelector('.game-item-interactive');
                if (goldItem && goldItem.dataset.itemUuid) {
                    descriptor.uuid = goldItem.dataset.itemUuid;
                    descriptor.quantity = parseInt(goldItem.dataset.quantity, 10) || goldQty;
                }
            }

            this.active = {
                fromSlotUuid: slot.dataset.slotUuid,
                fromSlot: slot,
                descriptor: descriptor,
                occEl: occEl,
                startX: e.clientX,
                startY: e.clientY,
                dragging: false,
                pointerId: e.pointerId
            };
        },

        beginDrag: function (e) {
            if (!this.active || this.active.dragging) return;
            this.active.dragging = true;

            var occEl = this.active.occEl;
            var descriptor = this.active.descriptor;

            this.ghost = document.createElement('div');
            this.ghost.className = 'storage-drag-ghost';
            if (occEl && occEl.classList && occEl.classList.contains('game-item-interactive')) {
                this.ghost.innerHTML = occEl.innerHTML;
            } else {
                this.ghost.textContent = '💰 x' + (descriptor.quantity || 0);
            }
            this.ghost.style.left = e.clientX + 'px';
            this.ghost.style.top = e.clientY + 'px';
            document.body.appendChild(this.ghost);

            if (this.active.fromSlot.setPointerCapture) {
                try { this.active.fromSlot.setPointerCapture(e.pointerId); } catch (_) {}
            }
        },

        onPointerMove: function (e) {
            if (!this.active) return;

            if (!this.active.dragging) {
                var dx = e.clientX - this.active.startX;
                var dy = e.clientY - this.active.startY;
                if ((dx * dx + dy * dy) < DRAG_THRESHOLD * DRAG_THRESHOLD) {
                    return;
                }
                this.beginDrag(e);
            }

            if (!this.ghost) return;

            this.ghost.style.left = (e.clientX + 8) + 'px';
            this.ghost.style.top = (e.clientY + 8) + 'px';

            document.querySelectorAll('.storage-slot--drag-over').forEach(function (el) {
                el.classList.remove('storage-slot--drag-over');
            });

            var target = document.elementFromPoint(e.clientX, e.clientY);
            if (!target) return;
            var dropSlot = target.closest('.storage-slot, .gold-chip--draggable');
            if (dropSlot && dropSlot.dataset.readonly !== '1' && dropSlot !== this.active.fromSlot) {
                dropSlot.classList.add('storage-slot--drag-over');
            }
        },

        onPointerUp: function (e) {
            if (!this.active) return;

            var wasDragging = this.active.dragging;
            var target = document.elementFromPoint(e.clientX, e.clientY);
            var dropSlot = target ? target.closest('.storage-slot, .gold-chip--draggable') : null;
            var fromUuid = this.active.fromSlotUuid;
            var descriptor = this.active.descriptor;

            if (this.ghost) {
                this.ghost.remove();
                this.ghost = null;
            }

            document.querySelectorAll('.storage-slot--drag-over').forEach(function (el) {
                el.classList.remove('storage-slot--drag-over');
            });

            if (wasDragging && dropSlot && dropSlot.dataset.readonly !== '1'
                && !dropSlot.classList.contains('storage-slot--readonly')
                && dropSlot.dataset.slotUuid !== fromUuid) {
                var toUuid = dropSlot.dataset.slotUuid;
                var isResource = descriptor.template_slug && !descriptor.stage;
                var canSplit = isResource && descriptor.quantity > 1 && e.shiftKey && window.ResourceQuantityModal;

                if (canSplit) {
                    window.ResourceQuantityModal.open({
                        name: descriptor.name,
                        icon: descriptor.icon,
                        available: descriptor.quantity,
                        maxStack: descriptor.max_stack,
                        confirmLabel: 'Перенести',
                        defaultToMax: false,
                        onConfirm: function (q) {
                            StorageManager.move(fromUuid, toUuid, q).then(function () {
                                if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                                if (typeof loadPlayerData === 'function') loadPlayerData();
                            }).catch(function (err) {
                                if (typeof showMsg === 'function') showMsg(err.message, 'error');
                            });
                        }
                    });
                } else {
                    StorageManager.move(fromUuid, toUuid, null).then(function () {
                        if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                        if (typeof loadPlayerData === 'function') loadPlayerData();
                    }).catch(function (err) {
                        if (typeof showMsg === 'function') showMsg(err.message, 'error');
                    });
                }
            }

            this.active = null;
        }
    };

    if (!document.getElementById('storage-grid-styles')) {
        var sgStyle = document.createElement('style');
        sgStyle.id = 'storage-grid-styles';
        sgStyle.textContent =
            '.storage-grid{display:grid;width:max-content;max-width:100%}' +
            '.storage-slot{width:var(--slot-size,44px);height:var(--slot-size,44px);min-height:unset;aspect-ratio:unset;background:rgba(0,0,0,0.25);border:2px solid rgba(255,255,255,0.12);border-radius:6px;position:relative;display:flex;align-items:center;justify-content:center;user-select:none;touch-action:none;box-sizing:border-box;flex-shrink:0}' +
            '.storage-slot--empty{border-style:dashed;border-color:rgba(255,255,255,0.18);background:rgba(255,255,255,0.03);opacity:1}' +
            '.storage-slot--draggable{cursor:grab}' +
            '.storage-slot--readonly{opacity:0.85}' +
            '.storage-slot--drag-over{border-color:#667eea;background:rgba(102,126,234,0.15)}' +
            '.storage-slot--locked{border-color:rgba(255,255,255,0.08)}' +
            '.storage-slot-item--locked{opacity:0.45;filter:grayscale(1);pointer-events:none}' +
            '.storage-slot .item{width:100%;height:100%;border:none;background:transparent}' +
            '.storage-drag-ghost{position:fixed;z-index:10002;pointer-events:none;opacity:0.85;transform:translate(-50%,-50%);padding:8px;background:rgba(30,30,46,0.95);border:2px solid #667eea;border-radius:8px}' +
            '.special-slots-bar{display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap}' +
            '.special-slots-bar .special-slot{min-width:52px;max-width:64px;flex:0 0 auto}' +
            '.gold-chip{position:relative;cursor:default;user-select:none;touch-action:none}' +
            '.gold-chip--draggable{cursor:grab}' +
            '.gold-chip-item{position:absolute;inset:0;opacity:0;pointer-events:none}';
        document.head.appendChild(sgStyle);
    }

    var PLAY_PANEL_ACTIONS = {
        journal: { window: 'journal', icon: '💬', label: 'Чат' },
        inventory: { window: 'inventory', icon: '🎒', label: 'Инвентарь' },
        character: { window: 'character', icon: '🛡️', label: 'Персонаж' },
        auction: { window: 'auction', icon: '🏪', label: 'Аукцион' },
        players: { window: 'players', icon: '👥', label: 'Игроки' },
        settings: { window: 'settings', icon: '⚙️', label: 'Настройки' },
    };

    var WindowResizer = {
        handlers: {},
        register: function (name, fn) {
            this.handlers[name] = fn;
        },
        resizeAll: function () {
            var self = this;
            Object.keys(self.handlers).forEach(function (name) {
                try { self.handlers[name](); } catch (e) { console.warn('WindowResizer', name, e); }
            });
        },
    };

    var PlayPanelManager = {
        characterUuid: null,
        cols: 12,
        slots: [],
        layout: {},
        _saveTimer: null,
        _drag: null,

        getHeight: function () {
            var slot = (window.GameSettings && GameSettings.getSlotSize()) || 44;
            return slot + 16;
        },

        updateCanvasInset: function () {
            var h = this.getHeight();
            var canvas = document.getElementById('gameCanvas');
            if (canvas) canvas.style.bottom = h + 'px';
            document.querySelectorAll('#window-journal, #window-inventory, #window-trade, #window-players').forEach(function (el) {
                el.style.maxHeight = 'calc(100% - ' + Math.max(8, h - 60) + 'px)';
            });
        },

        resize: function () {
            var slotSize = (window.GameSettings && GameSettings.getSlotSize()) || 44;
            var grid = document.getElementById('playPanelGrid');
            if (grid) {
                grid.style.gridTemplateColumns = 'repeat(' + this.cols + ',' + slotSize + 'px)';
            }
            this.updateCanvasInset();
        },

        load: function (characterUuid) {
            var self = this;
            self.characterUuid = characterUuid;
            self.renderSkeleton();
            return GameApi.fetch('/api/play-panel/' + characterUuid)
                .then(function (r) {
                    if (!r.ok) throw new Error('play-panel ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    if (!data.panel) {
                        self.renderSkeleton();
                        return;
                    }
                    self.cols = data.panel.cols || 12;
                    self.slots = (data.panel.slots || []).map(function (slot) {
                        return { uuid: slot.uuid };
                    });
                    self.layout = self.normalizeLayout(data.panel.layout || {}, data.panel.slots || []);
                    self.render();
                })
                .catch(function (e) {
                    console.error('PlayPanel load error:', e);
                    self.renderSkeleton();
                });
        },

        renderSkeleton: function () {
            var defaults = ['journal', 'inventory', 'character', 'auction', 'players', 'settings'];
            this.cols = 12;
            this.slots = [];
            this.layout = {};
            for (var i = 0; i < 12; i++) {
                var uuid = 'skeleton-' + i;
                this.slots.push({ uuid: uuid });
                if (defaults[i]) this.layout[uuid] = defaults[i];
            }
            this.render();
        },

        normalizeLayout: function (layout, slots) {
            var result = {};
            var used = {};
            var self = this;

            function mapAction(action) {
                if (action === 'trade') return 'players';
                if (action === 'workbench') return null;
                return PLAY_PANEL_ACTIONS[action] ? action : null;
            }

            Object.keys(layout || {}).forEach(function (slotUuid) {
                var action = mapAction(layout[slotUuid]);
                if (action && !used[action]) {
                    result[slotUuid] = action;
                    used[action] = true;
                }
            });

            (slots || []).forEach(function (slot) {
                if (result[slot.uuid] || !slot.action) return;
                var action = mapAction(slot.action);
                if (action && !used[action]) {
                    result[slot.uuid] = action;
                    used[action] = true;
                }
            });

            if (!used.players) {
                var target = self.slots.find(function (s) { return !result[s.uuid]; });
                if (target) {
                    result[target.uuid] = 'players';
                }
            }

            if (!used.character) {
                var charTarget = self.slots.find(function (s) { return !result[s.uuid]; });
                if (charTarget) {
                    result[charTarget.uuid] = 'character';
                }
            }

            return result;
        },

        render: function () {
            var container = document.getElementById('playPanel');
            if (!container) return;

            var slotSize = (window.GameSettings && GameSettings.getSlotSize()) || 44;
            var html = '<div class="play-panel-grid" id="playPanelGrid" style="grid-template-columns:repeat(' + this.cols + ',' + slotSize + 'px)">';

            this.slots.forEach(function (slot) {
                var action = PlayPanelManager.layout[slot.uuid] || null;
                html += '<div class="storage-slot storage-slot--empty play-panel-slot" data-slot-uuid="' + slot.uuid + '">';
                if (action && PLAY_PANEL_ACTIONS[action]) {
                    var meta = PLAY_PANEL_ACTIONS[action];
                    var active = window.WindowManager && WindowManager.isOpen(meta.window);
                    html += '<button type="button" class="play-panel-chip' + (active ? ' active' : '') + '" ' +
                        'data-action="' + action + '" data-window="' + meta.window + '" ' +
                        'data-label="' + meta.label + '" data-icon="' + meta.icon + '">' +
                        '<span class="pp-icon">' + meta.icon + '</span></button>';
                }
                html += '</div>';
            });

            html += '</div>';
            container.innerHTML = html;
            this.bindEvents();
            this.resize();
            if (window.WindowManager) WindowManager.updateToolbar();
        },

        bindEvents: function () {
            var self = this;
            var grid = document.getElementById('playPanelGrid');
            if (!grid) return;

            grid.querySelectorAll('.play-panel-chip').forEach(function (chip) {
                chip.addEventListener('click', function (e) {
                    if (self._drag && self._drag.moved) return;
                    GameItemTooltip.hide();
                    var win = chip.dataset.window;
                    if (win && window.WindowManager) WindowManager.toggle(win);
                });

                chip.addEventListener('mouseenter', function (e) {
                    GameItemTooltip.showPlain(e, chip.dataset.label || '', chip.dataset.icon || '');
                });
                chip.addEventListener('mouseleave', function () { GameItemTooltip.hide(); });
                chip.addEventListener('mousemove', function (e) { GameItemTooltip.move(e); });

                chip.addEventListener('pointerdown', function (e) {
                    if (e.button !== 0) return;
                    self._drag = {
                        action: chip.dataset.action,
                        fromSlotUuid: chip.closest('.play-panel-slot').dataset.slotUuid,
                        startX: e.clientX,
                        startY: e.clientY,
                        moved: false,
                        ghost: null,
                    };
                    chip.setPointerCapture(e.pointerId);
                });

                chip.addEventListener('pointermove', function (e) {
                    if (!self._drag || self._drag.action !== chip.dataset.action) return;
                    var dx = e.clientX - self._drag.startX;
                    var dy = e.clientY - self._drag.startY;
                    if (!self._drag.moved && (dx * dx + dy * dy) < 36) return;
                    self._drag.moved = true;
                    if (!self._drag.ghost) {
                        self._drag.ghost = document.createElement('div');
                        self._drag.ghost.className = 'play-panel-drag-ghost';
                        self._drag.ghost.textContent = chip.dataset.label || (chip.querySelector('.pp-icon') && chip.querySelector('.pp-icon').textContent) || '';
                        document.body.appendChild(self._drag.ghost);
                    }
                    self._drag.ghost.style.left = (e.clientX + 8) + 'px';
                    self._drag.ghost.style.top = (e.clientY + 8) + 'px';
                    grid.querySelectorAll('.play-panel-slot--drag-over').forEach(function (el) {
                        el.classList.remove('play-panel-slot--drag-over');
                    });
                    var target = document.elementFromPoint(e.clientX, e.clientY);
                    var slotEl = target ? target.closest('.play-panel-slot') : null;
                    if (slotEl) slotEl.classList.add('play-panel-slot--drag-over');
                });

                chip.addEventListener('pointerup', function (e) {
                    if (!self._drag || self._drag.action !== chip.dataset.action) return;
                    var drag = self._drag;
                    self._drag = null;
                    if (drag.ghost) { drag.ghost.remove(); }
                    grid.querySelectorAll('.play-panel-slot--drag-over').forEach(function (el) {
                        el.classList.remove('play-panel-slot--drag-over');
                    });
                    if (!drag.moved) return;
                    var target = document.elementFromPoint(e.clientX, e.clientY);
                    var toSlot = target ? target.closest('.play-panel-slot') : null;
                    if (!toSlot) return;
                    var toUuid = toSlot.dataset.slotUuid;
                    if (!toUuid || toUuid === drag.fromSlotUuid) return;
                    self.moveAction(drag.fromSlotUuid, toUuid);
                });
            });
        },

        moveAction: function (fromUuid, toUuid) {
            var fromAction = this.layout[fromUuid];
            var toAction = this.layout[toUuid] || null;
            if (!fromAction) return;

            if (toAction) {
                this.layout[fromUuid] = toAction;
                this.layout[toUuid] = fromAction;
            } else {
                delete this.layout[fromUuid];
                this.layout[toUuid] = fromAction;
            }
            this.render();
            this.saveLayout();
        },

        saveLayout: function () {
            var self = this;
            if (!self.characterUuid) return;
            var layout = {};
            Object.keys(self.layout).forEach(function (slotUuid) {
                if (slotUuid.indexOf('skeleton-') === 0) return;
                layout[slotUuid] = self.layout[slotUuid];
            });
            clearTimeout(self._saveTimer);
            self._saveTimer = setTimeout(function () {
                GameApi.fetch('/api/settings/' + self.characterUuid + '/multiple', {
                    method: 'POST',
                    body: JSON.stringify({ settings: { play_panel_layout: layout } }),
                }).catch(function (e) { console.error('PlayPanel save error:', e); });
            }, 300);
        },

        refreshActiveState: function () {
            var grid = document.getElementById('playPanelGrid');
            if (!grid) return;
            grid.querySelectorAll('.play-panel-chip').forEach(function (chip) {
                var win = chip.dataset.window;
                chip.classList.toggle('active', window.WindowManager && WindowManager.isOpen(win));
            });
        },
    };

    window.WindowResizer = WindowResizer;
    window.PlayPanelManager = PlayPanelManager;
    window.PLAY_PANEL_ACTIONS = PLAY_PANEL_ACTIONS;

    var StorageQuickActions = {
        ensureStorages: function () {
            if (!window.StorageManager || !window.GameState) {
                return Promise.reject(new Error('StorageManager unavailable'));
            }
            return StorageManager.load(GameState.characterUuid, 'inventory,equipment,stats');
        },

        isEquippable: function (item) {
            var slotType = item && item.slot_type ? String(item.slot_type) : '';
            return slotType.indexOf('equipment_') === 0;
        },

        findEquipSlot: function (item) {
            var storage = StorageManager.equipmentStorage;
            if (!storage || !item || !item.slot_type) return null;
            var slots = storage.special_slots || storage.slots || [];
            var targetType = item.slot_type;
            if (targetType === 'equipment_ring') {
                var ringSlots = slots.filter(function (s) { return s.slot_type === 'equipment_ring'; });
                var emptyRing = ringSlots.find(function (s) { return !slotOccupant(s); });
                return emptyRing || ringSlots[0] || null;
            }
            return slots.find(function (s) { return s.slot_type === targetType; }) || null;
        },

        findEmptyInventorySlot: function () {
            var storage = StorageManager.inventoryStorage;
            if (!storage) return null;
            var slots = storage.grid_slots || storage.slots || [];
            return slots.find(function (s) {
                return !s.hidden && (s.slot_type == null || s.slot_type === '') && !slotOccupant(s);
            }) || null;
        },

        equipItem: function (item, fromSlotUuid) {
            var self = this;
            if (!item || item.locked) return Promise.resolve();
            return this.ensureStorages().then(function () {
                if (!item.slot_type && StorageManager.inventoryStorage) {
                    var invSlots = StorageManager.inventoryStorage.grid_slots || StorageManager.inventoryStorage.slots || [];
                    invSlots.forEach(function (s) {
                        var occ = s.item || s.resource;
                        if (occ && occ.uuid === item.uuid) {
                            if (occ.slot_type) item.slot_type = occ.slot_type;
                            if (occ.slot_uuid) item.slot_uuid = occ.slot_uuid;
                        }
                    });
                }
                var toSlot = self.findEquipSlot(item);
                if (!toSlot) {
                    if (typeof showMsg === 'function') showMsg('Нет подходящего слота экипировки', 'error');
                    return;
                }
                var fromUuid = fromSlotUuid || item.slot_uuid;
                if (!fromUuid) return;
                return StorageManager.move(fromUuid, toSlot.uuid).then(function () {
                    if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                    if (typeof loadPlayerData === 'function') loadPlayerData();
                }).catch(function (err) {
                    if (typeof showMsg === 'function') showMsg(err.message, 'error');
                });
            });
        },

        unequipFromSlot: function (fromSlotUuid) {
            var self = this;
            return this.ensureStorages().then(function () {
                var toSlot = self.findEmptyInventorySlot();
                if (!toSlot) {
                    if (typeof showMsg === 'function') showMsg('Нет свободного места в инвентаре', 'error');
                    return;
                }
                return StorageManager.move(fromSlotUuid, toSlot.uuid).then(function () {
                    if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                    if (typeof loadPlayerData === 'function') loadPlayerData();
                }).catch(function (err) {
                    if (typeof showMsg === 'function') showMsg(err.message, 'error');
                });
            });
        },
    };

    window.StorageQuickActions = StorageQuickActions;
    window.StorageGrid = StorageGrid;
    window.SpecialSlotsBar = SpecialSlotsBar;
    window.GoldChip = GoldChip;
    window.StorageManager = StorageManager;
    window.DragEngine = DragEngine;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { DragEngine.init(); });
    } else {
        DragEngine.init();
    }
})();
