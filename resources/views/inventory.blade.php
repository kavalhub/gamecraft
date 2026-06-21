<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Крафт-Игра - Верстак</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh; padding: 20px; color: #eee;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: rgba(255,255,255,0.05); border-radius: 15px;
            padding: 20px 30px; margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .player-info { display: flex; align-items: center; gap: 20px; }
        .player-name { font-size: 24px; font-weight: 600; }
        .gold { font-size: 20px; color: #fbbf24; font-weight: 600; }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }

        /* Инвентарь */
        .inventory-panel {
            background: rgba(255,255,255,0.05);
            border-radius: 15px; padding: 25px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .panel-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; }
        .items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
        }
        .item {
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 10px; padding: 10px; text-align: center;
            cursor: grab; transition: all 0.2s;
            user-select: none;
        }
        .item:hover { border-color: #667eea; transform: translateY(-2px); }
        .item.dragging { opacity: 0.4; cursor: grabbing; }
        .item-icon { font-size: 32px; margin-bottom: 5px; }
        .item-name { font-size: 11px; font-weight: 600; }
        .item-qty { font-size: 13px; font-weight: 700; color: #fbbf24; }
        .item-type { font-size: 9px; color: #888; text-transform: uppercase; margin-top: 3px; }

        .item[data-type="recipe"] { border-color: rgba(168,85,247,0.4); background: rgba(168,85,247,0.1); }
        .item[data-type="equipment"] { border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.05); }
        .item[data-type="material"] { border-color: rgba(251,191,36,0.3); background: rgba(251,191,36,0.05); }

        /* Верстак */
        .workbench {
            background: linear-gradient(135deg, rgba(139,69,19,0.2), rgba(101,67,33,0.2));
            border: 2px solid rgba(139,69,19,0.5);
            border-radius: 15px; padding: 25px;
            position: sticky; top: 20px;
        }
        .workbench-title {
            font-size: 20px; font-weight: 700; text-align: center;
            margin-bottom: 20px; color: #d4a574;
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .slot {
            aspect-ratio: 1;
            background: rgba(0,0,0,0.3);
            border: 2px dashed rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 8px; text-align: center;
            transition: all 0.2s;
            min-height: 90px;
        }
        .slot.drag-over {
            border-color: #667eea;
            background: rgba(102,126,234,0.2);
            transform: scale(1.05);
        }
        .slot.filled {
            border-style: solid;
            background: rgba(255,255,255,0.08);
        }
        .slot-label {
            font-size: 10px; color: #888;
            text-transform: uppercase; margin-bottom: 5px;
        }
        .slot-content { font-size: 28px; }
        .slot-name { font-size: 10px; margin-top: 3px; }
        .slot-qty { font-size: 11px; color: #fbbf24; font-weight: 700; }

        .center-row {
            display: grid;
            grid-template-columns: 1fr 1.5fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }
        .center-slot {
            aspect-ratio: 1;
            background: rgba(168,85,247,0.1);
            border: 2px dashed rgba(168,85,247,0.5);
            border-radius: 12px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 10px; text-align: center;
            transition: all 0.2s;
        }
        .center-slot.drag-over {
            border-color: #a855f7;
            background: rgba(168,85,247,0.3);
            transform: scale(1.05);
        }
        .center-slot.filled {
            border-style: solid;
            background: rgba(168,85,247,0.2);
        }

        .result-slot {
            background: rgba(16,185,129,0.1);
            border: 2px dashed rgba(16,185,129,0.5);
            border-radius: 12px;
            padding: 15px; text-align: center;
            margin-bottom: 15px;
            min-height: 90px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
        }
        .result-slot.ready {
            border-style: solid;
            background: rgba(16,185,129,0.2);
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); }
            50% { box-shadow: 0 0 20px 5px rgba(16,185,129,0.2); }
        }

        .actions { display: flex; gap: 10px; }
        .btn {
            flex: 1; padding: 12px; border: none; border-radius: 8px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: all 0.2s; text-transform: uppercase;
        }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-craft { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-craft:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16,185,129,0.4); }
        .btn-disassemble { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-disassemble:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(239,68,68,0.4); }
        .btn-clear { background: rgba(255,255,255,0.1); color: #aaa; }
        .btn-clear:hover { background: rgba(255,255,255,0.2); }

        .msg {
            padding: 12px; border-radius: 8px; margin-bottom: 15px;
            display: none; font-size: 14px; text-align: center;
        }
        .msg.success { background: rgba(16,185,129,0.2); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .msg.error { background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .msg.show { display: block; }

        .hint {
            text-align: center; font-size: 12px; color: #888;
            margin-top: 10px; font-style: italic;
        }

        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
            .workbench { position: static; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="player-info">
            <div class="player-name" id="playerName">Загрузка...</div>
            <div class="gold" id="playerGold">💰 0</div>
        </div>
    </div>

    <div id="msg" class="msg"></div>

    <div class="main-grid">
        <!-- Инвентарь -->
        <div class="inventory-panel">
            <div class="panel-title">🎒 Инвентарь <span style="font-size:12px;color:#888">(перетащи предмет на верстак)</span></div>
            <div id="inventoryContent" class="items"></div>
        </div>

        <!-- Верстак -->
        <div class="workbench">
            <div class="workbench-title">🔨 Верстак</div>

            <!-- Центральный слот (рецепт или предмет для разборки) -->
            <div class="center-slot" id="centerSlot" data-slot-type="center">
                <div class="slot-label">Рецепт / Предмет</div>
                <div class="slot-content">?</div>
                <div class="slot-name" style="color:#888;font-size:11px">Перетащи или кликни дважды</div>
            </div>

            <!-- Режим крафта: список ингредиентов + количество -->
            <div id="craftMode" style="display:none">
                <div style="margin:20px 0">
                    <div style="font-size:14px;font-weight:600;margin-bottom:10px;color:#d4a574">📋 Необходимые ингредиенты:</div>
                    <div id="ingredientsList" style="background:rgba(0,0,0,0.2);border-radius:8px;padding:12px"></div>
                </div>

                <div style="margin:15px 0">
                    <label style="display:block;font-size:13px;margin-bottom:6px;color:#aaa">Количество:</label>
                    <input type="number" id="craftQuantity" value="1" min="1" max="99"
                           style="width:100%;padding:10px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:16px">
                </div>

                <!-- Результат -->
                <div class="result-slot" id="resultSlot">
                    <div class="slot-label">Будет создано</div>
                    <div class="slot-content">—</div>
                </div>

                <div class="actions">
                    <button class="btn btn-craft" id="btnCraft">⚒️ Создать</button>
                </div>
                <div class="actions" style="margin-top:10px">
                    <button class="btn btn-clear" onclick="clearWorkbench()">🗑️ Очистить</button>
                </div>
            </div>

            <!-- Режим разборки -->
            <div id="disassembleMode" style="display:none">
                <div style="margin:20px 0">
                    <div style="font-size:14px;font-weight:600;margin-bottom:10px;color:#d4a574">🔧 Будет получено:</div>
                    <div id="disassembleResult" style="background:rgba(0,0,0,0.2);border-radius:8px;padding:12px"></div>
                </div>

                <div class="actions">
                    <button class="btn btn-disassemble" id="btnDisassemble">🔧 Разобрать</button>
                </div>
                <div class="actions" style="margin-top:10px">
                    <button class="btn btn-clear" onclick="clearWorkbench()">🗑️ Очистить</button>
                </div>
            </div>

            <!-- Пустое состояние -->
            <div id="emptyMode">
                <div class="hint">Положите чертёж в центр для крафта или предмет для разборки</div>
            </div>
        </div>
    </div>
</div>

<script>
    const userId = localStorage.getItem('userId');
    if (!userId) { window.location.href = '/'; }

    let inventory = [];
    let recipes = [];
    let workbenchState = {
        center: null,
        mode: null,
        recipe: null,
        quantity: 1,
    };

    function showMsg(text, type) {
        const el = document.getElementById('msg');
        el.textContent = text;
        el.className = `msg ${type} show`;
        setTimeout(() => el.classList.remove('show'), 3000);
    }

    function getIcon(type) {
        return { material: '📦', equipment: '⚔️', consumable: '🧪', recipe: '📜' }[type] || '📦';
    }

    async function loadAll() {
        try {
            const [invRes, recRes] = await Promise.all([
                fetch(`/api/inventory?user_id=${userId}`, { headers: { 'Accept': 'application/json' } }),
                fetch('/api/recipes', { headers: { 'Accept': 'application/json' } })
            ]);
            const invData = await invRes.json();
            const recData = await recRes.json();

            if (invData.user) {
                document.getElementById('playerName').textContent = invData.user.username;
                document.getElementById('playerGold').textContent = '💰 ' + invData.user.gold;
            }

            inventory = invData.inventory || [];
            recipes = recData.recipes || [];
            renderInventory();
            renderWorkbench();
        } catch (e) {
            showMsg('Ошибка загрузки: ' + e.message, 'error');
        }
    }

    function renderInventory() {
        const el = document.getElementById('inventoryContent');
        if (!inventory.length) {
            el.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:30px;color:#666">Инвентарь пуст</div>';
            return;
        }
        el.innerHTML = inventory.map(item => `
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
        handleCenterDrop(item);
    }

    function setupDropZone(el, onDrop) {
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

    function renderWorkbench() {
        const craftMode = document.getElementById('craftMode');
        const disassembleMode = document.getElementById('disassembleMode');
        const emptyMode = document.getElementById('emptyMode');
        const centerSlot = document.getElementById('centerSlot');

        craftMode.style.display = 'none';
        disassembleMode.style.display = 'none';
        emptyMode.style.display = 'none';

        if (workbenchState.mode === 'craft') {
            craftMode.style.display = 'block';
            renderCenterSlot();
            renderIngredientsList();
            renderResultSlot();
        } else if (workbenchState.mode === 'disassemble') {
            disassembleMode.style.display = 'block';
            renderCenterSlot();
            renderDisassembleResult();
        } else {
            emptyMode.style.display = 'block';
            centerSlot.classList.remove('filled');
            centerSlot.innerHTML = `
                <div class="slot-label">Рецепт / Предмет</div>
                <div class="slot-content">?</div>
                <div class="slot-name" style="color:#888;font-size:11px">Перетащи или кликни дважды</div>
            `;
        }
    }

    function renderCenterSlot() {
        const slot = document.getElementById('centerSlot');
        if (workbenchState.center) {
            const item = workbenchState.center;
            slot.classList.add('filled');
            slot.innerHTML = `
                <div class="slot-label">${workbenchState.mode === 'craft' ? 'Чертёж' : 'Предмет'}</div>
                <div class="slot-content">${getIcon(item.type)}</div>
                <div class="slot-name">${item.name}</div>
            `;
        }
    }

    function renderIngredientsList() {
        if (!workbenchState.recipe) return;

        const container = document.getElementById('ingredientsList');
        const components = workbenchState.recipe.components;
        const qty = workbenchState.quantity;

        container.innerHTML = components.map(comp => {
            const needed = comp.quantity * qty;
            const invItem = inventory.find(i => i.template_id === comp.template_id);
            const available = invItem ? invItem.quantity : 0;
            const isEnough = available >= needed;

            return `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;margin-bottom:6px;background:rgba(255,255,255,0.05);border-radius:6px">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-size:20px">${getIcon('material')}</span>
                        <span style="font-size:13px">${comp.name}</span>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:14px;font-weight:700;color:${isEnough ? '#10b981' : '#ef4444'}">
                            ${available} / ${needed}
                        </div>
                        <div style="font-size:10px;color:#888">${isEnough ? '✅ Достаточно' : '❌ Не хватает'}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderResultSlot() {
        const slot = document.getElementById('resultSlot');
        if (!workbenchState.recipe) return;

        const r = workbenchState.recipe.result;
        const qty = workbenchState.quantity;
        const canCraft = canCraftNow();

        slot.classList.toggle('ready', canCraft);
        slot.innerHTML = `
            <div class="slot-label">Будет создано</div>
            <div class="slot-content">${getIcon('equipment')}</div>
            <div class="slot-name">${r.name}</div>
            <div class="slot-qty">x${r.quantity * qty}</div>
        `;
    }

    function renderDisassembleResult() {
        if (!workbenchState.center) return;

        const container = document.getElementById('disassembleResult');
        const invItem = inventory.find(i => i.instance_id === workbenchState.center.instance_id);

        if (!invItem) {
            container.innerHTML = '<div style="color:#888">Предмет не найден</div>';
            return;
        }

        const template = inventory.find(i => i.template_id === invItem.template_id);
        const templateData = findTemplateById(invItem.template_id);

        // Получаем disassemble_data из БД через API (упрощенно - ищем в рецептах)
        // В реальности нужно добавить API для получения disassemble_data
        const disData = getDisassembleData(invItem.template_id);

        if (!disData || Object.keys(disData).length === 0) {
            container.innerHTML = '<div style="color:#888">Этот предмет нельзя разобрать</div>';
            return;
        }

        container.innerHTML = Object.entries(disData).map(([tid, qty]) => {
            const t = findTemplateById(parseInt(tid));
            return `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;margin-bottom:6px;background:rgba(255,255,255,0.05);border-radius:6px">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-size:20px">${getIcon('material')}</span>
                        <span style="font-size:13px">${t?.name || 'Неизвестно'}</span>
                    </div>
                    <div style="font-size:14px;font-weight:700;color:#10b981">+${qty}</div>
                </div>
            `;
        }).join('');
    }

    function getDisassembleData(templateId) {
        // Ищем в инвентаре (там есть stats)
        const item = inventory.find(i => i.template_id === templateId);
        if (item && item.stats?.disassemble_data) {
            return item.stats.disassemble_data;
        }

        // Если не нашли - возвращаем дефолтные данные для деревянного меча
        if (templateId === 3) {
            return { '1': 2 }; // 2 дерева
        }

        return null;
    }

    function findTemplateById(templateId) {
        const item = inventory.find(i => i.template_id === templateId);
        if (item) return { name: item.name, type: item.type };
        for (const r of recipes) {
            if (r.result.template_id === templateId) return { name: r.result.name, type: 'equipment' };
            const comp = r.components.find(c => c.template_id === templateId);
            if (comp) return { name: comp.name, type: 'material' };
        }
        return null;
    }

    function canCraftNow() {
        if (!workbenchState.recipe) return false;
        const qty = workbenchState.quantity;
        return workbenchState.recipe.components.every(comp => {
            const needed = comp.quantity * qty;
            const invItem = inventory.find(i => i.template_id === comp.template_id);
            return invItem && invItem.quantity >= needed;
        });
    }

    function handleCenterDrop(item) {
        workbenchState.quantity = 1;
        document.getElementById('craftQuantity').value = 1;

        if (item.type === 'recipe') {
            const invItem = inventory.find(i => i.instance_id === item.instance_id);
            const recipeId = invItem?.stats?.recipe_id;
            const recipe = recipes.find(r => r.recipe_id === recipeId);

            if (!recipe) {
                showMsg('Не удалось найти рецепт для этого чертежа', 'error');
                return;
            }

            workbenchState.center = item;
            workbenchState.mode = 'craft';
            workbenchState.recipe = recipe;
        } else if (item.type === 'equipment' || item.type === 'consumable') {
            workbenchState.center = item;
            workbenchState.mode = 'disassemble';
            workbenchState.recipe = null;
        } else {
            showMsg('В центр можно положить только чертёж или предмет для разборки', 'error');
            return;
        }

        renderWorkbench();
    }

    function clearWorkbench() {
        workbenchState = {
            center: null,
            mode: null,
            recipe: null,
            quantity: 1,
        };
        renderWorkbench();
    }

    setupDropZone(document.getElementById('centerSlot'), handleCenterDrop);

    document.getElementById('craftQuantity').addEventListener('input', (e) => {
        workbenchState.quantity = parseInt(e.target.value) || 1;
        renderIngredientsList();
        renderResultSlot();
    });

    document.getElementById('btnCraft').addEventListener('click', async () => {
        if (!workbenchState.recipe) return;

        if (!canCraftNow()) {
            showMsg('Недостаточно материалов!', 'error');
            return;
        }

        try {
            const qty = workbenchState.quantity;
            const promises = [];

            for (let i = 0; i < qty; i++) {
                promises.push(fetch('/api/craft', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        recipe_id: workbenchState.recipe.recipe_id,
                        user_id: userId
                    })
                }));
            }

            const results = await Promise.all(promises);
            const lastResult = await results[results.length - 1].json();

            if (lastResult.error) {
                showMsg(lastResult.error, 'error');
            } else {
                const totalQty = lastResult.item.quantity * qty;
                showMsg(`✅ Создано: ${lastResult.item.name} x${totalQty}`, 'success');
                clearWorkbench();
                await loadAll();
            }
        } catch (e) {
            showMsg('Ошибка: ' + e.message, 'error');
        }
    });

    document.getElementById('btnDisassemble').addEventListener('click', async () => {
        if (!workbenchState.center) return;
        try {
            const res = await fetch('/api/disassemble', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    instance_id: workbenchState.center.instance_id,
                    user_id: userId
                })
            });
            const data = await res.json();
            if (data.error) {
                showMsg(data.error, 'error');
            } else {
                showMsg(`✅ ${data.message}`, 'success');
                clearWorkbench();
                await loadAll();
            }
        } catch (e) {
            showMsg('Ошибка: ' + e.message, 'error');
        }
    });

    loadAll();
</script>
</body>
</html>
