<div style="max-width:600px;margin:0 auto">
    <h2 style="font-size:20px;font-weight:700;margin-bottom:20px;color:#d4a574">🔨 Верстак</h2>

    <div style="background:linear-gradient(135deg,rgba(139,69,19,0.2),rgba(101,67,33,0.2));border:2px solid rgba(139,69,19,0.5);border-radius:12px;padding:20px">
        <div id="centerSlot" style="background:rgba(168,85,247,0.1);border:2px dashed rgba(168,85,247,0.5);border-radius:10px;padding:15px;text-align:center;margin-bottom:15px">
            <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">Чертёж / Предмет</div>
            <div style="font-size:36px">?</div>
            <div style="font-size:11px;color:#888">Двойной клик на предмете из инвентаря</div>
        </div>

        <div id="craftMode" style="display:none">
            <div style="margin:15px 0">
                <div style="font-size:14px;font-weight:600;margin-bottom:10px;color:#d4a574">📋 Необходимо:</div>
                <div id="ingredientsList" style="background:rgba(0,0,0,0.2);border-radius:8px;padding:12px"></div>
            </div>

            <div style="margin:15px 0">
                <label style="display:block;font-size:13px;margin-bottom:6px;color:#aaa">Название (опционально):</label>
                <input type="text" id="customName" placeholder="Оставь пустым для автоназвания"
                       style="width:100%;padding:10px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:14px">
            </div>

            <div id="resultSlot" style="background:rgba(16,185,129,0.1);border:2px dashed rgba(16,185,129,0.5);border-radius:10px;padding:15px;text-align:center;margin-bottom:15px">
                <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">Будет создано</div>
                <div style="font-size:36px">—</div>
            </div>

            <button id="btnCraft" style="width:100%;padding:12px;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-transform:uppercase">
                ⚒️ Создать
            </button>
        </div>

        <div id="disassembleMode" style="display:none">
            <div style="margin:15px 0">
                <div style="font-size:14px;font-weight:600;margin-bottom:10px;color:#d4a574">🔧 Будет возвращено:</div>
                <div id="disassembleResult" style="background:rgba(0,0,0,0.2);border-radius:8px;padding:12px"></div>
            </div>

            <button id="btnDisassemble" style="width:100%;padding:12px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-transform:uppercase">
                🔧 Разобрать
            </button>
        </div>

        <div id="emptyMode">
            <div style="text-align:center;font-size:12px;color:#888;font-style:italic;padding:20px">
                Двойной клик на чертеже для крафта<br>или на предмете для разборки
            </div>
        </div>

        <button id="btnClearWorkbench" style="width:100%;padding:10px;margin-top:10px;background:rgba(255,255,255,0.1);color:#aaa;border:none;border-radius:8px;font-size:13px;cursor:pointer">
            🗑️ Очистить
        </button>
    </div>
</div>

<script>
    let workbenchState = {
        center: null,
        mode: null,
        recipe: null,
    };

    window.handleWorkbenchDrop = function(item) {
        const customNameInput = document.getElementById('customName');
        if (customNameInput) customNameInput.value = '';

        if (item.stage === 'blueprint') {
            const recipe = GameState.recipes.find(r => r.slug === item.recipe_slug);
            if (!recipe) {
                showMsg('Рецепт не найден для этого чертежа', 'error');
                return;
            }
            workbenchState.center = item;
            workbenchState.mode = 'craft';
            workbenchState.recipe = recipe;
        } else if (item.stage === 'item') {
            workbenchState.center = item;
            workbenchState.mode = 'disassemble';
            workbenchState.recipe = null;
        } else {
            showMsg('Можно положить только чертёж или предмет', 'error');
            return;
        }
        renderWorkbench();
    };

    function initWorkbench() {
        document.getElementById('btnCraft').addEventListener('click', craftItem);
        document.getElementById('btnDisassemble').addEventListener('click', disassembleItem);
        document.getElementById('btnClearWorkbench').addEventListener('click', clearWorkbench);
    }

    function renderWorkbench() {
        const craftMode = document.getElementById('craftMode');
        const disassembleMode = document.getElementById('disassembleMode');
        const emptyMode = document.getElementById('emptyMode');

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
        }
    }

    function renderCenterSlot() {
        const slot = document.getElementById('centerSlot');
        if (workbenchState.center) {
            const item = workbenchState.center;
            const icon = item.icon || (item.stage === 'blueprint' ? '📜' : '⚔️');
            slot.innerHTML = `
                <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">${workbenchState.mode === 'craft' ? 'Чертёж' : 'Предмет'}</div>
                <div style="font-size:36px">${icon}</div>
                <div style="font-size:13px;font-weight:600;margin-top:5px">${item.name}</div>
            `;
        }
    }

    function renderIngredientsList() {
        if (!workbenchState.recipe || !workbenchState.recipe.craft_formula) return;
        const container = document.getElementById('ingredientsList');
        const formula = workbenchState.recipe.craft_formula;

        container.innerHTML = Object.entries(formula).map(([slug, needed]) => {
            const invItem = GameState.inventory.find(i => i.template_slug === slug);
            const available = invItem ? invItem.quantity : 0;
            const isEnough = available >= needed;

            return `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;margin-bottom:6px;background:rgba(255,255,255,0.05);border-radius:6px">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-size:20px">${invItem?.icon || '📦'}</span>
                        <span style="font-size:13px">${invItem?.name || slug}</span>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:14px;font-weight:700;color:${isEnough ? '#10b981' : '#ef4444'}">${available} / ${needed}</div>
                        <div style="font-size:10px;color:#888">${isEnough ? '✅ Достаточно' : '❌ Не хватает'}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderResultSlot() {
        const slot = document.getElementById('resultSlot');
        if (!workbenchState.recipe) return;
        slot.innerHTML = `
            <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">Будет создано</div>
            <div style="font-size:36px">⚔️</div>
            <div style="font-size:13px;font-weight:600;margin-top:5px">${workbenchState.recipe.name}</div>
        `;
    }

    function renderDisassembleResult() {
        const container = document.getElementById('disassembleResult');
        if (!workbenchState.center) return;

        const recipe = GameState.recipes.find(r => r.slug === workbenchState.center.recipe_slug);
        if (!recipe) {
            container.innerHTML = '<div style="color:#888">Рецепт не найден</div>';
            return;
        }

        container.innerHTML = `
            <div style="padding:8px;background:rgba(255,255,255,0.05);border-radius:6px">
                <div style="font-size:13px;color:#10b981">✅ Чертёж будет возвращён</div>
                <div style="font-size:11px;color:#888;margin-top:4px">+ материалы по формуле разбора</div>
            </div>
        `;
    }

    function clearWorkbench() {
        workbenchState = { center: null, mode: null, recipe: null };
        renderWorkbench();
    }

    async function craftItem() {
        if (!workbenchState.recipe || !workbenchState.center) return;

        const customName = document.getElementById('customName').value.trim();

        try {
            const res = await fetch(`/api/crafting/${GameState.characterUuid}/craft-item`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    recipe_slug: workbenchState.recipe.slug,
                    blueprint_uuid: workbenchState.center.uuid,
                    custom_name: customName || null,
                })
            });
            const data = await res.json();
            if (data.error) {
                showMsg(data.error, 'error');
            } else {
                showMsg(`✅ Создано: ${data.item.custom_name || data.item.template_slug}`, 'success');
                clearWorkbench();
                loadPlayerData();
            }
        } catch (e) {
            showMsg('Ошибка: ' + e.message, 'error');
        }
    }

    async function disassembleItem() {
        if (!workbenchState.center) return;

        try {
            const res = await fetch(`/api/crafting/${GameState.characterUuid}/disassemble`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    item_uuid: workbenchState.center.uuid,
                })
            });
            const data = await res.json();
            if (data.error) {
                showMsg(data.error, 'error');
            } else {
                showMsg(`✅ Разобрано. Получено: ${Object.entries(data.returned_resources).map(([k,v]) => `${k} x${v}`).join(', ')}`, 'success');
                clearWorkbench();
                loadPlayerData();
            }
        } catch (e) {
            showMsg('Ошибка: ' + e.message, 'error');
        }
    }
</script>
