<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Крафт-Мир')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh; color: #eee; overflow: hidden;
        }

        .game-canvas {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 80px;
            overflow: hidden;
        }

        .game-background {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(102,126,234,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(168,85,247,0.1) 0%, transparent 50%),
                linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }

        .window {
            position: absolute;
            background: rgba(20, 20, 35, 0.98);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            display: none;
            flex-direction: column;
            min-width: 300px;
            min-height: 200px;
            overflow: hidden;
        }

        .window.active {
            display: flex;
            animation: windowAppear 0.2s ease-out;
        }

        @keyframes windowAppear {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .window.focused {
            border-color: rgba(102,126,234,0.5);
            box-shadow: 0 15px 50px rgba(0,0,0,0.7), 0 0 0 1px rgba(102,126,234,0.3);
        }

        .window-header {
            padding: 12px 16px;
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: move;
            user-select: none;
        }

        .window-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #d4a574;
        }

        .window-title .icon {
            font-size: 18px;
        }

        .window-controls {
            display: flex;
            gap: 6px;
        }

        .window-btn {
            width: 28px; height: 28px;
            border-radius: 6px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: #aaa;
            font-size: 14px;
        }

        .window-btn:hover {
            background: rgba(239,68,68,0.3);
            border-color: rgba(239,68,68,0.5);
            color: #ef4444;
        }

        .window-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

        .window.dragging {
            opacity: 0.8;
        }

        .window.dragging .window-header {
            cursor: grabbing;
        }

        #window-journal {
            width: 340px;
            height: calc(100% - 40px);
        }

        #window-journal .window-body {
            padding: 10px;
        }

        #window-inventory {
            width: 360px;
            height: calc(100% - 40px);
        }

        #window-inventory .player-bar {
            padding: 12px 16px;
            background: rgba(255,255,255,0.03);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #window-inventory .player-name { font-size: 16px; font-weight: 600; }
        #window-inventory .gold { font-size: 15px; color: #fbbf24; font-weight: 700; }

        #window-inventory .items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 8px;
        }

        #window-inventory .item {
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
            position: relative;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #window-inventory .item:hover { border-color: #667eea; transform: translateY(-2px); }
        #window-inventory .item-icon { font-size: 32px; }
        #window-inventory .item-qty {
            position: absolute;
            bottom: 2px;
            right: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #fbbf24;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
        }

        #window-workbench {
            width: 900px;
            height: 650px;
        }

        #window-auction {
            width: 900px;
            height: 650px;
        }

        #window-trade {
            width: 800px;
            height: 600px;
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            height: 100%;
        }

        .event-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-left: 3px solid #667eea;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 12px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .event-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .event-icon { font-size: 16px; margin-right: 6px; }
        .event-title { font-weight: 600; font-size: 12px; }
        .event-time { font-size: 10px; color: #888; font-family: monospace; }
        .event-body { color: #bbb; font-size: 11px; line-height: 1.5; margin-top: 4px; }
        .event-body b { color: #fbbf24; }

        .events-empty { text-align: center; padding: 40px 20px; color: #666; font-size: 12px; }

        .toolbar {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 80px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            padding: 0 30px;
            z-index: 10000;
        }

        .tool-btn {
            width: 60px; height: 60px;
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            gap: 4px;
            color: #eee;
        }
        .tool-btn:hover {
            background: rgba(255,255,255,0.1);
            border-color: #667eea;
            transform: translateY(-3px);
        }
        .tool-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        .tool-btn .icon { font-size: 24px; }
        .tool-btn .label { font-size: 10px; font-weight: 600; }

        .toolbar-separator {
            width: 1px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            margin: 0 8px;
        }

        .msg {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 100000;
            display: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            max-width: 700px;
            min-width: 300px;
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

        /* Tooltip */
        .tooltip {
            position: fixed;
            background: rgba(20, 20, 35, 0.98);
            border: 2px solid rgba(102, 126, 234, 0.6);
            border-radius: 8px;
            padding: 12px 15px;
            max-width: 300px;
            z-index: 99999;
            pointer-events: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            display: none;
            font-size: 13px;
        }
        .tooltip.visible {
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
        .tooltip-icon { font-size: 28px; }
        .tooltip-name { font-weight: 700; font-size: 15px; color: #fff; }
        .tooltip-type { font-size: 11px; color: #888; text-transform: uppercase; margin-top: 2px; }
        .tooltip-description { color: #bbb; font-size: 12px; line-height: 1.4; margin-bottom: 8px; font-style: italic; }
        .tooltip-stats { display: flex; flex-direction: column; gap: 4px; }
        .tooltip-stat { display: flex; justify-content: space-between; align-items: center; font-size: 12px; }
        .tooltip-stat-label { color: #888; }
        .tooltip-stat-value { color: #10b981; font-weight: 600; }
        .tooltip-quantity { margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.1); text-align: center; font-size: 14px; color: #fbbf24; font-weight: 700; }

        /* Mobile adaptation */
        @media (max-width: 768px) {
            .game-canvas {
                bottom: 60px;
            }
            .toolbar {
                height: 60px;
                gap: 8px;
                padding: 0 10px;
            }
            .tool-btn {
                width: 50px;
                height: 50px;
            }
            .tool-btn .icon { font-size: 20px; }
            .tool-btn .label { font-size: 9px; }
            .toolbar-separator { height: 30px; margin: 0 4px; }

            .window {
                width: 100% !important;
                height: calc(100% - 60px) !important;
                left: 0 !important;
                top: 0 !important;
                right: 0 !important;
                border-radius: 0;
            }
            .window-header {
                cursor: default;
            }
            #window-journal, #window-inventory {
                width: 100% !important;
                height: calc(100% - 60px) !important;
            }
        }
    </style>
    @stack('styles')
</head>
<body>

<div class="game-canvas" id="gameCanvas">
    <div class="game-background"></div>

    <div class="window" id="window-journal" data-window="journal">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">📜</span>
                <span>Журнал событий</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('journal')">✕</div>
            </div>
        </div>
        <div class="window-body">
            <div class="events-list" id="eventsList">
                <div class="events-empty">Загрузка...</div>
            </div>
        </div>
    </div>

    <div class="window" id="window-inventory" data-window="inventory">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">🎒</span>
                <span>Инвентарь</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('inventory')">✕</div>
            </div>
        </div>
        <div class="player-bar">
            <div class="player-name" id="playerName">Загрузка...</div>
            <div class="gold" id="playerGold">💰 0</div>
        </div>
        <div class="window-body">
            <div id="inventoryContent" class="items"></div>
        </div>
    </div>

    <div class="window" id="window-workbench" data-window="workbench">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">🔨</span>
                <span>Верстак</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('workbench')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.workbench')
        </div>
    </div>

    <div class="window" id="window-auction" data-window="auction">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">🏪</span>
                <span>Аукцион</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('auction')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.auction')
        </div>
    </div>

    <div class="window" id="window-trade" data-window="trade">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">🤝</span>
                <span>Обмен</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('trade')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.trade')
        </div>
    </div>
</div>

<nav class="toolbar">
    <div class="tool-btn" data-window="journal" onclick="WindowManager.toggle('journal')">
        <div class="icon">📜</div>
        <div class="label">Журнал</div>
    </div>
    <div class="tool-btn" data-window="inventory" onclick="WindowManager.toggle('inventory')">
        <div class="icon">🎒</div>
        <div class="label">Инвентарь</div>
    </div>
    <div class="toolbar-separator"></div>
    <div class="tool-btn" data-window="workbench" onclick="WindowManager.toggle('workbench')">
        <div class="icon">🔨</div>
        <div class="label">Верстак</div>
    </div>
    <div class="tool-btn" data-window="auction" onclick="WindowManager.toggle('auction')">
        <div class="icon">🏪</div>
        <div class="label">Аукцион</div>
    </div>
    <div class="tool-btn" data-window="trade" onclick="WindowManager.toggle('trade')">
        <div class="icon">🤝</div>
        <div class="label">Обмен</div>
    </div>
    <div class="toolbar-separator"></div>
    <div class="tool-btn" onclick="WindowManager.resetPositions()" title="Сбросить позиции окон">
        <div class="icon">🔄</div>
        <div class="label">Сброс</div>
    </div>
</nav>

<div id="msg" class="msg"></div>
<div id="itemTooltip" class="tooltip"></div>

<script>
    window.GameState = {
        characterUuid: null,
        inventory: [],
        recipes: [],
    };

    window.WindowManager = {
        windows: {},
        zIndex: 100,
        activeWindow: null,
        positions: {},

        defaults: {
            journal:    { x: 20,  y: 20 },
            inventory:  { x: null, y: 20, right: 20 },
            workbench:  { center: true, verticalCenter: true },
            auction:    { center: true, verticalCenter: true },
            trade:      { center: true, verticalCenter: true },
        },

        init() {
            console.log('WindowManager.init() called');
            document.querySelectorAll('.window').forEach(win => {
                const name = win.dataset.window;
                this.windows[name] = win;
                this.positionWindow(name);
                this.makeDraggable(win);
                win.addEventListener('mousedown', () => this.focus(name));
            });
        },

        positionWindow(name) {
            const win = this.windows[name];
            const defaults = this.defaults[name];
            if (!win || !defaults) return;

            const canvas = document.getElementById('gameCanvas');
            const canvasRect = canvas.getBoundingClientRect();

            if (defaults.center) {
                // Получаем размеры окна
                const rect = win.getBoundingClientRect();
                const winWidth = rect.width || parseInt(win.style.width) || 900;
                const winHeight = rect.height || parseInt(win.style.height) || 650;
                
                // Центрируем по горизонтали
                const left = Math.max(0, (canvasRect.width - winWidth) / 2);
                win.style.left = left + 'px';
                
                // Центрируем по вертикали с проверкой границ
                if (defaults.verticalCenter) {
                    // Если окно слишком большое - размещаем сверху с отступом
                    if (winHeight > canvasRect.height - 40) {
                        win.style.top = '20px';
                    } else {
                        const top = Math.max(0, (canvasRect.height - winHeight) / 2);
                        win.style.top = top + 'px';
                    }
                } else {
                    win.style.top = '20px';
                }
                
                // Убираем right если он был установлен
                win.style.right = '';
            } else {
                if (defaults.right !== undefined) {
                    win.style.right = defaults.right + 'px';
                } else if (defaults.x !== null) {
                    win.style.left = defaults.x + 'px';
                }
                if (defaults.y !== null) {
                    win.style.top = defaults.y + 'px';
                }
            }
        },

        makeDraggable(win) {
            const header = win.querySelector('.window-header');
            if (!header) {
                console.warn('No header for window:', win.dataset.window);
                return;
            }

            let isDragging = false;
            let startX, startY, startLeft, startTop;
            const winName = win.dataset.window;

            console.log('Making draggable:', winName);

            header.addEventListener('mousedown', (e) => {
                if (e.target.closest('.window-btn')) return;

                isDragging = true;
                win.classList.add('dragging');
                
                const rect = win.getBoundingClientRect();
                const canvasRect = document.getElementById('gameCanvas').getBoundingClientRect();
                
                startX = e.clientX;
                startY = e.clientY;
                startLeft = rect.left - canvasRect.left;
                startTop = rect.top - canvasRect.top;

                if (win.style.right) {
                    win.style.left = startLeft + 'px';
                    win.style.right = '';
                }

                console.log('Drag start:', winName, 'at', startLeft, startTop);
                e.preventDefault();
            });

            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;

                const canvasRect = document.getElementById('gameCanvas').getBoundingClientRect();
                let newX = startLeft + (e.clientX - startX);
                let newY = startTop + (e.clientY - startY);

                const rect = win.getBoundingClientRect();
                newX = Math.max(0, Math.min(newX, canvasRect.width - rect.width));
                newY = Math.max(0, Math.min(newY, canvasRect.height - rect.height));

                win.style.left = newX + 'px';
                win.style.top = newY + 'px';
            });

            document.addEventListener('mouseup', () => {
                if (!isDragging) return;
                
                isDragging = false;
                win.classList.remove('dragging');
                console.log('Drag end:', winName, '- saving positions');
                
                setTimeout(() => {
                    console.log('Calling savePositions for', winName);
                    window.WindowManager.savePositions();
                }, 100);
            });
        },

        toggle(name) {
            const win = this.windows[name];
            if (!win) return;

            if (win.classList.contains('active')) {
                this.close(name);
            } else {
                this.open(name);
            }
        },

        open(name) {
            const win = this.windows[name];
            if (!win) return;

            const keepOpen = ['journal', 'inventory'];
            if (!keepOpen.includes(name)) {
                Object.keys(this.windows).forEach(n => {
                    if (!keepOpen.includes(n) && n !== name) {
                        this.close(n);
                    }
                });
            }

            win.classList.add('active');
            this.focus(name);
            this.updateToolbar();

            // Применяем сохранённую позицию если есть
            if (this.positions[name]) {
                const pos = this.positions[name];
                win.style.left = pos.left + 'px';
                win.style.top = pos.top + 'px';
                win.style.right = '';
            }

            if (name === 'auction' && typeof window.initAuction === 'function') {
                setTimeout(() => window.initAuction(), 50);
            }
            if (name === 'workbench' && typeof window.initWorkbench === 'function') {
                setTimeout(() => window.initWorkbench(), 50);
            }
            if (name === 'trade' && typeof window.initTrade === 'function') {
                setTimeout(() => window.initTrade(), 50);
            }
        },

        close(name) {
            const win = this.windows[name];
            if (!win) return;

            win.classList.remove('active');
            win.classList.remove('focused');
            this.updateToolbar();
        },

        focus(name) {
            const win = this.windows[name];
            if (!win || !win.classList.contains('active')) return;

            Object.values(this.windows).forEach(w => w.classList.remove('focused'));
            win.classList.add('focused');
            win.style.zIndex = ++this.zIndex;
            this.activeWindow = name;
        },

        updateToolbar() {
            document.querySelectorAll('.tool-btn').forEach(btn => {
                const name = btn.dataset.window;
                const win = this.windows[name];
                if (win) {
                    btn.classList.toggle('active', win.classList.contains('active'));
                }
            });
        },

        isOpen(name) {
            const win = this.windows[name];
            return win && win.classList.contains('active');
        },

        async resetPositions() {
            if (!confirm('Сбросить все позиции окон?')) return;
            
            // Очищаем сохранённые позиции
            this.positions = {};
            
            // Сбрасываем стили
            Object.keys(this.windows).forEach(name => {
                const win = this.windows[name];
                win.style.left = '';
                win.style.top = '';
                win.style.right = '';
                this.positionWindow(name);
            });
            
            // Сохраняем пустые позиции
            await this.savePositions();
            
            showMsg('Позиции окон сброшены', 'success');
        },

        async loadPositions() {
            if (!GameState.characterUuid) {
                console.warn('Cannot load positions: characterUuid not set');
                return;
            }

            try {
                const res = await fetch(`/api/settings/${GameState.characterUuid}`);
                const data = await res.json();
                this.positions = data.settings?.window_positions || {};
                
                console.log('Loaded positions:', this.positions);
                
                // Применяем сохранённые позиции только для открытых окон
                Object.keys(this.positions).forEach(name => {
                    const win = this.windows[name];
                    if (!win) return;
                    const pos = this.positions[name];
                    
                    // Применяем только если координаты валидные
                    if (pos && typeof pos.left === 'number' && typeof pos.top === 'number') {
                        win.style.left = pos.left + 'px';
                        win.style.top = pos.top + 'px';
                        // Убираем right если он был установлен
                        win.style.right = '';
                    }
                });
            } catch (e) {
                console.error('Load positions error:', e);
            }
        },

        async savePositions() {
            if (!GameState.characterUuid) {
                console.warn('Cannot save positions: characterUuid not set');
                return;
            }

            const positions = {};
            Object.keys(this.windows).forEach(name => {
                const win = this.windows[name];
                // Сохраняем все окна, используя их текущие стили
                const left = parseInt(win.style.left);
                const top = parseInt(win.style.top);
                
                // Если окно позиционировано через left/top
                if (!isNaN(left) && !isNaN(top)) {
                    positions[name] = { left, top };
                } else if (win.classList.contains('active')) {
                    // Если окно открыто, используем getBoundingClientRect
                    const rect = win.getBoundingClientRect();
                    const canvasRect = document.getElementById('gameCanvas').getBoundingClientRect();
                    const calcLeft = Math.round(rect.left - canvasRect.left);
                    const calcTop = Math.round(rect.top - canvasRect.top);
                    
                    if (calcLeft >= 0 && calcTop >= 0) {
                        positions[name] = { left: calcLeft, top: calcTop };
                    }
                }
            });

            console.log('Saving positions:', positions);

            try {
                const res = await fetch(`/api/settings/${GameState.characterUuid}/multiple`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ settings: { window_positions: positions } })
                });
                const data = await res.json();
                console.log('Save result:', data);
            } catch (e) {
                console.error('Save positions error:', e);
            }
        }
    };

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

    async function loadPlayerData() {
        try {
            const res = await fetch(`/api/inventory/${GameState.characterUuid}`);
            const data = await res.json();

            document.getElementById('playerName').textContent = data.character_name;

            const goldResource = data.resources.find(r => r.template_slug === 'gold');
            const gold = goldResource ? goldResource.quantity : 0;
            document.getElementById('playerGold').textContent = '💰 ' + gold;

            GameState.inventory = [...data.resources, ...data.items];
            renderInventory();
        } catch (e) {
            console.error('Player data load error:', e);
        }
    }

    async function loadRecipes() {
        try {
            const res = await fetch(`/api/crafting/${GameState.characterUuid}/recipes`);
            const data = await res.json();
            GameState.recipes = data.recipes || [];
        } catch (e) {
            console.error('Recipes load error:', e);
        }
    }

    function renderInventory() {
        const el = document.getElementById('inventoryContent');
        if (!GameState.inventory.length) {
            el.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:30px;color:#666">Инвентарь пуст</div>';
            return;
        }
        el.innerHTML = GameState.inventory.map(item => {
            const icon = item.icon || (item.stage === 'blueprint' ? '📜' : '📦');
            const qty = item.quantity ? `<div class="item-qty">x${item.quantity}</div>` : '';
            return `
                <div class="item"
                     data-uuid="${item.uuid}"
                     data-name="${item.name}"
                     data-stage="${item.stage || ''}"
                     data-recipe-slug="${item.recipe_slug || ''}"
                     data-template-slug="${item.template_slug || ''}"
                     data-description="${item.description || ''}"
                     data-stats='${JSON.stringify(item.stats || {})}'
                     data-quantity="${item.quantity || 1}">
                    <div class="item-icon">${icon}</div>
                    ${qty}
                </div>
            `;
        }).join('');

        // Добавляю обработчики тултипа
        el.querySelectorAll('.item').forEach(itemEl => {
            itemEl.addEventListener('mouseenter', showItemTooltip);
            itemEl.addEventListener('mouseleave', hideItemTooltip);
            itemEl.addEventListener('mousemove', moveItemTooltip);
        });
    }

    function showItemTooltip(e) {
        const tooltip = document.getElementById('itemTooltip');
        const element = e.currentTarget;

        const name = element.dataset.name;
        const stage = element.dataset.stage;
        const quantity = parseInt(element.dataset.quantity) || 1;
        const description = element.dataset.description || '';
        const stats = JSON.parse(element.dataset.stats || '{}');
        const icon = element.querySelector('.item-icon').textContent;

        let type = 'Предмет';
        if (stage === 'blueprint') type = 'Чертёж';
        else if (stage === 'item') type = 'Предмет';
        else if (quantity > 1) type = 'Ресурс';

        let html = `
            <div class="tooltip-header">
                <div class="tooltip-icon">${icon}</div>
                <div>
                    <div class="tooltip-name">${name}</div>
                    <div class="tooltip-type">${type}</div>
                </div>
            </div>
        `;

        if (description) {
            html += `<div class="tooltip-description">${description}</div>`;
        }

        if (Object.keys(stats).length > 0) {
            html += '<div class="tooltip-stats">';
            for (const [key, value] of Object.entries(stats)) {
                const label = {
                    attack: 'Атака',
                    defense: 'Защита',
                    health: 'Здоровье',
                    durability: 'Прочность',
                    level: 'Уровень',
                    weight: 'Вес',
                    value: 'Ценность'
                }[key] || key;
                html += `
                    <div class="tooltip-stat">
                        <span class="tooltip-stat-label">${label}:</span>
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

    // ================================================================
    //                     EVENT POLLER
    // ================================================================
    window.EventPoller = {
        lastEventId: 0,
        pollingInterval: null,
        listeners: [],

        start(characterUuid) {
            this.characterUuid = characterUuid;
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
                const res = await fetch(`/api/events/${this.characterUuid}/latest?limit=30`);
                const data = await res.json();
                if (data.events && data.events.length > 0) {
                    this.lastEventId = Math.max(...data.events.map(e => e.id));
                }
            } catch (e) { console.error('Events load error:', e); }
        },

        async poll() {
            if (!this.characterUuid) return;
            try {
                const res = await fetch(`/api/events/${this.characterUuid}/latest?after_id=${this.lastEventId}`);
                const data = await res.json();
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
    //                     JOURNAL
    // ================================================================
    window.Journal = {
        init() {
            EventPoller.on((events) => {
                events.forEach(e => this.addEvent(e, true));
            });
        },

        async loadInitial() {
            try {
                const res = await fetch(`/api/events/${EventPoller.characterUuid}/latest?limit=30`);
                const data = await res.json();
                if (data.events && data.events.length > 0) {
                    const list = document.getElementById('eventsList');
                    list.innerHTML = '';
                    data.events.forEach(e => this.addEvent(e, false));
                } else {
                    const list = document.getElementById('eventsList');
                    list.innerHTML = '<div class="events-empty">Пока нет событий</div>';
                }
            } catch (e) { console.error('Journal load error:', e); }
        },

        addEvent(event, isNew) {
            const list = document.getElementById('eventsList');
            if (!list) return;
            const empty = list.querySelector('.events-empty');
            if (empty) empty.remove();

            const formatted = this.formatEvent(event);
            if (!formatted) return;

            const el = document.createElement('div');
            el.className = 'event-item';
            el.innerHTML = `
                <div class="event-header">
                    <div>
                        <span class="event-icon">${formatted.icon}</span>
                        <span class="event-title">${formatted.title}</span>
                    </div>
                    <span class="event-time">${event.occurred_at}</span>
                </div>
                <div class="event-body">${formatted.body}</div>
            `;
            list.appendChild(el);

            while (list.children.length > 50) list.removeChild(list.firstChild);
            if (isNew) list.scrollTop = list.scrollHeight;
        },

        formatEvent(event) {
            const p = event.payload || {};
            switch (event.type) {
                case 'user.registered':
                    return { icon: '🎉', title: 'Регистрация', body: `Создан герой <b>${p.username || '???'}</b>` };
                case 'item.received':
                    return { icon: '📥', title: 'Получен предмет', body: `<b>${p.name || p.template_slug || '???'}</b> x${p.quantity || 1}` };
                case 'item.removed':
                    return { icon: '📤', title: 'Предмет изъят', body: `<b>${p.name || p.template_slug || '???'}</b> x${p.quantity || 1}` };
                case 'item.crafted':
                    return { icon: '⚒️', title: 'Крафт', body: `Создан <b>${p.name || '???'}</b>` };
                case 'item.disassembled':
                    return { icon: '🔧', title: 'Разборка', body: `<b>${p.item_name || '???'}</b> разобран` };
                case 'auction.listed':
                    return { icon: '📢', title: 'Лот выставлен', body: `<b>${p.template_slug || '???'}</b> за ${p.price || 0} 💰` };
                case 'auction.purchase':
                    return { icon: '🛒', title: 'Покупка', body: `Куплено <b>${p.template_slug || '???'}</b> за ${p.payment_amount || 0} 💰` };
                case 'auction.sale':
                    return { icon: '💰', title: 'Продажа', body: `Продано <b>${p.template_slug || '???'}</b> за ${p.payment_amount || 0} 💰` };
                case 'auction.cancelled':
                    return { icon: '❌', title: 'Лот отменён', body: `<b>${p.template_slug || '???'}</b>` };
                case 'trade.created':
                    return { icon: '🤝', title: 'Обмен создан', body: `С <b>${p.partner_name || '???'}</b>` };
                case 'trade.completed':
                    return { icon: '✨', title: 'Обмен завершён', body: `С <b>${p.partner_name || '???'}</b>` };
                case 'trade.cancelled':
                    return { icon: '❌', title: 'Обмен отменён', body: `ID: ${p.trade_uuid || '???'}` };
                default:
                    return { icon: '📌', title: event.type, body: JSON.stringify(p).slice(0, 100) };
            }
        }
    };

    // ================================================================
    //                     UI UPDATER
    // ================================================================
    window.UIUpdater = {
        init() {
            EventPoller.on((events) => this.handle(events));
        },

        handle(events) {
            let needsInventoryUpdate = false;
            let needsAuctionUpdate = false;
            let needsTradeUpdate = false;

            events.forEach(e => {
                if (['item.received', 'item.removed', 'item.crafted', 'item.disassembled'].includes(e.type)) {
                    needsInventoryUpdate = true;
                }
                if (['auction.listed', 'auction.purchase', 'auction.sale', 'auction.cancelled'].includes(e.type)) {
                    needsInventoryUpdate = true;
                    needsAuctionUpdate = true;
                }
                if (['trade.created', 'trade.updated', 'trade.completed', 'trade.cancelled'].includes(e.type)) {
                    needsTradeUpdate = true;
                }
            });

            if (needsInventoryUpdate) {
                setTimeout(() => loadPlayerData(), 100);
            }
            if (needsAuctionUpdate && WindowManager.isOpen('auction')) {
                setTimeout(() => {
                    if (typeof window.loadMarket === 'function') window.loadMarket();
                    if (typeof window.loadMyLots === 'function') window.loadMyLots();
                }, 150);
            }
            if (needsTradeUpdate && WindowManager.isOpen('trade')) {
                setTimeout(() => {
                    if (typeof window.loadTrades === 'function') window.loadTrades();
                }, 150);
            }
        }
    };

    document.addEventListener('DOMContentLoaded', async () => {
        const characterUuid = localStorage.getItem('characterUuid');
        if (!characterUuid) {
            window.location.href = '/';
            return;
        }

        GameState.characterUuid = characterUuid;

        WindowManager.init();
        await WindowManager.loadPositions();

        await Promise.all([loadPlayerData(), loadRecipes()]);

        WindowManager.open('journal');
        WindowManager.open('inventory');

        // Запускаем polling событий
        EventPoller.start(characterUuid);
        Journal.init();
        Journal.loadInitial();
        UIUpdater.init();

        // Heartbeat каждые 30 секунд
        setInterval(() => {
            fetch(`/api/heartbeat/${characterUuid}`, { method: 'POST' }).catch(() => {});
        }, 30000);
        // Сразу отправляем
        fetch(`/api/heartbeat/${characterUuid}`, { method: 'POST' }).catch(() => {});

        const inventoryContent = document.getElementById('inventoryContent');
        inventoryContent.addEventListener('dblclick', (e) => {
            const itemEl = e.target.closest('.item');
            if (!itemEl) return;

            const uuid = itemEl.dataset.uuid;
            const item = GameState.inventory.find(i => i.uuid === uuid);
            if (!item) return;

            const fullItem = {
                ...item,
                uuid: itemEl.dataset.uuid,
                name: itemEl.dataset.name,
                stage: itemEl.dataset.stage || item.stage,
                recipe_slug: itemEl.dataset.recipeSlug || item.recipe_slug,
                template_slug: itemEl.dataset.templateSlug || item.template_slug,
            };

            const activeWindows = ['workbench', 'auction', 'trade'].filter(w => WindowManager.isOpen(w));
            const activeWindow = activeWindows[0];

            if (activeWindow === 'workbench' && typeof handleWorkbenchDrop === 'function') {
                handleWorkbenchDrop(fullItem);
            } else if (activeWindow === 'auction' && typeof handleAuctionDrop === 'function') {
                handleAuctionDrop(fullItem);
            } else if (activeWindow === 'trade' && typeof handleTradeDrop === 'function') {
                handleTradeDrop(fullItem);
            } else {
                if (fullItem.stage === 'blueprint' || fullItem.stage === 'item') {
                    WindowManager.open('workbench');
                    setTimeout(() => {
                        if (typeof handleWorkbenchDrop === 'function') {
                            handleWorkbenchDrop(fullItem);
                        }
                    }, 100);
                }
            }
        });
    });
</script>
@stack('scripts')
