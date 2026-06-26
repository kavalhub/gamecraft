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

        .game-layout {
            display: grid;
            grid-template-columns: 320px 1fr 350px;
            grid-template-rows: 1fr 80px;
            height: 100vh;
            gap: 10px;
            padding: 10px;
        }

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
        }

        .event-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .event-icon { font-size: 16px; margin-right: 6px; }
        .event-title { font-weight: 600; font-size: 12px; }
        .event-time { font-size: 10px; color: #888; font-family: monospace; }
        .event-body { color: #bbb; font-size: 11px; line-height: 1.5; margin-top: 4px; }
        .event-body b { color: #fbbf24; }

        .center-panel {
            grid-row: 1; grid-column: 2;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
            display: flex; flex-direction: column;
        }
        .center-content { flex: 1; overflow-y: auto; padding: 20px; }

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
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
            position: relative;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .item:hover { border-color: #667eea; transform: translateY(-2px); }
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
        .tool-btn:hover { background: rgba(255,255,255,0.1); border-color: #667eea; }
        .tool-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
        }
        .tool-btn .icon { font-size: 24px; }
        .tool-btn .label { font-size: 10px; font-weight: 600; }

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
    </style>
    @stack('styles')
</head>
<body>
<div class="game-layout">
    <aside class="journal-panel">
        <div class="panel-header">
            <h2>📜 Журнал</h2>
        </div>
        <div class="events-list" id="eventsList">
            <div style="text-align:center;padding:40px;color:#666">Загрузка...</div>
        </div>
    </aside>

    <main class="center-panel">
        <div class="center-content" id="centerContent">
            @yield('center')
        </div>
    </main>

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
    window.GameState = {
        characterUuid: null,
        inventory: [],
        recipes: [],
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

    function switchTool(tool) {
        document.querySelectorAll('.tool-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tool === tool);
        });
        document.querySelectorAll('.tool-panel').forEach(panel => panel.style.display = 'none');
        const active = document.getElementById('tool-' + tool);
        if (active) active.style.display = 'block';
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
                     data-template-slug="${item.template_slug || ''}">
                    <div class="item-icon">${icon}</div>
                    ${qty}
                </div>
            `;
        }).join('');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const characterUuid = localStorage.getItem('characterUuid');
        if (!characterUuid) {
            window.location.href = '/';
            return;
        }

        GameState.characterUuid = characterUuid;
        loadPlayerData();
        loadRecipes();
        switchTool('workbench');
    });

    async function loadRecipes() {
        try {
            const res = await fetch(`/api/crafting/${GameState.characterUuid}/recipes`);
            const data = await res.json();
            GameState.recipes = data.recipes || [];
        } catch (e) {
            console.error('Recipes load error:', e);
        }
    }
</script>
@stack('scripts')
