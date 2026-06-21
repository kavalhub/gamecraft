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
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 8px;
        }
        .item {
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 8px; padding: 8px; text-align: center;
            cursor: grab; transition: all 0.2s; user-select: none;
        }
        .item:hover { border-color: #667eea; transform: translateY(-2px); }
        .item.dragging { opacity: 0.4; cursor: grabbing; }
        .item-icon { font-size: 28px; margin-bottom: 4px; }
        .item-name { font-size: 10px; font-weight: 600; }
        .item-qty { font-size: 12px; font-weight: 700; color: #fbbf24; }
        .item-type { font-size: 8px; color: #888; text-transform: uppercase; margin-top: 2px; }

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

<script>
    // ===== Глобальное состояние =====
    window.GameState = {
        userId: null,
        inventory: [],
        recipes: [],
        currentTool: 'workbench',
    };

    // ===== Утилиты =====
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

    // ===== Переключение инструментов =====
    function switchTool(tool) {
        GameState.currentTool = tool;
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tool === tool);
        });
        document.querySelectorAll('.tool-panel').forEach(panel => panel.style.display = 'none');
        const active = document.getElementById('tool-' + tool);
        if (active) active.style.display = 'block';
    }

    // ===== Загрузка данных игрока =====
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
        el.innerHTML = GameState.inventory.map(item => `
                <div class="item"
                     data-type="${item.type}"
                     data-instance-id="${item.instance_id}"
                     data-template-id="${item.template_id}"
                     data-quantity="${item.quantity}"
                     draggable="true">
                    <div class="item-icon">${getIcon(item.type)}</div>
                    <div class="item-name">${item.name}</div>
                    ${item.quantity > 1 ? `<div class="item-qty">x${item.quantity}</div>` : ''}
                    <div class="item-type">${item.type}</div>
                </div>
            `).join('');

        el.querySelectorAll('.item').forEach(itemEl => {
            itemEl.addEventListener('dragstart', onDragStart);
            itemEl.addEventListener('dragend', onDragEnd);
            itemEl.addEventListener('dblclick', onDoubleClick);
        });
    }

    // ===== Drag-and-Drop =====
    let draggedItem = null;

    function onDragStart(e) {
        draggedItem = {
            instance_id: parseInt(e.currentTarget.dataset.instanceId),
            template_id: parseInt(e.currentTarget.dataset.templateId),
            quantity: parseInt(e.currentTarget.dataset.quantity),
            type: e.currentTarget.dataset.type,
            name: e.currentTarget.querySelector('.item-name').textContent,
        };
        e.currentTarget.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');
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
            name: e.currentTarget.querySelector('.item-name').textContent,
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

    // ===== Event Sourcing =====
    window.GameEvents = {
        lastEventId: 0,
        pollingInterval: null,

        start(userId) {
            GameState.userId = userId;
            this.loadInitial();
            this.pollingInterval = setInterval(() => this.poll(), 2000);
        },

        stop() {
            if (this.pollingInterval) clearInterval(this.pollingInterval);
        },

        async loadInitial() {
            try {
                const res = await fetch(`/api/events/latest?user_id=${GameState.userId}&limit=30`);
                const data = await res.json();
                if (data.events && data.events.length > 0) {
                    this.lastEventId = Math.max(...data.events.map(e => e.id));
                    this.renderAll(data.events);
                } else {
                    document.getElementById('eventsList').innerHTML =
                        '<div class="events-empty">Пока нет событий.<br>Начни играть!</div>';
                }
            } catch (e) { console.error('Events load error:', e); }
        },

        async poll() {
            if (!GameState.userId) return;
            try {
                const res = await fetch(`/api/events/latest?user_id=${GameState.userId}&after_id=${this.lastEventId}`);
                const data = await res.json();

                if (data.events && data.events.length > 0) {
                    let needsInventoryUpdate = false;
                    let needsTradeUpdate = false;

                    data.events.forEach(e => {
                        this.addEvent(e, true);
                        if (e.id > this.lastEventId) this.lastEventId = e.id;

                        // Инвентарь и золото
                        if (['item.received', 'item.removed', 'item.crafted', 'item.disassembled', 'user.gold_changed', 'auction.listed', 'auction.purchase', 'auction.sale', 'auction.cancelled', 'trade.completed'].includes(e.type)) {
                            needsInventoryUpdate = true;
                        }

                        // Обмен
                        if (['trade.created', 'trade.updated', 'trade.accepted', 'trade.completed', 'trade.cancelled'].includes(e.type)) {
                            needsTradeUpdate = true;
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

                    if (needsTradeUpdate) {
                        setTimeout(async () => {
                            if (typeof window.loadTrades === 'function') await window.loadTrades();
                            // Если открыт обмен — обновляем его содержимое
                            if (typeof tradeState !== 'undefined' && tradeState.currentTrade) {
                                if (typeof openTrade === 'function') {
                                    await openTrade(tradeState.currentTrade.id);
                                }
                            }
                        }, 200);
                    }
                }
            } catch (e) { console.error('Events poll error:', e); }
        },

        renderAll(events) {
            const list = document.getElementById('eventsList');
            list.innerHTML = '';
            events.forEach(e => this.addEvent(e, false));
            setTimeout(() => list.scrollTop = list.scrollHeight, 100);
        },

        addEvent(event, isNew) {
            const list = document.getElementById('eventsList');
            const empty = list.querySelector('.events-empty');
            if (empty) empty.remove();

            const { icon, title, body } = this.formatEvent(event);

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
            while (list.children.length > 50) list.removeChild(list.firstChild);

            if (isNew) {
                setTimeout(() => list.scrollTop = list.scrollHeight, 50);
            }
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
                    return { icon: '📥', title: 'Получен предмет', body: `<b>${p.template_name || '???'}</b> x${p.quantity}<br>${p.reason === 'stack_add' ? 'Добавлено к стаку' : 'Новый предмет'}` };

                case 'item.removed':
                    if (p.reason === 'auction_list') {
                        return { icon: '📢', title: 'Выставлено на аукцион', body: `<b>${p.template_name || '???'}</b> x${p.quantity}<br>Ожидает покупателя` };
                    }
                    return { icon: '📤', title: 'Предмет изъят', body: `<b>${p.template_name || '???'}</b> x${p.quantity}<br>Причина: ${p.reason || 'расход'}` };

                case 'item.crafted':
                    const components = (p.components || []).map(c => `${c.name} x${c.quantity}`).join(', ');
                    return { icon: '⚒️', title: 'Крафт', body: `<b>Получен:</b> ${p.result_name} x${p.quantity}<br><b>Использовано:</b> ${components || '???'}` };

                case 'item.disassembled':
                    const mats = (p.materials || []).map(m => `${m.name} x${m.quantity}`).join(', ');
                    return { icon: '🔧', title: 'Разборка', body: `<b>Разобран:</b> ${p.item_name}<br><b>Получено:</b> ${mats || '???'}` };

                case 'auction.listed':
                    return { icon: '📢', title: 'Лот выставлен', body: `<b>${p.template_name || '???'}</b> x${p.quantity}<br>Цена: <b>${p.price}</b> 💰` };

                case 'auction.purchase':
                    return { icon: '🛒', title: 'Аукцион — покупка', body: `<b>Получено:</b> ${p.item_name} x${p.quantity}<br><b>Оплата:</b> 💰 ${p.payment_amount} золота<br>Баланс: ${p.new_gold_balance} 💰<br><span style="font-size:10px;color:#888">Продавец: ${p.seller_name}</span>` };

                case 'auction.sale':
                    return { icon: '💰', title: 'Аукцион — продажа', body: `<b>Продано:</b> ${p.item_name} x${p.quantity}<br><b>Получено:</b> 💰 ${p.payment_amount} золота${p.commission > 0 ? `<br><span style="font-size:10px;color:#888">Комиссия: ${p.commission}</span>` : ''}<br>Баланс: ${p.new_gold_balance} 💰<br><span style="font-size:10px;color:#888">Покупатель: ${p.buyer_name}</span>` };

                case 'auction.cancelled':
                    return { icon: '❌', title: 'Лот отменён', body: `<b>${p.template_name}</b> x${p.quantity}<br>Предмет возвращён` };

                case 'trade.created':
                    return { icon: '🤝', title: 'Обмен создан', body: `ID: ${p.trade_id}<br>Инициатор: ${p.initiator_id} → Партнёр: ${p.partner_id}` };

                case 'trade.accepted':
                    return { icon: '✅', title: 'Обмен подтверждён', body: `Сторона: ${p.side}<br>ID обмена: ${p.trade_id}` };

                case 'trade.completed':
                    const received = (p.received_items || []).map(i => `${i.name} x${i.quantity}`).join(', ') || 'ничего';
                    const given = (p.given_items || []).map(i => `${i.name} x${i.quantity}`).join(', ') || 'ничего';
                    const goldText = p.gold_delta !== 0 ? `<br>Золото: <b>${p.gold_delta > 0 ? '+' : ''}${p.gold_delta}</b> 💰` : '';
                    return { icon: '✨', title: 'Обмен завершён', body: `С: <b>${p.opponent_name}</b><br><b>Получено:</b> ${received}<br><b>Отдано:</b> ${given}${goldText}` };

                case 'trade.cancelled':
                    return { icon: '❌', title: 'Обмен отменён', body: `ID: ${p.trade_id}` };

                case 'trade.updated':
                    return { icon: '🔄', title: 'Обмен обновлён', body: `ID: ${p.trade_id}` };

                default:
                    return { icon: '📌', title: event.type, body: JSON.stringify(p).slice(0, 100) };
            }
        }
    };

    // ===== Инициализация =====
    document.addEventListener('DOMContentLoaded', () => {
        const userId = localStorage.getItem('userId');
        if (!userId) {
            window.location.href = '/';
            return;
        }

        GameState.userId = userId;
        loadPlayerData();
        switchTool('workbench');
        GameEvents.start(userId);

        // Heartbeat
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
</body>
</html>
