<div style="display:grid;grid-template-columns:1fr 350px;gap:20px;height:100%">
    <div>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:20px;color:#d4a574">🔨 Верстак</h2>

        <div style="background:linear-gradient(135deg,rgba(139,69,19,0.2),rgba(101,67,33,0.2));border:2px solid rgba(139,69,19,0.5);border-radius:12px;padding:20px">
            <div id="centerSlot" style="background:rgba(168,85,247,0.1);border:2px dashed rgba(168,85,247,0.5);border-radius:10px;padding:15px;text-align:center;margin-bottom:15px;cursor:pointer">
                <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">Рецепт / Предмет</div>
                <div style="font-size:36px">?</div>
                <div style="font-size:11px;color:#888">Перетащи или кликни дважды</div>
            </div>

            <div id="craftMode" style="display:none">
                <div style="margin:15px 0">
                    <div style="font-size:14px;font-weight:600;margin-bottom:10px;color:#d4a574">📋 Необходимые ингредиенты:</div>
                    <div id="ingredientsList" style="background:rgba(0,0,0,0.2);border-radius:8px;padding:12px"></div>
                </div>

                <div style="margin:15px 0">
                    <label style="display:block;font-size:13px;margin-bottom:6px;color:#aaa">Количество:</label>
                    <input type="number" id="craftQuantity" value="1" min="1" max="99"
                           style="width:100%;padding:10px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:16px">
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
                    <div style="font-size:14px;font-weight:600;margin-bottom:10px;color:#d4a574">🔧 Будет получено:</div>
                    <div id="disassembleResult" style="background:rgba(0,0,0,0.2);border-radius:8px;padding:12px"></div>
                </div>

                <button id="btnDisassemble" style="width:100%;padding:12px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-transform:uppercase">
                    🔧 Разобрать
                </button>
            </div>

            <div id="emptyMode">
                <div style="text-align:center;font-size:12px;color:#888;font-style:italic;padding:20px">
                    Положите чертёж в центр для крафта или предмет для разборки
                </div>
            </div>

            <button id="btnClearWorkbench" style="width:100%;padding:10px;margin-top:10px;background:rgba(255,255,255,0.1);color:#aaa;border:none;border-radius:8px;font-size:13px;cursor:pointer">
                🗑️ Очистить
            </button>
        </div>
    </div>

    <div>
        <h3 style="font-size:16px;font-weight:600;margin-bottom:15px">📜 Доступные рецепты</h3>
        <div id="recipesList" style="display:flex;flex-direction:column;gap:10px;max-height:600px;overflow-y:auto"></div>
    </div>
</div>

<script>
    let workbenchState = { center: null, mode: null, recipe: null, quantity: 1 };

    // Глобальная функция для обработки drop/double-click из inventory
    window.handleWorkbenchDrop = function(item) {
        workbenchState.quantity = 1;
        const qtyInput = document.getElementById('craftQuantity');
        if (qtyInput) qtyInput.value = 1;

        if (item.type === 'recipe') {
            const invItem = GameState.inventory.find(i => i.instance_id === item.instance_id);
            const recipeId = invItem?.stats?.recipe_id;
            const recipe = GameState.recipes.find(r => r.recipe_id === recipeId);
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
    };

    function initWorkbench() {
        loadRecipes();
        setupDropZone(document.getElementById('centerSlot'), handleWorkbenchDrop);

        document.getElementById('btnCraft').addEventListener('click', craftItem);
        document.getElementById('btnDisassemble').addEventListener('click', disassembleItem);
        document.getElementById('btnClearWorkbench').addEventListener('click', clearWorkbench);

        document.getElementById('craftQuantity').addEventListener('input', (e) => {
            workbenchState.quantity = parseInt(e.target.value) || 1;
            renderIngredientsList();
            renderResultSlot();
        });
    }

    async function loadRecipes() {
        try {
            const res = await fetch('/api/recipes', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            GameState.recipes = data.recipes || [];
            renderRecipes();
        } catch (e) {
            console.error(e);
        }
    }

    function renderRecipes() {
        const el = document.getElementById('recipesList');
        if (!GameState.recipes.length) {
            el.innerHTML = '<div style="text-align:center;padding:20px;color:#666">Нет рецептов</div>';
            return;
        }
        el.innerHTML = GameState.recipes.map(r => `
            <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:12px">
                <div style="font-weight:600;font-size:13px;margin-bottom:6px">${r.name}</div>
                <div style="font-size:11px;color:#888">
                    ${r.components.map(c => `${c.name} x${c.quantity}`).join(' + ')}
                    → ${r.result.name} x${r.result.quantity}
                </div>
            </div>
        `).join('');
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
            const centerSlot = document.getElementById('centerSlot');
            centerSlot.innerHTML = `
                <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">Рецепт / Предмет</div>
                <div style="font-size:36px">?</div>
                <div style="font-size:11px;color:#888">Перетащи или кликни дважды</div>
            `;
        }
    }

    function renderCenterSlot() {
        const slot = document.getElementById('centerSlot');
        if (workbenchState.center) {
            const item = workbenchState.center;
            slot.innerHTML = `
                <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">${workbenchState.mode === 'craft' ? 'Чертёж' : 'Предмет'}</div>
                <div style="font-size:36px">${getIcon(item.type)}</div>
                <div style="font-size:13px;font-weight:600;margin-top:5px">${item.name}</div>
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
            const invItem = GameState.inventory.find(i => i.template_id === comp.template_id);
            const available = invItem ? invItem.quantity : 0;
            const isEnough = available >= needed;

            return `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;margin-bottom:6px;background:rgba(255,255,255,0.05);border-radius:6px">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-size:20px">${getIcon('material')}</span>
                        <span style="font-size:13px">${comp.name}</span>
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
        const r = workbenchState.recipe.result;
        const qty = workbenchState.quantity;
        slot.innerHTML = `
            <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">Будет создано</div>
            <div style="font-size:36px">${getIcon('equipment')}</div>
            <div style="font-size:13px;font-weight:600;margin-top:5px">${r.name}</div>
            <div style="font-size:14px;color:#fbbf24;font-weight:700">x${r.quantity * qty}</div>
        `;
    }

    function renderDisassembleResult() {
        if (!workbenchState.center) return;
        const container = document.getElementById('disassembleResult');
        const invItem = GameState.inventory.find(i => i.instance_id === workbenchState.center.instance_id);
        if (!invItem) {
            container.innerHTML = '<div style="color:#888">Предмет не найден</div>';
            return;
        }
        const disData = invItem.template_id === 3 ? { '1': 2 } : null;
        if (!disData) {
            container.innerHTML = '<div style="color:#888">Этот предмет нельзя разобрать</div>';
            return;
        }
        container.innerHTML = Object.entries(disData).map(([tid, qty]) => {
            const t = GameState.inventory.find(i => i.template_id === parseInt(tid));
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

    function clearWorkbench() {
        workbenchState = { center: null, mode: null, recipe: null, quantity: 1 };
        renderWorkbench();
    }

    async function craftItem() {
        if (!workbenchState.recipe) return;
        const qty = workbenchState.quantity;
        const promises = [];
        for (let i = 0; i < qty; i++) {
            promises.push(fetch('/api/craft', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ recipe_id: workbenchState.recipe.recipe_id, user_id: GameState.userId })
            }));
        }
        const results = await Promise.all(promises);
        const lastResult = await results[results.length - 1].json();
        if (lastResult.error) {
            showMsg(lastResult.error, 'error');
        } else {
            showMsg(`✅ Создано: ${lastResult.item.name} x${lastResult.item.quantity * qty}`, 'success');
            clearWorkbench();
        }
    }

    async function disassembleItem() {
        if (!workbenchState.center) return;
        const res = await fetch('/api/disassemble', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ instance_id: workbenchState.center.instance_id, user_id: GameState.userId })
        });
        const data = await res.json();
        if (data.error) {
            showMsg(data.error, 'error');
        } else {
            showMsg(`✅ ${data.message}`, 'success');
            clearWorkbench();
        }
    }
</script>
