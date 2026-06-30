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
            } else if (raw.template_slug && !raw.recipe_slug
                && !(raw.slot_type && String(raw.slot_type).indexOf('equipment_') === 0)) {
                stage = '';
            } else {
                stage = 'item';
            }
        }

        const quantity = parseInt(raw.quantity, 10) || 1;
        const isResource = stage === '' && Boolean(raw.template_slug) && !raw.recipe_slug;

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
            quest_slug: raw.quest_slug || '',
            is_resource: isResource,
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
            'data-quest-slug': d.quest_slug || '',
            'data-locked': d.locked ? '1' : '0',
            'data-is-resource': d.is_resource ? '1' : '0',
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
            quest_slug: el.dataset.questSlug || '',
            locked: el.dataset.locked === '1',
            is_resource: el.dataset.isResource === '1'
                || (!el.dataset.recipeSlug && Boolean(el.dataset.templateSlug)
                    && el.dataset.stage !== 'blueprint' && el.dataset.stage !== 'item'),
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

        var materialsUsed = d.materials_used;
        if (materialsUsed && typeof materialsUsed === 'object') {
            var crafter = materialsUsed.crafter;
            if (crafter && crafter.character_name) {
                html += '<div class="tooltip-crafter">Создал: ' + crafter.character_name + '</div>';
            }
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
            var questBadge = d.quest_slug ? '<span class="quest-badge">!</span>' : '';
            return '<div class="item game-item-interactive ' + extraClass + lockedClass + '" ' + attrs + '>' +
                '<div class="item-icon">' + d.icon + questBadge + '</div>' + qty + '</div>';
        },
        renderLink: function (descriptor, extraClass) {
            extraClass = extraClass || '';
            var d = normalizeDescriptor(descriptor);
            var attrs = attrsToString(descriptorDataAttrs(d));
            var qtySuffix = d.quantity > 1 ? ' ×' + d.quantity : '';
            return '<span class="game-item-link game-item-interactive ' + extraClass + '" ' + attrs + '>' +
                d.name + qtySuffix + '</span>';
        },
        applyItemInteractions: function () {
            initGlobalItemInteractions();
        },
    };

    var _tooltipTarget = null;
    var _clickTimer = null;
    var _clickTarget = null;

    function initGlobalItemInteractions() {
        if (window._globalItemInteractionsBound) return;
        window._globalItemInteractionsBound = true;

        document.addEventListener('mouseover', function (e) {
            var el = e.target.closest('.game-item-interactive');
            if (!el) {
                if (_tooltipTarget) {
                    GameItemTooltip.hide();
                    _tooltipTarget = null;
                }
                return;
            }
            if (el === _tooltipTarget) return;
            _tooltipTarget = el;
            GameItemTooltip.show(e, readDescriptorFromElement(el));
        });

        document.addEventListener('mousemove', function (e) {
            if (_tooltipTarget) GameItemTooltip.move(e);
        });

        document.addEventListener('mouseout', function (e) {
            if (!e.target.closest('.game-item-interactive')) return;
            var el = e.target.closest('.game-item-interactive');
            var related = e.relatedTarget;
            if (related && el && el.contains(related)) return;
            if (_tooltipTarget === el) {
                GameItemTooltip.hide();
                _tooltipTarget = null;
            }
        });

        document.addEventListener('click', function (e) {
            var el = e.target.closest('.game-item-interactive');
            if (!el) return;
            if (e.detail !== 1) return;
            if (_clickTarget !== el) {
                _clickTarget = el;
                clearTimeout(_clickTimer);
                _clickTimer = setTimeout(function () {
                    GameItemPreview.open(readDescriptorFromElement(el));
                    _clickTarget = null;
                }, 250);
            }
        });

        document.addEventListener('dblclick', function (e) {
            var el = e.target.closest('.game-item-interactive');
            if (!el) return;
            clearTimeout(_clickTimer);
            _clickTarget = null;
            GameItemTooltip.hide();
            _tooltipTarget = null;
            GameItemPreview.close();
        });

        document.addEventListener('contextmenu', function (e) {
            if (e.target.closest('.game-item-interactive')) {
                GameItemTooltip.hide();
                _tooltipTarget = null;
            }
        });
    }

    function initGlobalItemUiDismiss() {
        if (window._itemUiDismissBound) return;
        window._itemUiDismissBound = true;

        document.addEventListener('mousedown', function (e) {
            if (e.target.closest('#itemTooltip')) return;
            if (e.target.closest('#window-item-preview')) return;
            if (e.target.closest('#window-confirm')) return;

            GameItemTooltip.hide();

            if (!e.target.closest('.game-item-interactive')) {
                GameItemPreview.close();
            }
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initGlobalItemUiDismiss();
            initGlobalItemInteractions();
        });
    } else {
        initGlobalItemUiDismiss();
        initGlobalItemInteractions();
    }

    if (!document.getElementById('game-item-ui-styles')) {
        var style = document.createElement('style');
        style.id = 'game-item-ui-styles';
        style.textContent = '.game-item-link{color:#a5b4fc;cursor:pointer;text-decoration:underline;text-decoration-style:dotted}' +
            '.game-item-link:hover{color:#c4b5fd}' +
            '.storage-slot--readonly .game-item-interactive,.storage-slot--readonly .game-item-link,' +
            '.storage-slot--locked .game-item-interactive,.storage-slot--locked .game-item-link' +
            '{pointer-events:auto;cursor:pointer}';
        document.head.appendChild(style);
    }

    window.GameItemPresenter = GameItemPresenter;
    window.GameItemTooltip = GameItemTooltip;
    window.GameItemPreview = GameItemPreview;
    window.GameItemDetailModal = GameItemDetailModal;
    window.normalizeItemDescriptor = normalizeDescriptor;
    window.readItemDescriptorFromElement = readDescriptorFromElement;
    window.bindItemTooltips = function () {
        initGlobalItemInteractions();
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

    /**
     * Подбор слота по правилам окна: сначала типизированные пустые, затем стак того же ресурса.
     * @param {Array} slots
     * @param {Object} item — нормализованный descriptor
     * @param {Array<{slotTypes: string[]|null, accepts: function}>} rules — по приоритету
     */
    var WindowSlotPlacement = {
        findBestSlot: function (slots, item, rules) {
            if (!slots || !item || !rules) return null;

            for (var i = 0; i < rules.length; i++) {
                var rule = rules[i];
                if (!rule.accepts(item)) continue;

                var candidates = slots.filter(function (s) {
                    if (rule.slotTypes === null) return true;
                    return rule.slotTypes.indexOf(s.slot_type) >= 0;
                });

                var empty = candidates.find(function (s) { return !slotOccupant(s); });
                if (empty) return empty;

                if (item.template_slug) {
                    var sameStack = candidates.find(function (s) {
                        var occ = slotOccupant(s);
                        return occ && occ.template_slug === item.template_slug;
                    });
                    if (sameStack) return sameStack;
                }
            }

            return null;
        },
    };

    var CRAFT_SLOT_RULES = [
        {
            slotTypes: ['craft_material'],
            accepts: function (item) {
                return getCraftActions(item).some(function (a) { return a.mode === 'material'; });
            },
        },
        {
            slotTypes: ['craft_center'],
            accepts: function (item) {
                return getCraftActions(item).some(function (a) { return a.mode === 'center'; });
            },
        },
    ];

    var DISASSEMBLE_SLOT_RULES = [
        {
            slotTypes: ['disassemble_center'],
            accepts: function (item) {
                return getDisassembleActions(item).length > 0;
            },
        },
    ];

    var STATION_STORAGE_INCLUDE = 'inventory,craft,disassemble';

    function normalizeStationTargetSlot(mode) {
        if (mode === 'center' || mode === 'blueprint_center' || mode === 'disassemble_center') {
            return 'center';
        }
        if (mode === 'material') {
            return 'material';
        }
        return null;
    }

    window.WindowSlotPlacement = WindowSlotPlacement;

    function getRecipes() {
        return (window.GameState && GameState.recipes) || [];
    }

    function getCraftActions(item) {
        var d = window.normalizeItemDescriptor ? normalizeItemDescriptor(item) : item;
        var actions = [];
        if (!d) return actions;

        if (d.stage === 'blueprint' && d.recipe_slug) {
            var blueprintRecipe = getRecipes().find(function (r) {
                return r.slug === d.recipe_slug && r.type === 'blueprint'
                    && r.craft_formula && Object.keys(r.craft_formula).length > 0;
            });
            if (blueprintRecipe) {
                actions.push({
                    window: 'craft',
                    mode: 'center',
                    target_slot: 'center',
                    recipe_slug: blueprintRecipe.slug,
                    label: (blueprintRecipe.craft_action && blueprintRecipe.craft_action.label) || 'Создать',
                });
            }
        }

        if (d.template_slug && (d.is_resource || (d.stage !== 'blueprint' && d.stage !== 'item'))) {
            getRecipes().forEach(function (recipe) {
                if (recipe.type !== 'resource' || !recipe.craft_formula) return;
                if (!Object.prototype.hasOwnProperty.call(recipe.craft_formula, d.template_slug)) return;
                var label = (recipe.craft_action && recipe.craft_action.label) || 'Создать';
                actions.push({
                    window: 'craft',
                    mode: 'center',
                    target_slot: 'center',
                    recipe_slug: recipe.slug,
                    label: label,
                });
                actions.push({
                    window: 'craft',
                    mode: 'material',
                    target_slot: 'material',
                    recipe_slug: recipe.slug,
                    label: label,
                });
            });
        }

        return actions;
    }

    function getDisassembleActions(item) {
        var d = window.normalizeItemDescriptor ? normalizeItemDescriptor(item) : item;
        var actions = [];
        if (!d || !d.template_slug) return actions;

        if (d.stage === 'item' && d.recipe_slug) {
            var itemRecipe = getRecipes().find(function (r) {
                return r.slug === d.recipe_slug
                    && r.disassemble_formula && Object.keys(r.disassemble_formula).length > 0;
            });
            if (itemRecipe) {
                actions.push({
                    window: 'disassemble',
                    mode: 'center',
                    target_slot: 'center',
                    recipe_slug: itemRecipe.slug,
                    label: (itemRecipe.disassemble_action && itemRecipe.disassemble_action.label) || 'Разобрать',
                });
            }
        }

        if (d.is_resource || (d.stage !== 'blueprint' && d.stage !== 'item')) {
            getRecipes().forEach(function (recipe) {
                if (recipe.type !== 'resource' || !recipe.disassemble_formula) return;
                if (recipe.result_template_slug !== d.template_slug) return;
                if (!Object.keys(recipe.disassemble_formula).length) return;
                actions.push({
                    window: 'disassemble',
                    mode: 'center',
                    target_slot: 'center',
                    recipe_slug: recipe.slug,
                    label: (recipe.disassemble_action && recipe.disassemble_action.label) || 'Разобрать',
                });
            });
        }

        return actions;
    }

    function hasCraftRecipe(item) {
        return getCraftActions(item).length > 0;
    }

    function hasDisassembleRecipe(item) {
        return getDisassembleActions(item).length > 0;
    }

    window.getCraftActions = getCraftActions;
    window.getDisassembleActions = getDisassembleActions;

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
                var isProtectedCurrency = slot.slot_type === 'gold' || slot.slot_type === 'experience';
                var classes = storageSlotClasses(slot, {
                    draggable: options.draggable !== false && !isProtectedCurrency,
                });
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
            var amount = goldAmount != null ? goldAmount : 0;
            if (goldSlot && goldSlot.resource && goldAmount == null) {
                amount = goldSlot.resource.quantity || 0;
            }
            return '<div class="gold gold-chip" id="playerGold">💰 ' + amount + '</div>';
        },

        mount: function (container, storageData, goldAmount) {
            if (!container) return;
            container.outerHTML = this.render(storageData, goldAmount);
        }
    };

    var ExperienceChip = {
        render: function (storageData, experienceAmount) {
            var xpSlot = (storageData.special_slots || []).find(function (s) {
                return s.slot_type === 'experience';
            });
            var amount = experienceAmount != null ? experienceAmount : 0;
            if (!xpSlot) {
                return '<div class="experience" id="playerExperience">⭐ ' + amount + '</div>';
            }
            var occ = xpSlot.resource;
            if (occ && experienceAmount == null) {
                amount = occ.quantity || 0;
            }
            return '<div class="experience experience-chip" id="playerExperience">⭐ ' + amount + '</div>';
        },

        mount: function (container, storageData, experienceAmount) {
            if (!container) return;
            container.outerHTML = this.render(storageData, experienceAmount);
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
        craftStorage: null,
        disassembleStorage: null,
        questStorage: null,
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
                    self.applyLayout(data);
                    return data;
                });
        },

        applyLayout: function (layout) {
            if (!layout) return;
            this.layout = layout;
            var storages = layout.storages || [];
            this.inventoryStorage = storages.find(function (s) {
                return s.storage_type === 'inventory';
            }) || this.inventoryStorage;
            this.equipmentStorage = storages.find(function (s) {
                return s.storage_type === 'equipment';
            }) || this.equipmentStorage;
            this.craftStorage = storages.find(function (s) {
                return s.storage_type === 'craft';
            }) || this.craftStorage;
            this.disassembleStorage = storages.find(function (s) {
                return s.storage_type === 'disassemble';
            }) || this.disassembleStorage;
            this.questStorage = layout.quest_storage || this.questStorage;
            this.characterStats = layout.character_stats || this.characterStats;
            this.myTradeSlots = layout.my_trade_slots || this.myTradeSlots;
            this.partnerTradeSlots = layout.partner_trade_slots || this.partnerTradeSlots;
            this.syncGameStateInventory();
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
                        self.applyLayout(data.layout);
                        if (data.layout.gold != null) {
                            var goldEl = document.getElementById('playerGold');
                            if (goldEl && window.GoldChip && StorageManager.inventoryStorage) {
                                GoldChip.mount(goldEl, StorageManager.inventoryStorage, data.layout.gold);
                            } else if (goldEl) {
                                goldEl.textContent = '💰 ' + data.layout.gold;
                            }
                        }
                        if (data.layout.experience != null) {
                            var xpEl = document.getElementById('playerExperience');
                            if (xpEl && window.ExperienceChip && StorageManager.inventoryStorage) {
                                ExperienceChip.mount(xpEl, StorageManager.inventoryStorage, data.layout.experience);
                            } else if (xpEl) {
                                xpEl.textContent = '⭐ ' + data.layout.experience;
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
                var slot = e.target.closest('.storage-slot--draggable');
                if (!slot || slot.dataset.readonly === '1') return;
                var occ = slot.querySelector('.game-item-interactive');
                if (!occ) return;
                self.onPointerDown(e, slot, occ);
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
            var dropSlot = target.closest('.storage-slot');
            if (dropSlot && dropSlot.dataset.readonly !== '1' && dropSlot !== this.active.fromSlot) {
                dropSlot.classList.add('storage-slot--drag-over');
            }
        },

        onPointerUp: function (e) {
            if (!this.active) return;

            var wasDragging = this.active.dragging;
            var target = document.elementFromPoint(e.clientX, e.clientY);
            var dropSlot = target ? target.closest('.storage-slot') : null;
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
            '.gold-chip{position:relative;cursor:default;user-select:none;touch-action:none}';
        document.head.appendChild(sgStyle);
    }

    var PLAY_PANEL_ACTIONS = {
        journal: { window: 'journal', icon: '💬', label: 'Чат' },
        inventory: { window: 'inventory', icon: '🎒', label: 'Инвентарь' },
        character: { window: 'character', icon: '🛡️', label: 'Персонаж' },
        quests: { window: 'quests', icon: '📜', label: 'Квесты' },
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
            var defaults = ['journal', 'inventory', 'character', 'quests', 'auction', 'players', 'settings'];
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
                if (action === 'workbench' || action === 'craft' || action === 'disassemble') return null;
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

        equipItem: function (item, fromSlotUuid, options) {
            var self = this;
            options = options || {};
            var silent = options.silent === true;
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
                    if (!silent && typeof showMsg === 'function') showMsg('Нет подходящего слота экипировки', 'error');
                    return;
                }
                var fromUuid = fromSlotUuid || item.slot_uuid;
                if (!fromUuid) return;
                return StorageManager.move(fromUuid, toSlot.uuid).then(function () {
                    if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                    if (typeof loadPlayerData === 'function') loadPlayerData();
                }).catch(function (err) {
                    if (!silent && typeof showMsg === 'function') showMsg(err.message, 'error');
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

        isWorkbenchResource: function (item) {
            if (!item || item.locked) return false;
            if (item.stage === 'blueprint' || item.stage === 'item') return false;
            return Boolean(item.template_slug);
        },

        isWorkbenchBlueprint: function (item) {
            if (!item || item.locked) return false;
            return item.stage === 'blueprint' || item.stage === 'item';
        },

        findStationSlot: function (item, windowName, options) {
            options = options || {};
            var storage = windowName === 'disassemble'
                ? StorageManager.disassembleStorage
                : StorageManager.craftStorage;
            if (!storage || !item) return null;
            var slots = storage.slots || storage.special_slots || [];
            var target = normalizeStationTargetSlot(options.targetSlot || options.mode);
            var rules = windowName === 'disassemble' ? DISASSEMBLE_SLOT_RULES : CRAFT_SLOT_RULES;

            if (target === 'center') {
                var centerType = windowName === 'disassemble' ? 'disassemble_center' : 'craft_center';
                var centerRule = rules.find(function (rule) {
                    return rule.slotTypes && rule.slotTypes.indexOf(centerType) >= 0 && rule.accepts(item);
                });
                if (!centerRule) return null;
                var centerSlot = slots.find(function (s) { return s.slot_type === centerType; });
                if (!centerSlot) return null;
                if (!slotOccupant(centerSlot)) return centerSlot;
                if (item.template_slug) {
                    var centerOcc = slotOccupant(centerSlot);
                    if (centerOcc && centerOcc.template_slug === item.template_slug) return centerSlot;
                }
                return centerSlot;
            }

            if (target === 'material' && windowName === 'craft') {
                var materialRule = rules.find(function (rule) {
                    return rule.slotTypes && rule.slotTypes.indexOf('craft_material') >= 0 && rule.accepts(item);
                });
                if (!materialRule) return null;
                return WindowSlotPlacement.findBestSlot(slots, item, [materialRule]);
            }

            return WindowSlotPlacement.findBestSlot(slots, item, rules);
        },

        placeOnStation: function (item, fromSlotUuid, options) {
            var self = this;
            options = options || {};
            var silent = options.silent !== false;
            var windowName = options.window || 'craft';

            if (!item || item.locked) return Promise.resolve();
            return StorageManager.load(GameState.characterUuid, STATION_STORAGE_INCLUDE).then(function () {
                var toSlot = self.findStationSlot(item, windowName, options);
                if (!toSlot) {
                    return;
                }
                var fromUuid = fromSlotUuid || item.slot_uuid;
                if (!fromUuid) return;
                return StorageManager.move(fromUuid, toSlot.uuid).then(function () {
                    if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                    if (windowName === 'disassemble' && window.DisassemblePanel) {
                        DisassemblePanel.render();
                    } else if (window.CraftPanel) {
                        CraftPanel.render();
                    }
                }).catch(function (err) {
                    if (!silent && typeof showMsg === 'function') showMsg(err.message, 'error');
                });
            });
        },

        placeOnWorkbench: function (item, fromSlotUuid, options) {
            return this.placeOnStation(item, fromSlotUuid, options);
        },

        returnFromStation: function (tempSlotUuid) {
            var storage = StorageManager.craftStorage;
            var slots = storage ? (storage.slots || storage.special_slots || []) : [];
            var slot = slots.find(function (s) { return s.uuid === tempSlotUuid; });
            if (!slot && StorageManager.disassembleStorage) {
                storage = StorageManager.disassembleStorage;
                slots = storage.slots || storage.special_slots || [];
                slot = slots.find(function (s) { return s.uuid === tempSlotUuid; });
            }
            if (!storage || !tempSlotUuid || !slot) return Promise.resolve();
            var occ = slotOccupant(slot);
            if (!occ || !occ.slot_uuid) return Promise.resolve();

            return StorageManager.load(GameState.characterUuid, STATION_STORAGE_INCLUDE).then(function () {
                return StorageManager.move(tempSlotUuid, occ.slot_uuid).then(function () {
                    if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                    if (window.CraftPanel) CraftPanel.render();
                    if (window.DisassemblePanel) DisassemblePanel.render();
                    if (typeof loadPlayerData === 'function') loadPlayerData();
                }).catch(function (err) {
                    if (typeof showMsg === 'function') showMsg(err.message, 'error');
                });
            });
        },

        returnFromWorkbench: function (tempSlotUuid) {
            return this.returnFromStation(tempSlotUuid);
        },

        returnToInventory: function (fromSlotUuid) {
            return this.unequipFromSlot(fromSlotUuid);
        },
    };

    var ItemDispatcher = {
        SINK_WINDOWS: ['craft', 'disassemble', 'trade', 'auction', 'quest'],

        getOpenSinks: function () {
            if (!window.WindowManager) return [];
            return this.SINK_WINDOWS.filter(function (name) {
                return WindowManager.isOpen(name);
            });
        },

        handleInventoryDblclick: function (item, sourceSlotUuid) {
            if (!item) return Promise.resolve();

            if (item.locked) {
                if (typeof showMsg === 'function') showMsg('Предмет занят', 'info');
                return Promise.resolve();
            }

            var self = this;
            if (item.quest_slug && window.QuestWindow) {
                return QuestWindow.open(item.quest_slug, { source: 'item' });
            }

            return this._continueDblclick(item, sourceSlotUuid);
        },

        _continueDblclick: function (item, sourceSlotUuid) {
            if (item.quest_slug || item.stage === 'quest_item') {
                return Promise.resolve();
            }

            if (window.StorageQuickActions && StorageQuickActions.isEquippable(item)) {
                if (window.WindowManager) WindowManager.open('character');
                return StorageQuickActions.equipItem(item, sourceSlotUuid, { silent: true });
            }

            var sinks = this.getOpenSinks();
            if (sinks.length !== 1) {
                return Promise.resolve();
            }

            return this.dispatchTo(sinks[0], item, sourceSlotUuid);
        },

        handleContextAction: function (action, item, sourceSlotUuid, extraOptions) {
            extraOptions = extraOptions || {};
            if (!item || item.locked) {
                if (item && item.locked && typeof showMsg === 'function') {
                    showMsg('Предмет занят', 'info');
                }
                return Promise.resolve();
            }

            if (action === 'drop') {
                if (!window.ConfirmActionModal || !window.GameState || !window.GameApi) {
                    return Promise.resolve();
                }

                var itemName = item.name || 'Предмет';
                var itemIcon = item.icon || '📦';
                var linkHtml = '';
                if (window.GameItemPresenter && window.GameItemPresenter.renderLink) {
                    linkHtml = GameItemPresenter.renderLink(item, 'confirm-item-link');
                } else {
                    linkHtml = '<span class="confirm-item-link">' + itemName + '</span>';
                }

                ConfirmActionModal.open({
                    title: 'Выбросить',
                    messageHtml: 'Вы уверены, что хотите выбросить ' + linkHtml + '?',
                    icon: itemIcon,
                    item: item,
                    onConfirm: function () {
                        return GameApi.fetch('/api/storage/' + GameState.characterUuid + '/drop-to-world', {
                            method: 'POST',
                            body: JSON.stringify({ item_uuid: item.uuid }),
                        }).then(function (r) { return r.json(); }).then(function (data) {
                            if (data.error) throw new Error(data.error);
                            if (data.layout && window.StorageManager) {
                                StorageManager.layout = data.layout;
                                StorageManager.inventoryStorage = (data.layout.storages || []).find(function (s) {
                                    return s.storage_type === 'inventory';
                                }) || StorageManager.inventoryStorage;
                            }
                            if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                            if (typeof loadPlayerData === 'function') loadPlayerData();
                            if (typeof showMsg === 'function') showMsg('Предмет выброшен в мир', 'success');
                        });
                    },
                });

                return Promise.resolve();
            }

            if (action === 'craft' || action === 'disassemble') {
                var recipeSlug = extraOptions.recipeSlug;
                var mode = extraOptions.mode;
                var winName = action === 'craft' ? 'craft' : 'disassemble';
                WindowManager.open(winName);
                return StorageManager.load(GameState.characterUuid, STATION_STORAGE_INCLUDE).then(function () {
                    return ItemDispatcher.dispatchTo(winName, item, sourceSlotUuid, {
                        recipeSlug: recipeSlug,
                        mode: mode,
                        targetSlot: mode,
                    });
                });
            }

            if (!window.WindowManager || !window.StorageManager || !window.GameState) {
                return Promise.resolve();
            }

            return Promise.resolve();
        },

        dispatchTo: function (windowName, item, sourceSlotUuid, options) {
            options = options || {};
            var descriptor = window.normalizeItemDescriptor
                ? window.normalizeItemDescriptor(item)
                : item;
            switch (windowName) {
                case 'craft':
                    return this.dispatchCraft(descriptor, sourceSlotUuid, options);
                case 'disassemble':
                    return this.dispatchDisassemble(descriptor, sourceSlotUuid, options);
                case 'workbench':
                    return this.dispatchCraft(descriptor, sourceSlotUuid);
                case 'trade':
                    return this.dispatchTrade(descriptor, sourceSlotUuid);
                case 'auction':
                    return this.dispatchAuction(descriptor);
                default:
                    return Promise.resolve();
            }
        },

        dispatchCraft: function (item, sourceSlotUuid, options) {
            var qa = window.StorageQuickActions;
            if (!qa) return Promise.resolve();
            return qa.placeOnStation(item, sourceSlotUuid, {
                silent: true,
                window: 'craft',
                mode: options.mode,
                targetSlot: options.targetSlot || options.mode,
                recipeSlug: options.recipeSlug,
            });
        },

        dispatchDisassemble: function (item, sourceSlotUuid, options) {
            var qa = window.StorageQuickActions;
            if (!qa) return Promise.resolve();
            return qa.placeOnStation(item, sourceSlotUuid, {
                silent: true,
                window: 'disassemble',
                mode: options.mode,
                targetSlot: options.targetSlot || options.mode,
                recipeSlug: options.recipeSlug,
            });
        },

        dispatchWorkbench: function (item, sourceSlotUuid) {
            return this.dispatchCraft(item, sourceSlotUuid);
        },

        dispatchTrade: function (item) {
            if (typeof window.handleTradeDrop !== 'function') return Promise.resolve();
            var isResource = item.template_slug && !item.recipe_slug
                && item.stage !== 'blueprint' && item.stage !== 'item';
            window.handleTradeDrop(item, { fullStack: isResource });
            return Promise.resolve();
        },

        dispatchAuction: function (item) {
            if (typeof window.handleAuctionDrop !== 'function') return Promise.resolve();
            window.handleAuctionDrop(item);
            return Promise.resolve();
        },
    };

    window.ItemDispatcher = ItemDispatcher;
    window.StorageQuickActions = StorageQuickActions;
    window.StorageGrid = StorageGrid;
    window.SpecialSlotsBar = SpecialSlotsBar;
    window.GoldChip = GoldChip;
    window.ExperienceChip = ExperienceChip;
    window.StorageManager = StorageManager;
    window.DragEngine = DragEngine;

    var QuestLog = {
        data: { available: [], active: [], finished: [] },
        tab: 'available',

        refresh: function () {
            var self = this;
            if (!window.GameState || !window.GameApi) return Promise.resolve();
            return GameApi.fetch('/api/quests/' + GameState.characterUuid)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    self.data = data;
                    self.render();
                })
                .catch(function (e) { console.error('QuestLog', e); });
        },

        switchTab: function (tab) {
            this.tab = tab;
            document.querySelectorAll('[data-quests-tab]').forEach(function (btn) {
                btn.classList.toggle('active', btn.dataset.questsTab === tab);
            });
            this.render();
        },

        render: function () {
            var list = document.getElementById('questsList');
            if (!list) return;
            var items = this.data[this.tab] || [];
            if (!items.length) {
                list.innerHTML = '<div class="chat-placeholder">Нет квестов</div>';
                return;
            }
            list.innerHTML = items.map(function (q) {
                var readyClass = q.ready_to_turn_in ? ' quest-list-item--ready' : '';
                return '<div class="quest-list-item' + readyClass + '" data-quest-slug="' + q.slug + '">' +
                    '<strong>' + q.name + '</strong>' +
                    '<span>' + (q.description || '') + '</span></div>';
            }).join('');
            var self = this;
            list.querySelectorAll('.quest-list-item').forEach(function (el) {
                el.addEventListener('click', function () {
                    var slug = el.dataset.questSlug;
                    if (slug && window.QuestWindow) QuestWindow.open(slug, { source: 'log' });
                });
            });
        },
    };

    var QuestWindow = {
        current: null,

        isOfferable: function (questSlug) {
            if (!window.GameState || !window.GameApi) return Promise.resolve(false);
            return GameApi.fetch('/api/quests/' + GameState.characterUuid)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    return (data.available || []).some(function (q) { return q.slug === questSlug; });
                })
                .catch(function () { return false; });
        },

        open: function (questSlug, options) {
            var self = this;
            options = options || {};
            if (!window.GameState || !window.GameApi) return Promise.resolve();
            return GameApi.fetch('/api/quests/' + GameState.characterUuid + '/' + questSlug)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) throw new Error(data.error);
                    self.current = data;
                    if (window.StorageManager && data.layout) {
                        StorageManager.layout = data.layout;
                        StorageManager.questStorage = data.layout.quest_storage || null;
                        StorageManager.inventoryStorage = (data.layout.storages || []).find(function (s) {
                            return s.storage_type === 'inventory';
                        }) || StorageManager.inventoryStorage;
                        StorageManager.characterStats = data.layout.character_stats || StorageManager.characterStats;
                    }
                    if (window.WindowManager) WindowManager.open('quest');
                    self.render();
                    if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                })
                .catch(function (e) {
                    if (typeof showMsg === 'function') showMsg(e.message, 'error');
                });
        },

        render: function () {
            var data = this.current;
            if (!data || !data.quest) return;
            var q = data.quest;
            var title = document.getElementById('questPanelTitle');
            var winTitle = document.getElementById('questWindowTitle');
            var desc = document.getElementById('questPanelDescription');
            var objEl = document.getElementById('questObjectives');
            if (title) title.textContent = q.name;
            if (winTitle) winTitle.textContent = q.name;
            if (desc) desc.textContent = q.description || '';

            if (objEl) {
                objEl.innerHTML = (q.objectives || []).map(function (o) {
                    var done = o.current >= o.required_count;
                    var label = o.label || o.type || '';
                    return '<div class="quest-objective-row' + (done ? ' is-done' : '') + '">' +
                        (o.current || 0) + ' / ' + o.required_count + ' — ' + label + '</div>';
                }).join('');
            }

            var grantSection = document.getElementById('questGrantSection');
            var turnSection = document.getElementById('questTurnInSection');
            var grantGrid = document.getElementById('questGrantGrid');
            var turnGrid = document.getElementById('questTurnInGrid');
            var qs = window.StorageManager && StorageManager.questStorage;
            var mode = data.mode || 'offer';
            var hasRewards = q.rewards && (
                (q.rewards.resources && Object.keys(q.rewards.resources).length > 0)
                || (q.rewards.items && q.rewards.items.length > 0)
            );

            if (grantSection) grantSection.style.display = mode === 'offer' ? '' : 'none';
            if (turnSection) turnSection.style.display = hasRewards ? '' : 'none';

            if (grantGrid && window.StorageGrid && mode === 'offer') {
                StorageGrid.mount(grantGrid, { slots: (qs && qs.grant_slots) || [], cols: 6 }, { draggable: false, readonly: true });
            }
            if (turnGrid && window.StorageGrid && hasRewards) {
                StorageGrid.mount(turnGrid, { slots: (qs && qs.turnin_slots) || [], cols: 6 }, { draggable: false, readonly: true });
            }

            var btnAccept = document.getElementById('btnQuestAccept');
            var btnTurnIn = document.getElementById('btnQuestTurnIn');
            if (btnAccept) btnAccept.style.display = data.can_accept ? '' : 'none';
            if (btnTurnIn) btnTurnIn.style.display = data.can_turn_in ? '' : 'none';
        },

        accept: function () {
            var self = this;
            if (!self.current || !GameState) return;
            return GameApi.fetch('/api/quests/' + GameState.characterUuid + '/accept', {
                method: 'POST',
                body: JSON.stringify({ quest_slug: self.current.quest.slug }),
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.error) throw new Error(data.error);
                if (data.layout && StorageManager) {
                    StorageManager.layout = data.layout;
                    StorageManager.questStorage = data.layout.quest_storage || null;
                }
                return self.open(self.current.quest.slug);
            }).then(function () {
                if (QuestLog) QuestLog.refresh();
                if (typeof showMsg === 'function') showMsg('Квест принят', 'success');
            }).catch(function (e) {
                if (typeof showMsg === 'function') showMsg(e.message, 'error');
            });
        },

        turnIn: function () {
            var self = this;
            if (!self.current || !GameState) return;
            return GameApi.fetch('/api/quests/' + GameState.characterUuid + '/turn-in', {
                method: 'POST',
                body: JSON.stringify({ quest_slug: self.current.quest.slug }),
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.error) throw new Error(data.error);
                if (data.layout && StorageManager) {
                    StorageManager.layout = data.layout;
                    StorageManager.questStorage = data.layout.quest_storage || null;
                    StorageManager.inventoryStorage = (data.layout.storages || []).find(function (s) {
                        return s.storage_type === 'inventory';
                    }) || StorageManager.inventoryStorage;
                }
                return self.open(self.current.quest.slug);
            }).then(function () {
                if (QuestLog) QuestLog.refresh();
                if (typeof loadPlayerData === 'function') loadPlayerData();
                if (typeof showMsg === 'function') showMsg('Квест сдан', 'success');
            }).catch(function (e) {
                if (typeof showMsg === 'function') showMsg(e.message, 'error');
            });
        },
    };

    window.QuestLog = QuestLog;
    window.QuestWindow = QuestWindow;

    function craftGetSlots() {
        var storage = window.StorageManager && StorageManager.craftStorage;
        if (!storage) return [];
        return storage.slots || storage.special_slots || [];
    }

    function craftGetSlot(slotType) {
        return craftGetSlots().find(function (s) { return s.slot_type === slotType; }) || null;
    }

    function craftMaterialSlots() {
        return craftGetSlots().filter(function (s) { return s.slot_type === 'craft_material'; });
    }

    function disassembleGetSlots() {
        var storage = window.StorageManager && StorageManager.disassembleStorage;
        if (!storage) return [];
        return storage.slots || storage.special_slots || [];
    }

    function disassembleGetSlot(slotType) {
        return disassembleGetSlots().find(function (s) { return s.slot_type === slotType; }) || null;
    }

    function stationOccupant(slot) {
        return slot ? (slot.item || slot.resource || null) : null;
    }

    function wbNormalizeMaterialsUsed(data) {
        if (!data || typeof data !== 'object') return {};
        if (data.resources && typeof data.resources === 'object') return data.resources;
        return data;
    }

    function craftSumMaterial(slug) {
        var total = 0;
        craftMaterialSlots().forEach(function (slot) {
            var occ = stationOccupant(slot);
            if (occ && occ.template_slug === slug) {
                total += parseInt(occ.quantity, 10) || 0;
            }
        });
        return total;
    }

    function craftIngredientTotals() {
        var totals = {};
        craftGetSlots().forEach(function (slot) {
            var occ = stationOccupant(slot);
            if (occ && occ.template_slug) {
                totals[occ.template_slug] = (totals[occ.template_slug] || 0) + (parseInt(occ.quantity, 10) || 0);
            }
        });
        return totals;
    }

    function craftDetectResourceRecipe() {
        var totals = craftIngredientTotals();
        return getRecipes().find(function (recipe) {
            if (recipe.type !== 'resource' || !recipe.craft_formula) return false;
            return Object.keys(recipe.craft_formula).every(function (slug) {
                return (totals[slug] || 0) >= recipe.craft_formula[slug];
            });
        }) || null;
    }

    function stationRenderSlotElement(slot, options) {
        options = options || {};
        var occ = stationOccupant(slot);
        var classes = 'storage-slot workbench-storage-slot';
        classes += occ ? ' storage-slot--draggable' : ' storage-slot--empty';
        var kind = slot.kind || 'temporary';
        var html = '<div class="' + classes + '" data-slot-uuid="' + slot.uuid + '" data-slot-kind="' + kind + '" data-readonly="0">';
        if (occ && window.GameItemPresenter) {
            html += GameItemPresenter.renderIcon(occ, 'storage-slot-item');
        }
        html += '</div>';
        if (options.hint) {
            html = '<div class="workbench-ingredient-cell">' + html +
                '<div class="workbench-formula-qty ' + (options.hintClass || '') + '">' + options.hint + '</div></div>';
        }
        return html;
    }

    function stationRenderLockedPreview(descriptor, label) {
        var preview = Object.assign({}, descriptor, { locked: true });
        var html = '<div class="storage-slot storage-slot--readonly storage-slot--locked workbench-result-preview" data-readonly="1">';
        if (window.GameItemPresenter) {
            html += GameItemPresenter.renderIcon(preview, 'storage-slot-item storage-slot-item--locked');
        }
        html += '</div>';
        if (label) html += '<div class="workbench-slot-caption">' + label + '</div>';
        return html;
    }

    function stationRenderStats(container, descriptor) {
        if (!container) return;
        var stats = descriptor && descriptor.stats ? descriptor.stats : {};
        var keys = Object.keys(stats).filter(function (k) { return stats[k] != null && stats[k] !== ''; });
        if (!keys.length) {
            container.innerHTML = '<div class="workbench-stats-empty">—</div>';
            return;
        }
        container.innerHTML = keys.map(function (key) {
            var val = stats[key];
            if (typeof val === 'object' && val.min != null && val.max != null) {
                val = val.min + '–' + val.max;
            }
            return '<div class="workbench-stat-row"><span>' + key + '</span><strong>' + val + '</strong></div>';
        }).join('');
    }

    function stationBindDblclick(panelSelector) {
        var panel = document.querySelector(panelSelector);
        if (!panel || panel.dataset.dblBound) return;
        panel.dataset.dblBound = '1';
        panel.addEventListener('dblclick', function (e) {
            var itemEl = e.target.closest('.game-item-interactive');
            var slotEl = e.target.closest('.workbench-storage-slot[data-slot-uuid]');
            if (!itemEl || !slotEl || !window.StorageQuickActions) return;
            if (window.GameItemTooltip) GameItemTooltip.hide();
            if (window.GameItemPreview) GameItemPreview.close();
            if (window.StorageQuickActions.returnFromStation) {
                StorageQuickActions.returnFromStation(slotEl.dataset.slotUuid);
            }
        });
    }

    var CraftPanel = {
        resolveContext: function () {
            var centerSlot = craftGetSlot('craft_center');
            var center = stationOccupant(centerSlot);
            var recipes = getRecipes();

            if (center) {
                var blueprintActions = getCraftActions(center).filter(function (a) {
                    return a.mode === 'center';
                });
                if (blueprintActions.length) {
                    var craftRecipe = recipes.find(function (r) {
                        return r.slug === blueprintActions[0].recipe_slug;
                    }) || null;
                    return { mode: 'craft', recipe: craftRecipe, center: center };
                }
            }

            var resourceRecipe = craftDetectResourceRecipe();
            if (resourceRecipe) {
                return { mode: 'resource', recipe: resourceRecipe, center: null };
            }

            return { mode: null, recipe: null, center: center };
        },

        render: function () {
            var ctx = this.resolveContext();
            var materialsEl = document.getElementById('craftMaterials');
            var blueprintEl = document.getElementById('craftBlueprintSlot');
            var resultEl = document.getElementById('craftResultSlot');
            var statsEl = document.getElementById('craftStatsBody');
            var craftMode = document.getElementById('craftItemMode');
            var resourceMode = document.getElementById('craftResourceMode');
            var emptyMode = document.getElementById('craftEmptyMode');

            if (craftMode) craftMode.style.display = 'none';
            if (resourceMode) resourceMode.style.display = 'none';
            if (emptyMode) emptyMode.style.display = 'none';

            if (ctx.mode === 'craft') {
                if (craftMode) craftMode.style.display = 'block';
                var nameInput = document.getElementById('craftCustomName');
                if (nameInput && ctx.recipe) {
                    var defaultName = ctx.recipe.name || '';
                    if (ctx.recipe.result_template_slug && GameItemPresenter.descriptorFromSlug) {
                        var preview = GameItemPresenter.descriptorFromSlug(ctx.recipe.result_template_slug, 1);
                        if (preview && preview.name) defaultName = preview.name;
                    }
                    nameInput.placeholder = defaultName;
                }
            } else if (ctx.mode === 'resource') {
                if (resourceMode) resourceMode.style.display = 'block';
                var transformBtn = document.getElementById('btnCraftResource');
                if (transformBtn && ctx.recipe && ctx.recipe.craft_action && ctx.recipe.craft_action.label) {
                    transformBtn.textContent = ctx.recipe.craft_action.label;
                } else if (transformBtn) {
                    transformBtn.textContent = 'Преобразовать';
                }
            } else if (emptyMode) {
                emptyMode.style.display = 'block';
            }

            var centerSlot = craftGetSlot('craft_center');
            var centerOcc = stationOccupant(centerSlot);
            if (blueprintEl) {
                if (centerSlot && centerOcc && getCraftActions(centerOcc).some(function (a) {
                    return a.mode === 'center';
                })) {
                    blueprintEl.innerHTML = stationRenderSlotElement(centerSlot);
                } else if (centerSlot && !centerOcc) {
                    blueprintEl.innerHTML = stationRenderSlotElement(centerSlot);
                } else {
                    blueprintEl.innerHTML = '<div class="storage-slot storage-slot--empty workbench-storage-slot" data-readonly="1"><div class="workbench-placeholder">?</div></div>';
                }
            }

            if (materialsEl) {
                var formula = (ctx.recipe && ctx.recipe.craft_formula) ? ctx.recipe.craft_formula : null;
                materialsEl.innerHTML = craftMaterialSlots().map(function (slot, index) {
                    var occ = stationOccupant(slot);
                    var hint = '';
                    var hintClass = '';
                    if (formula && !occ) {
                        var entries = Object.entries(formula);
                        if (entries[index]) {
                            var slug = entries[index][0];
                            var needed = entries[index][1];
                            var available = craftSumMaterial(slug);
                            hint = available + ' / ' + needed;
                            hintClass = available >= needed ? 'is-enough' : 'is-short';
                        }
                    }
                    return stationRenderSlotElement(slot, { hint: hint, hintClass: hintClass });
                }).join('');
            }

            if (resultEl) {
                if ((ctx.mode === 'craft' || ctx.mode === 'resource') && ctx.recipe && ctx.recipe.result_template_slug && GameItemPresenter) {
                    var full = GameItemPresenter.descriptorFromSlug(ctx.recipe.result_template_slug, 1);
                    resultEl.innerHTML = stationRenderLockedPreview(full, ctx.recipe.name);
                    stationRenderStats(statsEl, full);
                } else {
                    resultEl.innerHTML = '<div class="storage-slot storage-slot--empty storage-slot--locked workbench-result-preview" data-readonly="1"><div class="workbench-placeholder">—</div></div>';
                    stationRenderStats(statsEl, null);
                }
            }

            stationBindDblclick('.craft-panel');
            if (window.DragEngine) {
                DragEngine.registerGrid(document.getElementById('craftMaterials'));
                DragEngine.registerGrid(document.getElementById('craftBlueprintSlot'));
            }
        },

        load: function () {
            if (!StorageManager || !GameState.characterUuid) return Promise.resolve();
            return StorageManager.load(GameState.characterUuid, STATION_STORAGE_INCLUDE).then(function () {
                CraftPanel.render();
            });
        },
    };

    function disassembleReturnPreviewCells(formula) {
        var count = 8;
        var cells = [];
        for (var index = 0; index < count; index++) {
            var hint = '';
            var hintClass = '';
            if (formula) {
                var entries = Object.entries(formula);
                if (entries[index]) {
                    hint = '+' + entries[index][1];
                    hintClass = 'is-return';
                }
            }
            cells.push(
                '<div class="workbench-ingredient-cell">' +
                '<div class="storage-slot storage-slot--empty storage-slot--readonly workbench-storage-slot" data-readonly="1"></div>' +
                (hint ? '<div class="workbench-formula-qty ' + hintClass + '">' + hint + '</div>' : '') +
                '</div>'
            );
        }
        return cells.join('');
    }

    var DisassemblePanel = {
        resolveContext: function () {
            var centerSlot = disassembleGetSlot('disassemble_center');
            var center = stationOccupant(centerSlot);
            if (center) {
                var actions = getDisassembleActions(center);
                if (actions.length) {
                    var disRecipe = getRecipes().find(function (r) {
                        return r.slug === actions[0].recipe_slug;
                    }) || null;
                    return { mode: 'disassemble', recipe: disRecipe, center: center };
                }
            }
            return { mode: null, recipe: null, center: null };
        },

        render: function () {
            var ctx = this.resolveContext();
            var itemEl = document.getElementById('disassembleItemSlot');
            var returnsEl = document.getElementById('disassembleReturns');
            var statsEl = document.getElementById('disassembleStatsBody');
            var actionMode = document.getElementById('disassembleActionMode');
            var emptyMode = document.getElementById('disassembleEmptyMode');

            if (actionMode) actionMode.style.display = ctx.mode === 'disassemble' ? 'block' : 'none';
            if (emptyMode) emptyMode.style.display = ctx.mode ? 'none' : 'block';

            var disassembleBtn = document.getElementById('btnDisassembleItem');
            if (disassembleBtn) {
                if (ctx.recipe && ctx.recipe.disassemble_action && ctx.recipe.disassemble_action.label) {
                    disassembleBtn.textContent = ctx.recipe.disassemble_action.label;
                } else {
                    disassembleBtn.textContent = 'Разобрать';
                }
            }

            var centerSlot = disassembleGetSlot('disassemble_center');
            var centerOcc = stationOccupant(centerSlot);
            if (itemEl) {
                if (centerSlot && centerOcc && getDisassembleActions(centerOcc).length) {
                    itemEl.innerHTML = stationRenderSlotElement(centerSlot);
                } else if (centerSlot && !centerOcc) {
                    itemEl.innerHTML = stationRenderSlotElement(centerSlot);
                } else {
                    itemEl.innerHTML = '<div class="storage-slot storage-slot--empty workbench-storage-slot" data-readonly="1"><div class="workbench-placeholder">?</div></div>';
                }
            }

            if (returnsEl) {
                var disFormula = null;
                if (ctx.mode === 'disassemble' && ctx.center) {
                    var used = wbNormalizeMaterialsUsed(ctx.center.materials_used);
                    if (used && Object.keys(used).length) {
                        disFormula = used;
                    } else if (ctx.recipe && ctx.recipe.disassemble_formula) {
                        disFormula = ctx.recipe.disassemble_formula;
                    }
                }
                returnsEl.innerHTML = disassembleReturnPreviewCells(disFormula);
            }

            if (statsEl) {
                if (ctx.mode === 'disassemble' && ctx.center) {
                    stationRenderStats(statsEl, ctx.center);
                } else {
                    stationRenderStats(statsEl, null);
                }
            }

            stationBindDblclick('.disassemble-panel');
            if (window.DragEngine) {
                DragEngine.registerGrid(document.getElementById('disassembleItemSlot'));
                DragEngine.registerGrid(document.getElementById('disassembleReturns'));
            }
        },

        load: function () {
            if (!StorageManager || !GameState.characterUuid) return Promise.resolve();
            return StorageManager.load(GameState.characterUuid, STATION_STORAGE_INCLUDE).then(function () {
                DisassemblePanel.render();
            });
        },
    };

    window.CraftPanel = CraftPanel;
    window.DisassemblePanel = DisassemblePanel;
    window.renderCraftPanel = function () { CraftPanel.render(); };
    window.renderDisassemblePanel = function () { DisassemblePanel.render(); };
    window.loadCraftPanel = function () { return CraftPanel.load(); };
    window.loadDisassemblePanel = function () { return DisassemblePanel.load(); };

    function initCraftStation() {
        if (window._craftStationInitialized) return;
        window._craftStationInitialized = true;
        var btnCraft = document.getElementById('btnCraftItem');
        var btnTransform = document.getElementById('btnCraftResource');
        if (btnCraft) btnCraft.addEventListener('click', function () {
            var ctx = CraftPanel.resolveContext();
            if (!ctx.recipe || !ctx.center || ctx.mode !== 'craft') return;
            var customName = (document.getElementById('craftCustomName') || {}).value || '';
            customName = customName.trim();
            GameApi.fetch('/api/crafting/' + GameState.characterUuid + '/craft-item', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    recipe_slug: ctx.recipe.slug,
                    blueprint_uuid: ctx.center.uuid,
                    custom_name: customName || null,
                }),
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.error) throw new Error(data.error);
                if (typeof showMsg === 'function') showMsg('Создано: ' + (data.item.custom_name || data.item.template_slug), 'success');
                var nameInput = document.getElementById('craftCustomName');
                if (nameInput) nameInput.value = '';
                if (typeof loadPlayerData === 'function') loadPlayerData();
                return CraftPanel.load();
            }).catch(function (e) {
                if (typeof showMsg === 'function') showMsg(e.message, 'error');
            });
        });
        if (btnTransform) btnTransform.addEventListener('click', function () {
            var ctx = CraftPanel.resolveContext();
            if (!ctx.recipe || ctx.mode !== 'resource') return;
            GameApi.fetch('/api/crafting/' + GameState.characterUuid + '/craft-resource', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ recipe_slug: ctx.recipe.slug, times: 1 }),
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.error) throw new Error(data.error);
                if (typeof showMsg === 'function') showMsg('Ресурс преобразован', 'success');
                if (typeof loadPlayerData === 'function') loadPlayerData();
                return CraftPanel.load();
            }).catch(function (e) {
                if (typeof showMsg === 'function') showMsg(e.message, 'error');
            });
        });
    }

    function initDisassembleStation() {
        if (window._disassembleStationInitialized) return;
        window._disassembleStationInitialized = true;
        var btn = document.getElementById('btnDisassembleItem');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var ctx = DisassemblePanel.resolveContext();
            if (!ctx.center || ctx.mode !== 'disassemble') return;
            var body = { times: 1 };
            if (ctx.center.stage === 'item') {
                body.item_uuid = ctx.center.uuid;
            } else if (ctx.recipe) {
                body.recipe_slug = ctx.recipe.slug;
            } else {
                return;
            }
            GameApi.fetch('/api/crafting/' + GameState.characterUuid + '/disassemble', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body),
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.error) throw new Error(data.error);
                if (typeof showMsg === 'function') showMsg('Разобрано', 'success');
                if (typeof loadPlayerData === 'function') loadPlayerData();
                return DisassemblePanel.load();
            }).catch(function (e) {
                if (typeof showMsg === 'function') showMsg(e.message, 'error');
            });
        });
    }

    window.initCraftStation = initCraftStation;
    window.initDisassembleStation = initDisassembleStation;

    document.addEventListener('DOMContentLoaded', function () {
        initCraftStation();
        initDisassembleStation();
    });

    document.addEventListener('DOMContentLoaded', function () {
        var btnAccept = document.getElementById('btnQuestAccept');
        var btnTurnIn = document.getElementById('btnQuestTurnIn');
        if (btnAccept) btnAccept.addEventListener('click', function () { QuestWindow.accept(); });
        if (btnTurnIn) btnTurnIn.addEventListener('click', function () { QuestWindow.turnIn(); });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { DragEngine.init(); });
    } else {
        DragEngine.init();
    }
})();
