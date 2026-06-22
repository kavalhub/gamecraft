<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Крафт-Игра')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh; color: #eee; overflow: hidden;
        }

        .game-layout {
            display: grid;
            grid-template-columns: 320px 1fr 350px;
            grid-template-rows: 1fr 80px;
            height: 100vh;
            gap: 10px;
            padding: 10px;
        }

        /* ===== Журнал слева ===== */
        .journal-panel {
            grid-row: 1; grid-column: 1;
            background: rgba(0,0,0,0.4);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; flex-direction: column;
            overflow: hidden;
        }

        .panel-header {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.03);
            display: flex; align-items: center; gap: 10px;
        }
        .panel-header h2 { font-size: 15px; font-weight: 700; color: #d4a574; }
        .panel-header .subtitle { font-size: 11px; color: #888; }

        .events-list {
            flex: 1; overflow-y: auto; padding: 10px;
            display: flex; flex-direction: column; gap: 8px;
        }

        .event-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-left: 3px solid #667eea;
            border-radius: 6px; padding: 10px 12px;
            font-size: 12px;
            animation: slideIn 0.3s ease-out;
        }
        .event-item.new { animation: slideIn 0.4s ease-out, glow 1s ease-out; }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes glow {
            0% { box-shadow: 0 0 0 rgba(102,126,234,0); }
            50% { box-shadow: 0 0 15px rgba(102,126,234,0.4); }
            100% { box-shadow: 0 0 0 rgba(102,126,234,0); }
        }

        .event-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .event-icon { font-size: 16px; margin-right: 6px; }
        .event-title { font-weight: 600; font-size: 12px; }
        .event-time { font-size: 10px; color: #888; font-family: monospace; }
        .event-body { color: #bbb; font-size: 11px; line-height: 1.5; margin-top: 4px; }
        .event-body b { color: #fbbf24; }

        .event-item[data-type="user.registered"] { border-left-color: #10b981; }
        .event-item[data-type="user.gold_changed"] { border-left-color: #fbbf24; }
        .event-item[data-type="item.received"] { border-left-color: #3b82f6; }
        .event-item[data-type="item.removed"] { border-left-color: #ef4444; }
        .event-item[data-type="item.crafted"] { border-left-color: #a855f7; }
        .event-item[data-type="item.disassembled"] { border-left-color: #f97316; }
        .event-item[data-type="auction.listed"] { border-left-color: #06b6d4; }
        .event-item[data-type="auction.purchase"] { border-left-color: #84cc16; }
        .event-item[data-type="auction.sale"] { border-left-color: #fbbf24; }
        .event-item[data-type="auction.cancelled"] { border-left-color: #6b7280; }
        .event-item[data-type="trade.created"] { border-left-color: #10b981; }
        .event-item[data-type="trade.updated"] { border-left-color: #3b82f6; }
        .event-item[data-type="trade.accepted"] { border-left-color: #a855f7; }
        .event-item[data-type="trade.completed"] { border-left-color: #fbbf24; }
        .event-item[data-type="trade.cancelled"] { border-left-color: #ef4444; }

        .events-empty { text-align: center; padding: 40px 20px; color: #666; font-size: 12px; }

        .events-list::-webkit-scrollbar { width: 6px; }
        .events-list::-webkit-scrollbar-track { background: transparent; }
        .events-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }

        /* ===== Центр ===== */
        .center-panel {
            grid-row: 1; grid-column: 2;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
            display: flex; flex-direction: column;
        }
        .center-content { flex: 1; overflow-y: auto; padding: 20px; }

        /* ===== Инвентарь справа ===== */
        .inventory-panel {
            grid-row: 1; grid-column: 3;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; flex-direction: column;
            overflow: hidden;
        }
        .player-bar {
            padding: 15px 20px;
            background: rgba(255,255,255,0.03);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; justify-content: space-between; align-items: center;
        }
        .player-name { font-size: 18px; font-weight: 600; }
        .gold { font-size: 16px; color: #fbbf24; font-weight: 700; }

        .inventory-content { flex: 1; overflow-y: auto; padding: 15px; }
        .items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 8px;
        }
        .item {
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 8px;
            text-align: center;
            cursor: grab;
            transition: all 0.2s;
            user-select: none;
            position: relative;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .item:hover { border-color: #667eea; transform: translateY(-2px); }
        .item.dragging { opacity: 0.4; cursor: grabbing; }
        .item-icon { font-size: 32px; }
        .item-qty {
            position: absolute;
            bottom: 2px;
            right: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #fbbf24;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
        }
        .item-link {
            color: #667eea;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 1px dashed rgba(102, 126, 234, 0.5);
            transition: all 0.2s;
            display: inline;
        }
        .item-link:hover {
            color: #a855f7;
            border-bottom-color: #a855f7;
        }

        .item[data-type="recipe"] { border-color: rgba(168,85,247,0.4); background: rgba(168,85,247,0.1); }
        .item[data-type="equipment"] { border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.05); }
        .item[data-type="material"] { border-color: rgba(251,191,36,0.3); background: rgba(251,191,36,0.05); }

        /* ===== Тулбар ===== */
        .toolbar {
            grid-row: 2; grid-column: 1 / -1;
            background: rgba(0,0,0,0.5);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; justify-content: center; align-items: center;
            gap: 20px; padding: 0 30px;
        }
        .tool-btn {
            width: 60px; height: 60px; border-radius: 10px;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s; gap: 4px;
        }
        .tool-btn:hover { background: rgba(255,255,255,0.1); border-color: #667eea; transform: translateY(-3px); }
        .tool-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        .tool-btn .icon { font-size: 24px; }
        .tool-btn .label { font-size: 10px; font-weight: 600; }

        /* ===== Всплывашки ===== */
        .msg {
            position: fixed; top: 20px; left: 50%;
            transform: translateX(-50%);
            padding: 12px 20px; border-radius: 8px;
            font-size: 14px; z-index: 1000;
            display: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            max-width: 700px; min-width: 300px;
        }
        .msg.success { background: rgba(16,185,129,0.95); color: white; }
        .msg.error { background: rgba(239,68,68,0.95); color: white; }
        .msg.show { display: flex; animation: slideDown 0.3s ease-out; }

        @keyframes slideDown {
            from { opacity: 0; transform: translate(-50%, -20px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* ===== Tooltip ===== */
        #itemTooltip {
            position: fixed;
            background: rgba(20, 20, 35, 0.98);
            border: 2px solid rgba(102, 126, 234, 0.6);
            border-radius: 8px;
            padding: 12px 15px;
            max-width: 300px;
            z-index: 9999;
            pointer-events: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            display: none;
            font-size: 13px;
        }
        #itemTooltip.visible {
            display: block;
            animation: tooltipFadeIn 0.15s ease-out;
        }
        @keyframes tooltipFadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tooltip-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .tooltip-icon {
            font-size: 28px;
        }
        .tooltip-name {
            font-weight: 700;
            font-size: 15px;
            color: #fff;
        }
        .tooltip-type {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .tooltip-description {
            color: #bbb;
            font-size: 12px;
            line-height: 1.4;
            margin-bottom: 8px;
            font-style: italic;
        }
        .tooltip-stats {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .tooltip-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }
        .tooltip-stat-label {
            color: #888;
        }
        .tooltip-stat-value {
            color: #10b981;
            font-weight: 600;
        }
        .tooltip-quantity {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            font-size: 14px;
            color: #fbbf24;
            font-weight: 700;
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="game-layout">
    <!-- Журнал -->
    <aside class="journal-panel">
        <div class="panel-header">
            <h2>📜 Журнал</h2>
            <div class="subtitle">События игры</div>
        </div>
        <div class="events-list" id="eventsList">
            <div class="events-empty">Загрузка...</div>
        </div>
    </aside>

    <!-- Центр -->
    <main class="center-panel">
        <div class="center-content" id="centerContent">
            @yield('center')
        </div>
    </main>

    <!-- Инвентарь -->
    <aside class="inventory-panel">
        <div class="player-bar">
            <div class="player-name" id="playerName">Загрузка...</div>
            <div class="gold" id="playerGold">💰 0</div>
        </div>
        <div class="panel-header">
            <h2>🎒 Инвентарь</h2>
        </div>
        <div class="inventory-content">
            <div id="inventoryContent" class="items"></div>
        </div>
    </aside>

    <!-- Тулбар -->
    <nav class="toolbar">
        <div class="tool-btn" data-tool="workbench" onclick="switchTool('workbench')">
            <div class="icon">🔨</div>
            <div class="label">Верстак</div>
        </div>
        <div class="tool-btn" data-tool="auction" onclick="switchTool('auction')">
            <div class="icon">🏪</div>
            <div class="label">Аукцион</div>
        </div>
        <div class="tool-btn" data-tool="trade" onclick="switchTool('trade')">
            <div class="icon">🤝</div>
            <div class="label">Обмен</div>
        </div>
    </nav>
</div>

<div id="msg" class="msg"></div>
<div id="itemTooltip"></div>

<script>
    // ================================================================
    //                     ГЛОБАЛЬНОЕ СОСТОЯНИЕ
    // ================================================================
    window.GameState = {
        userId: null,
        inventory: [],
        recipes: [],
        currentTool: 'workbench',
    };

    // ================================================================
    //                          УТИЛИТЫ
    // ================================================================
    function showMsg(text, type) {
        const el = document.getElementById('msg');
        const copyId = 'msg_' + Date.now();
        el.innerHTML = `
                <div style="display:flex;align-items:center;gap:15px;flex:1;min-width:0">
                    <span style="flex:1;word-break:break-word">${text}</span>
                    <button onclick="copyMsgText('${copyId}')" style="padding:6px 12px;background:rgba(255,255,255,0.2);color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;white-space:nowrap;flex-shrink:0">📋 Копировать</button>
                    <span onclick="this.parentElement.parentElement.classList.remove('show')" style="cursor:pointer;font-weight:bold;opacity:0.7;font-size:18px;flex-shrink:0">✕</span>
                </div>
                <textarea id="${copyId}" style="position:absolute;left:-9999px">${text.replace(/<[^>]*>/g, '')}</textarea>
            `;
        el.className = `msg ${type} show`;
        clearTimeout(window.msgTimeout);
        window.msgTimeout = setTimeout(() => el.classList.remove('show'), 10000);
    }

    function copyMsgText(textareaId) {
        const textarea = document.getElementById(textareaId);
        if (!textarea) return;
        textarea.select();
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textarea.value);
            } else {
                document.execCommand('copy');
            }
            const btn = document.querySelector('#msg button');
            if (btn) {
                const orig = btn.innerHTML;
                btn.innerHTML = '✅ Скопировано!';
                setTimeout(() => btn.innerHTML = orig, 1500);
            }
        } catch (e) { console.error(e); }
    }

    function getIcon(type) {
        return { material: '📦', equipment: '⚔️', consumable: '🧪', recipe: '📜' }[type] || '📦';
    }

    // ================================================================
    //                     ПЕРЕКЛЮЧЕНИЕ ИНСТРУМЕНТОВ
    // ================================================================
    function switchTool(tool) {
        GameState.currentTool = tool;
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tool === tool);
        });
        document.querySelectorAll('.tool-panel').forEach(panel => panel.style.display = 'none');
        const active = document.getElementById('tool-' + tool);
        if (active) active.style.display = 'block';
    }

    // ================================================================
    //                     ЗАГРУЗКА ДАННЫХ ИГРОКА
    // ================================================================
    async function loadPlayerData() {
        try {
            const res = await fetch(`/api/inventory?user_id=${GameState.userId}`, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.user) {
                document.getElementById('playerName').textContent = data.user.username;
                document.getElementById('playerGold').textContent = '💰 ' + data.user.gold;
            }
            GameState.inventory = data.inventory || [];
            renderInventory();
        } catch (e) { console.error('Player data load error:', e); }
    }

    function renderInventory() {
        const el = document.getElementById('inventoryContent');
        if (!GameState.inventory.length) {
            el.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:30px;color:#666">Инвентарь пуст</div>';
            return;
        }
        el.innerHTML = GameState.inventory.map(item => {
            const inTrade = typeof isItemInActiveTrade === 'function' && isItemInActiveTrade(item.instance_id);
            const tradeClass = inTrade ? 'in-trade' : '';
            return `
                    <div class="item ${tradeClass}"
                         data-type="${item.type}"
                         data-instance-id="${item.instance_id}"
                         data-template-id="${item.template_id}"
                         data-quantity="${item.quantity}"
                         data-name="${item.name}"
                         data-description="${item.description || ''}"
                         data-stats='${JSON.stringify(item.stats || {})}'
                         draggable="${!inTrade}"
                         title="">
                        <div class="item-icon">${getIcon(item.type)}</div>
                        ${item.is_stackable && item.quantity != null ? `<div class="item-qty">x${item.quantity}</div>` : ''}
                    </div>
                `;
        }).join('');

        el.querySelectorAll('.item').forEach(itemEl => {
            const inTrade = itemEl.classList.contains('in-trade');
            if (!inTrade) {
                itemEl.addEventListener('dragstart', onDragStart);
                itemEl.addEventListener('dragend', onDragEnd);
                itemEl.addEventListener('dblclick', onDoubleClick);
            } else {
                itemEl.style.cursor = 'not-allowed';
            }
            itemEl.addEventListener('mouseenter', showItemTooltip);
            itemEl.addEventListener('mouseleave', hideItemTooltip);
            itemEl.addEventListener('mousemove', moveItemTooltip);
        });
    }

    // ================================================================
    //                       DRAG-AND-DROP
    // ================================================================
    let draggedItem = null;

    function onDragStart(e) {
        draggedItem = {
            instance_id: parseInt(e.currentTarget.dataset.instanceId),
            template_id: parseInt(e.currentTarget.dataset.templateId),
            quantity: parseInt(e.currentTarget.dataset.quantity),
            type: e.currentTarget.dataset.type,
            name: e.currentTarget.dataset.name,
            description: e.currentTarget.dataset.description,
            stats: JSON.parse(e.currentTarget.dataset.stats || '{}'),
        };
        e.currentTarget.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');
        hideItemTooltip();
    }

    function onDragEnd(e) {
        e.currentTarget.classList.remove('dragging');
        draggedItem = null;
        document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
    }

    function onDoubleClick(e) {
        const item = {
            instance_id: parseInt(e.currentTarget.dataset.instanceId),
            template_id: parseInt(e.currentTarget.dataset.templateId),
            quantity: parseInt(e.currentTarget.dataset.quantity),
            type: e.currentTarget.dataset.type,
            name: e.currentTarget.dataset.name,
            description: e.currentTarget.dataset.description,
            stats: JSON.parse(e.currentTarget.dataset.stats || '{}'),
        };

        if (GameState.currentTool === 'workbench' && typeof handleWorkbenchDrop === 'function') {
            handleWorkbenchDrop(item);
        }
        if (GameState.currentTool === 'auction' && typeof handleAuctionDrop === 'function') {
            handleAuctionDrop(item);
        }
        if (GameState.currentTool === 'trade' && typeof handleTradeDrop === 'function') {
            handleTradeDrop(item);
        }
    }

    function setupDropZone(el, onDrop) {
        if (!el) return;
        el.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            el.classList.add('drag-over');
        });
        el.addEventListener('dragleave', () => el.classList.remove('drag-over'));
        el.addEventListener('drop', (e) => {
            e.preventDefault();
            el.classList.remove('drag-over');
            if (draggedItem) onDrop(draggedItem);
        });
    }

    // ================================================================
    //                       TOOLTIP СИСТЕМА
    // ================================================================
    function showItemTooltip(e) {
        const tooltip = document.getElementById('itemTooltip');
        const element = e.currentTarget;

        let name, type, description, quantity, stats, icon;

        if (element.classList.contains('item')) {
            name = element.dataset.name;
            type = element.dataset.type;
            description = element.dataset.description;
            quantity = parseInt(element.dataset.quantity);
            stats = JSON.parse(element.dataset.stats || '{}');
            icon = getIcon(type);
        } else if (element.classList.contains('item-link')) {
            name = element.dataset.name;
            type = element.dataset.type;
            description = element.dataset.description;
            quantity = parseInt(element.dataset.quantity);
            stats = JSON.parse(element.dataset.stats || '{}');
            icon = element.dataset.icon || getIcon(type);
        } else {
            return;
        }

        // Если иконка — это файл (содержит точку), показываем эмодзи по типу
        // Если эмодзи — показываем как есть
        let iconHtml;
        if (icon && icon.includes('.')) {
            // Это файл, показываем эмодзи по типу
            iconHtml = getIcon(type);
        } else {
            // Это эмодзи
            iconHtml = icon || getIcon(type);
        }

        let html = `
        <div class="tooltip-header">
            <div class="tooltip-icon">${iconHtml}</div>
            <div>
                <div class="tooltip-name">${name}</div>
                <div class="tooltip-type">${getTypeName(type)}</div>
            </div>
        </div>
    `;

        if (description) {
            html += `<div class="tooltip-description">${description}</div>`;
        }

        if (Object.keys(stats).length > 0) {
            html += '<div class="tooltip-stats">';
            for (const [key, value] of Object.entries(stats)) {
                html += `
                <div class="tooltip-stat">
                    <span class="tooltip-stat-label">${getStatLabel(key)}:</span>
                    <span class="tooltip-stat-value">${value}</span>
                </div>
            `;
            }
            html += '</div>';
        }

        if (quantity > 1) {
            html += `<div class="tooltip-quantity">Количество: ${quantity}</div>`;
        }

        tooltip.innerHTML = html;
        tooltip.classList.add('visible');
        moveItemTooltip(e);
    }

    function hideItemTooltip() {
        const tooltip = document.getElementById('itemTooltip');
        tooltip.classList.remove('visible');
    }

    function moveItemTooltip(e) {
        const tooltip = document.getElementById('itemTooltip');
        const offsetX = 15;
        const offsetY = 15;

        let x = e.clientX + offsetX;
        let y = e.clientY + offsetY;

        const rect = tooltip.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        if (x + rect.width > viewportWidth) {
            x = e.clientX - rect.width - offsetX;
        }
        if (y + rect.height > viewportHeight) {
            y = e.clientY - rect.height - offsetY;
        }

        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    }

    function getTypeName(type) {
        const names = {
            material: 'Материал',
            equipment: 'Экипировка',
            consumable: 'Расходник',
            recipe: 'Чертёж'
        };
        return names[type] || type;
    }

    function getStatLabel(key) {
        const labels = {
            attack: 'Атака',
            defense: 'Защита',
            health: 'Здоровье',
            durability: 'Прочность',
            level: 'Уровень',
            weight: 'Вес',
            value: 'Ценность'
        };
        return labels[key] || key;
    }

    // ================================================================
    //          EVENT POLLER — источник событий (не знает про UI)
    // ================================================================
    window.EventPoller = {
        lastEventId: 0,
        pollingInterval: null,
        userId: null,
        listeners: [],

        start(userId) {
            this.userId = userId;
            this.loadInitial();
            this.pollingInterval = setInterval(() => this.poll(), 2000);
        },

        stop() {
            if (this.pollingInterval) clearInterval(this.pollingInterval);
        },

        on(callback) {
            this.listeners.push(callback);
        },

        async loadInitial() {
            try {
                const res = await fetch(`/api/events/latest?user_id=${this.userId}&limit=30`);
                const data = await res.json();
                if (data.events && data.events.length > 0) {
                    this.lastEventId = Math.max(...data.events.map(e => e.id));
                }
            } catch (e) { console.error('Events load error:', e); }
        },

        async poll() {
            if (!this.userId) return;
            try {
                const res = await fetch(`/api/events/latest?user_id=${this.userId}&after_id=${this.lastEventId}`);
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); }
                catch (e) {
                    console.error('Non-JSON response:', text.slice(0, 200));
                    return;
                }

                if (data.events && data.events.length > 0) {
                    data.events.forEach(e => {
                        if (e.id > this.lastEventId) this.lastEventId = e.id;
                    });
                    this.listeners.forEach(cb => {
                        try { cb(data.events); } catch (e) { console.error('Listener error:', e); }
                    });
                }
            } catch (e) { console.error('Events poll error:', e); }
        }
    };

    // ================================================================
    //            JOURNAL — визуализация событий (подписчик)
    // ================================================================
    window.Journal = {
        init() {
            EventPoller.on((events) => {
                events.forEach(e => this.addEvent(e, true));
            });
        },

        async loadInitial() {
            try {
                const res = await fetch(`/api/events/latest?user_id=${EventPoller.userId}&limit=30`);
                const data = await res.json();
                if (data.events && data.events.length > 0) {
                    this.renderAll(data.events);
                } else {
                    const list = document.getElementById('eventsList');
                    if (list) list.innerHTML = '<div class="events-empty">Пока нет событий.<br>Начни играть!</div>';
                }
            } catch (e) { console.error('Journal load error:', e); }
        },

        renderAll(events) {
            const list = document.getElementById('eventsList');
            if (!list) return;
            list.innerHTML = '';
            events.forEach(e => this.addEvent(e, false));
            setTimeout(() => list.scrollTop = list.scrollHeight, 100);
        },

        addEvent(event, isNew) {
            const formatted = this.formatEvent(event);
            if (!formatted) return;

            const { icon, title, body } = formatted;
            const list = document.getElementById('eventsList');
            if (!list) return;
            const empty = list.querySelector('.events-empty');
            if (empty) empty.remove();

            const el = document.createElement('div');
            el.className = 'event-item' + (isNew ? ' new' : '');
            el.dataset.type = event.type;
            el.innerHTML = `
            <div class="event-header">
                <div>
                    <span class="event-icon">${icon}</span>
                    <span class="event-title">${title}</span>
                </div>
                <span class="event-time">${event.occurred_at}</span>
            </div>
            <div class="event-body">${body}</div>
        `;

            list.appendChild(el);

            // Навешиваем tooltip на все item-link в этом событии
            el.querySelectorAll('.item-link').forEach(link => {
                link.addEventListener('mouseenter', showItemTooltip);
                link.addEventListener('mouseleave', hideItemTooltip);
                link.addEventListener('mousemove', moveItemTooltip);
            });

            while (list.children.length > 50) list.removeChild(list.firstChild);

            if (isNew) {
                setTimeout(() => list.scrollTop = list.scrollHeight, 50);
            }
        },

        // ===== НОВЫЙ МЕТОД: рендерит название предмета как кликабельную ссылку =====
        renderItemLink(item) {
            if (!item || !item.template_id) {
                return `<b>${item?.template_name || item?.name || '???'}</b>`;
            }

            const safeDescription = (item.description || '').replace(/"/g, '&quot;');
            const safeStats = JSON.stringify(item.stats || {}).replace(/'/g, "&apos;");
            const safeName = (item.template_name || item.name || '???').replace(/"/g, '&quot;');

            return `<span class="item-link"
                 data-template-id="${item.template_id}"
                 data-instance-id="${item.instance_id || ''}"
                 data-name="${safeName}"
                 data-type="${item.template_type || 'material'}"
                 data-icon="${item.template_icon || getIcon(item.template_type || 'material')}"
                 data-description="${safeDescription}"
                 data-stats='${safeStats}'
                 data-quantity="${item.quantity || 1}">${safeName}</span>`;
        },

        formatEvent(event) {
            const p = event.payload || {};

            switch (event.type) {
                case 'user.registered':
                    return { icon: '🎉', title: 'Регистрация', body: `Создан герой <b>${p.username || '???'}</b><br>Стартовое золото: <b>${p.starting_gold || 0}</b>` };

                case 'user.gold_changed':
                    const delta = p.delta || 0;
                    const sign = delta > 0 ? '+' : '';
                    return { icon: '💰', title: 'Золото', body: `${sign}<b>${delta}</b> золота (${p.reason || ''})<br>Баланс: <b>${p.new_balance}</b>` };

                case 'item.received':
                    const qtyReceived = (p.quantity > 1) ? ` x${p.quantity}` : '';
                    return {
                        icon: '📥',
                        title: 'Получен предмет',
                        body: `${this.renderItemLink(p)}${qtyReceived}<br>${p.reason === 'stack_add' ? 'Добавлено к стаку' : 'Новый предмет'}`
                    };

                case 'item.removed':
                    const qtyRemoved = (p.quantity > 1) ? ` x${p.quantity}` : '';
                    if (p.reason === 'auction_list') {
                        return {
                            icon: '📢',
                            title: 'Выставлено на аукцион',
                            body: `${this.renderItemLink(p)}${qtyRemoved}<br>Ожидает покупателя`
                        };
                    }
                    return {
                        icon: '📤',
                        title: 'Предмет изъят',
                        body: `${this.renderItemLink(p)}${qtyRemoved}<br>Причина: ${p.reason || 'расход'}`
                    };

                case 'item.crafted':
                    const components = (p.components || []).map(c => {
                        const qty = (c.quantity > 1) ? ` x${c.quantity}` : '';
                        return `${this.renderItemLink(c)}${qty}`;
                    }).join(', ');
                    const resultQty = (p.quantity > 1) ? ` x${p.quantity}` : '';
                    return {
                        icon: '⚒️',
                        title: 'Крафт',
                        body: `<b>Получен:</b> ${this.renderItemLink(p.result || p)}${resultQty}<br><b>Использовано:</b> ${components || '???'}`
                    };

                case 'item.disassembled':
                    const mats = (p.materials || []).map(m => {
                        const qty = (m.quantity > 1) ? ` x${m.quantity}` : '';
                        return `${this.renderItemLink(m)}${qty}`;
                    }).join(', ');
                    return {
                        icon: '🔧',
                        title: 'Разборка',
                        body: `${this.renderItemLink({ template_name: p.item_name, template_type: p.item_type, template_icon: p.item_icon, description: p.description })}<br><b>Получено:</b> ${mats || '???'}`
                    };

                case 'auction.listed':
                    const qtyListed = (p.quantity > 1) ? ` x${p.quantity}` : '';
                    return {
                        icon: '📢',
                        title: 'Лот выставлен',
                        body: `${this.renderItemLink(p)}${qtyListed}<br>Цена: <b>${p.price}</b> 💰`
                    };

                case 'auction.purchase':
                    const qtyPurchase = p.quantity > 1 ? ` x${p.quantity}` : '';
                    return {
                        icon: '🛒',
                        title: 'Аукцион — покупка',
                        body: `<b>Получено:</b> ${this.renderItemLink(p)}${qtyPurchase}<br><b>Оплата:</b> 💰 ${p.payment_amount || 0} золота<br>Баланс: ${p.new_gold_balance || 0} 💰<br><span style="font-size:10px;color:#888">Продавец: ${p.seller_name || 'Неизвестный'}</span>`
                    };

                case 'auction.sale':
                    const qtySale = (p.quantity > 1) ? ` x${p.quantity}` : '';
                    return {
                        icon: '💰',
                        title: 'Аукцион — продажа',
                        body: `<b>Продано:</b> ${this.renderItemLink(p)}${qtySale}<br><b>Получено:</b> 💰 ${p.payment_amount} золота${p.commission > 0 ? `<br><span style="font-size:10px;color:#888">Комиссия: ${p.commission}</span>` : ''}<br>Баланс: ${p.new_gold_balance} 💰<br><span style="font-size:10px;color:#888">Покупатель: ${p.buyer_name}</span>`
                    };

                case 'auction.cancelled':
                    const qtyCancelled = (p.quantity > 1) ? ` x${p.quantity}` : '';
                    return {
                        icon: '❌',
                        title: 'Лот отменён',
                        body: `${this.renderItemLink(p)}${qtyCancelled}<br>Предмет возвращён`
                    };

                case 'trade.created':
                    return { icon: '🤝', title: 'Обмен создан', body: `Инициатор: <b>${p.initiator_name || p.initiator_id}</b><br>Партнёр: <b>${p.partner_name || p.partner_id}</b>` };

                case 'trade.accepted':
                    return { icon: '✅', title: 'Обмен подтверждён', body: `ID обмена: ${p.trade_id}` };

                case 'trade.completed':
                    const received = (p.received_items || []).map(i => {
                        const isStackable = i.template_type === 'material' || i.template_type === 'consumable';
                        const qty = (i.quantity > 1 && isStackable) ? ` x${i.quantity}` : '';
                        return `${this.renderItemLink(i)}${qty}`;
                    }).join(', ') || 'ничего';
                    const given = (p.given_items || []).map(i => {
                        const isStackable = i.template_type === 'material' || i.template_type === 'consumable';
                        const qty = (i.quantity > 1 && isStackable) ? ` x${i.quantity}` : '';
                        return `${this.renderItemLink(i)}${qty}`;
                    }).join(', ') || 'ничего';
                    return { icon: '✨', title: 'Обмен завершён', body: `С: <b>${p.opponent_name}</b><br><b>Получено:</b> ${received}<br><b>Отдано:</b> ${given}` };

                case 'trade.cancelled':
                    return { icon: '❌', title: 'Обмен отменён', body: `ID: ${p.trade_id}` };

                case 'trade.updated':
                    return { icon: '🔄', title: 'Обмен обновлён', body: `ID: ${p.trade_id}` };

                default:
                    return { icon: '📌', title: event.type, body: JSON.stringify(p).slice(0, 100) };
            }
        }
    };

    // ================================================================
    //       UI UPDATER — обновляет UI на основе событий (подписчик)
    // ================================================================
    window.UIUpdater = {
        init() {
            EventPoller.on((events) => this.handle(events));
        },

        handle(events) {
            let needsInventoryUpdate = false;
            let autoOpenTradeId = null;
            let needsTradeUpdate = false;
            let shouldCloseTradeWindow = false;

            events.forEach(e => {
                if (['item.received', 'item.removed', 'item.crafted', 'item.disassembled', 'user.gold_changed', 'auction.listed', 'auction.purchase', 'auction.sale', 'auction.cancelled', 'trade.completed'].includes(e.type)) {
                    needsInventoryUpdate = true;
                }

                if (e.type === 'trade.created' && e.payload.partner_id == GameState.userId) {
                    autoOpenTradeId = e.payload.trade_id;
                }
                if (['trade.created', 'trade.updated', 'trade.accepted', 'trade.completed', 'trade.cancelled'].includes(e.type)) {
                    needsTradeUpdate = true;
                }

                if ((e.type === 'trade.completed' || e.type === 'trade.cancelled') &&
                    typeof tradeState !== 'undefined' &&
                    tradeState.currentTrade &&
                    tradeState.currentTrade.id == e.payload.trade_id) {
                    shouldCloseTradeWindow = true;
                }
            });

            if (needsInventoryUpdate) {
                setTimeout(() => {
                    loadPlayerData();
                    if (GameState.currentTool === 'auction') {
                        if (typeof window.loadMarket === 'function') window.loadMarket();
                        if (typeof window.loadMyLots === 'function') window.loadMyLots();
                    }
                }, 100);
            }

            if (needsTradeUpdate || autoOpenTradeId) {
                setTimeout(async () => {
                    if (typeof window.loadTrades === 'function') await window.loadTrades();

                    if (autoOpenTradeId) {
                        if (typeof switchTool === 'function') switchTool('trade');
                        await new Promise(r => setTimeout(r, 150));
                        if (typeof openTrade === 'function') await openTrade(autoOpenTradeId);
                    } else if (shouldCloseTradeWindow) {
                        if (typeof window.closeTradeWindow === 'function') window.closeTradeWindow();
                    } else if (typeof tradeState !== 'undefined' && tradeState.currentTrade) {
                        if (typeof openTrade === 'function') await openTrade(tradeState.currentTrade.id);
                    }
                }, 200);
            }
        }
    };

    // ================================================================
    //                        ИНИЦИАЛИЗАЦИЯ
    // ================================================================
    document.addEventListener('DOMContentLoaded', () => {
        const userId = localStorage.getItem('userId');
        if (!userId) {
            window.location.href = '/';
            return;
        }

        GameState.userId = userId;
        loadPlayerData();
        switchTool('workbench');

        EventPoller.start(userId);

        Journal.init();
        Journal.loadInitial();
        UIUpdater.init();

        async function heartbeat() {
            try {
                await fetch('/api/heartbeat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: GameState.userId })
                });
            } catch (e) { console.error('Heartbeat error:', e); }
        }
        heartbeat();
        setInterval(heartbeat, 10000);
    });
</script>
@stack('scripts')
