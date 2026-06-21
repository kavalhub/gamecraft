@extends('layouts.game')

@section('title', 'Игра')

@section('center')
    <div id="toolContent">

        <!-- ==================== ВЕРСТАК ==================== -->
        <div id="tool-workbench" class="tool-panel">
            <h2 style="font-size:20px;font-weight:700;margin-bottom:20px;color:#d4a574">🔨 Верстак</h2>

            <div style="background:linear-gradient(135deg,rgba(139,69,19,0.2),rgba(101,67,33,0.2));border:2px solid rgba(139,69,19,0.5);border-radius:12px;padding:20px">
                <div id="centerSlot" style="background:rgba(168,85,247,0.1);border:2px dashed rgba(168,85,247,0.5);border-radius:10px;padding:15px;text-align:center;margin-bottom:15px;transition:all 0.2s">
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
                        <label style="display:block;font-size:13px;margin-bottom:6px;color:#aaa">
                            Количество: <span id="craftQtyDisplay" style="color:#fbbf24;font-weight:700">1</span> / <span id="craftQtyMax" style="color:#888">1</span>
                        </label>
                        <div style="display:flex;gap:10px;align-items:center">
                            <input type="range" id="craftQuantityRange" min="1" max="1" value="1" style="flex:1;accent-color:#667eea">
                            <input type="number" id="craftQuantity" value="1" min="1" max="1" style="width:80px;padding:8px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:14px;text-align:center">
                        </div>
                    </div>

                    <div id="resultSlot" style="background:rgba(16,185,129,0.1);border:2px dashed rgba(16,185,129,0.5);border-radius:10px;padding:15px;text-align:center;margin-bottom:15px">
                        <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">Будет создано</div>
                        <div style="font-size:36px">—</div>
                    </div>

                    <button id="btnCraft" style="width:100%;padding:12px;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-transform:uppercase">⚒️ Создать</button>
                </div>

                <div id="disassembleMode" style="display:none">
                    <div style="margin:15px 0">
                        <div style="font-size:14px;font-weight:600;margin-bottom:10px;color:#d4a574">🔧 Будет получено:</div>
                        <div id="disassembleResult" style="background:rgba(0,0,0,0.2);border-radius:8px;padding:12px"></div>
                    </div>
                    <button id="btnDisassemble" style="width:100%;padding:12px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-transform:uppercase">🔧 Разобрать</button>
                </div>

                <div id="emptyMode">
                    <div style="text-align:center;font-size:12px;color:#888;font-style:italic;padding:20px">Положите чертёж в центр для крафта или предмет для разборки</div>
                </div>

                <button id="btnClearWorkbench" style="width:100%;padding:10px;margin-top:10px;background:rgba(255,255,255,0.1);color:#aaa;border:none;border-radius:8px;font-size:13px;cursor:pointer">🗑️ Очистить</button>
            </div>
        </div>

        <!-- ==================== АУКЦИОН ==================== -->
        <div id="tool-auction" class="tool-panel" style="display:none">
            <h2 style="font-size:20px;font-weight:700;margin-bottom:20px;color:#d4a574">🏪 Аукцион</h2>

            <div style="display:flex;gap:10px;margin-bottom:20px">
                <button class="auction-tab" data-tab="market" style="padding:10px 20px;border-radius:8px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer">🏪 Рынок</button>
                <button class="auction-tab" data-tab="my" style="padding:10px 20px;border-radius:8px;background:rgba(255,255,255,0.05);color:#aaa;border:1px solid rgba(255,255,255,0.1);font-size:13px;font-weight:600;cursor:pointer">📦 Мои лоты</button>
                <button class="auction-tab" data-tab="list" style="padding:10px 20px;border-radius:8px;background:rgba(255,255,255,0.05);color:#aaa;border:1px solid rgba(255,255,255,0.1);font-size:13px;font-weight:600;cursor:pointer">➕ Выставить</button>
            </div>

            <div id="auction-market">
                <div style="margin-bottom:15px">
                    <select id="filterType" style="padding:8px 12px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:13px">
                        <option value="">Все типы</option>
                        <option value="material">Материалы</option>
                        <option value="equipment">Экипировка</option>
                        <option value="consumable">Расходники</option>
                    </select>
                </div>
                <div id="marketList" style="display:flex;flex-direction:column;gap:10px;max-height:500px;overflow-y:auto"></div>
            </div>

            <div id="auction-my" style="display:none">
                <div id="myLotsList" style="display:flex;flex-direction:column;gap:10px;max-height:500px;overflow-y:auto"></div>
            </div>

            <div id="auction-list" style="display:none">
                <div style="display:flex;flex-direction:column;gap:15px">
                    <div>
                        <label style="display:block;font-size:12px;color:#aaa;margin-bottom:6px">Выберите предмет:</label>
                        <div id="selectedItem" style="background:rgba(168,85,247,0.1);border:2px dashed rgba(168,85,247,0.4);border-radius:8px;padding:15px;text-align:center;min-height:80px;display:flex;flex-direction:column;align-items:center;justify-content:center">
                            <div style="color:#888;font-size:12px">Перетащите предмет из инвентаря или кликните дважды</div>
                        </div>
                    </div>

                    <div id="auctionQuantityBlock" style="display:none">
                        <label style="display:block;font-size:12px;color:#aaa;margin-bottom:6px">
                            Количество: <span id="auctionQtyDisplay" style="color:#fbbf24;font-weight:700">1</span> / <span id="auctionQtyMax" style="color:#888">1</span>
                        </label>
                        <div style="display:flex;gap:10px;align-items:center">
                            <input type="range" id="auctionQuantityRange" min="1" max="1" value="1" style="flex:1;accent-color:#667eea">
                            <input type="number" id="auctionQuantity" value="1" min="1" max="1" style="width:80px;padding:8px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:14px;text-align:center">
                        </div>
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;color:#aaa;margin-bottom:6px">Цена за единицу (золото):</label>
                        <input type="number" id="listPrice" min="1" value="100" style="width:100%;padding:10px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:14px">
                        <div style="font-size:11px;color:#888;margin-top:4px">Итого: <span id="totalPrice" style="color:#fbbf24;font-weight:700">100</span> 💰</div>
                    </div>

                    <button id="btnSubmitLot" style="width:100%;padding:12px;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-transform:uppercase">📢 Выставить на аукцион</button>
                </div>
            </div>
        </div>

        <!-- ==================== ОБМЕН ==================== -->
        <div id="tool-trade" class="tool-panel" style="display:none">
            <h2 style="font-size:20px;font-weight:700;margin-bottom:20px;color:#d4a574">🤝 Обмен</h2>

            <div id="tradeListBlock">
                <div style="margin-bottom:15px;display:flex;justify-content:space-between;align-items:center">
                    <div style="font-size:13px;color:#aaa">🟢 Онлайн: <span id="onlineCount" style="color:#10b981;font-weight:700">0</span> игроков</div>
                    <button id="btnRefreshPlayers" style="padding:8px 14px;background:rgba(255,255,255,0.1);color:#aaa;border:1px solid rgba(255,255,255,0.1);border-radius:6px;font-size:12px;cursor:pointer">🔄 Обновить</button>
                </div>
                <div id="onlinePlayersList" style="display:flex;flex-direction:column;gap:8px"></div>

                <div style="margin-top:20px;padding-top:15px;border-top:1px solid rgba(255,255,255,0.1)">
                    <div style="font-size:12px;color:#888;margin-bottom:10px">📋 Активные обмены:</div>
                    <div id="activeTradesList" style="display:flex;flex-direction:column;gap:8px"></div>
                </div>
            </div>

            <div id="tradeWindow" style="display:none">
                <div style="background:rgba(0,0,0,0.3);border-radius:12px;padding:20px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                        <h3 style="font-size:16px;font-weight:600">Обмен с <span id="tradeOpponent" style="color:#fbbf24"></span></h3>
                        <button id="btnCloseTrade" style="padding:6px 12px;background:rgba(255,255,255,0.1);color:#aaa;border:none;border-radius:6px;cursor:pointer">✕ Закрыть</button>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                        <div>
                            <div style="font-size:13px;font-weight:600;margin-bottom:10px;color:#667eea">🎒 Моё предложение <span id="myAcceptStatus" style="font-size:11px;color:#888"></span></div>
                            <div id="myOfferItems" style="min-height:100px;background:rgba(255,255,255,0.03);border-radius:8px;padding:10px;margin-bottom:10px"></div>
                            <div style="display:flex;gap:8px;align-items:center">
                                <label style="font-size:12px;color:#aaa">💰 Золото:</label>
                                <input type="number" id="myGoldInput" min="0" value="0" style="flex:1;padding:6px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:13px">
                                <button id="btnSetGold" style="padding:6px 12px;background:rgba(251,191,36,0.2);color:#fbbf24;border:1px solid rgba(251,191,36,0.3);border-radius:6px;font-size:12px;cursor:pointer">Ок</button>
                            </div>
                        </div>

                        <div>
                            <div style="font-size:13px;font-weight:600;margin-bottom:10px;color:#f97316">📦 Предложение партнёра <span id="partnerAcceptStatus" style="font-size:11px;color:#888"></span></div>
                            <div id="partnerOfferItems" style="min-height:100px;background:rgba(255,255,255,0.03);border-radius:8px;padding:10px;margin-bottom:10px"></div>
                            <div style="display:flex;gap:8px;align-items:center">
                                <label style="font-size:12px;color:#aaa">💰 Золото:</label>
                                <div id="partnerGold" style="flex:1;padding:6px;font-size:13px;color:#fbbf24;font-weight:700">0</div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:20px">
                        <button id="btnAcceptTrade" style="flex:1;padding:12px;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-transform:uppercase">✅ Подтвердить</button>
                        <button id="btnCancelTrade" style="flex:1;padding:12px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-transform:uppercase">❌ Отменить</button>
                    </div>
                </div>

                <div style="margin-top:15px;background:rgba(0,0,0,0.2);border-radius:8px;padding:12px">
                    <div style="font-size:11px;color:#666">💡 Двойной клик по предмету в инвентаре справа добавит его в ваше предложение</div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        // ================================================================
        //                        ВЕРСТАК
        // ================================================================
        let workbenchState = { center: null, mode: null, recipe: null, quantity: 1 };

        function initWorkbench() {
            loadRecipes();
            setupDropZone(document.getElementById('centerSlot'), handleWorkbenchDrop);

            document.getElementById('btnCraft').addEventListener('click', craftItem);
            document.getElementById('btnDisassemble').addEventListener('click', disassembleItem);
            document.getElementById('btnClearWorkbench').addEventListener('click', clearWorkbench);

            const craftRange = document.getElementById('craftQuantityRange');
            const craftInput = document.getElementById('craftQuantity');

            craftRange.addEventListener('input', (e) => {
                const val = parseInt(e.target.value);
                craftInput.value = val;
                document.getElementById('craftQtyDisplay').textContent = val;
                workbenchState.quantity = val;
                renderIngredientsList();
                renderResultSlot();
            });

            craftInput.addEventListener('input', (e) => {
                let val = parseInt(e.target.value) || 1;
                const max = parseInt(craftRange.max) || 1;
                val = Math.max(1, Math.min(val, max));
                craftRange.value = val;
                document.getElementById('craftQtyDisplay').textContent = val;
                workbenchState.quantity = val;
                renderIngredientsList();
                renderResultSlot();
            });
        }

        window.handleWorkbenchDrop = function(item) {
            workbenchState.quantity = 1;

            if (item.type === 'recipe') {
                const invItem = GameState.inventory.find(i => i.instance_id === item.instance_id);
                const recipeId = invItem?.stats?.recipe_id;
                const recipe = GameState.recipes.find(r => r.recipe_id === recipeId);
                if (!recipe) { showMsg('Не удалось найти рецепт для этого чертежа', 'error'); return; }
                workbenchState.center = item;
                workbenchState.mode = 'craft';
                workbenchState.recipe = recipe;
                updateCraftMaxQuantity();
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

        function updateCraftMaxQuantity() {
            if (!workbenchState.recipe) return;
            let maxQty = Infinity;
            for (const comp of workbenchState.recipe.components) {
                const invItem = GameState.inventory.find(i => i.template_id === comp.template_id);
                const available = invItem ? invItem.quantity : 0;
                const canMake = Math.floor(available / comp.quantity);
                if (canMake < maxQty) maxQty = canMake;
            }
            if (maxQty === Infinity || maxQty < 1) maxQty = 1;

            document.getElementById('craftQuantityRange').max = maxQty;
            document.getElementById('craftQuantity').max = maxQty;
            document.getElementById('craftQtyMax').textContent = maxQty;
            document.getElementById('craftQuantityRange').value = 1;
            document.getElementById('craftQuantity').value = 1;
            document.getElementById('craftQtyDisplay').textContent = 1;
            workbenchState.quantity = 1;
        }

        async function loadRecipes() {
            try {
                const res = await fetch('/api/recipes', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                GameState.recipes = data.recipes || [];
            } catch (e) { console.error(e); }
        }

        function renderWorkbench() {
            document.getElementById('craftMode').style.display = 'none';
            document.getElementById('disassembleMode').style.display = 'none';
            document.getElementById('emptyMode').style.display = 'none';

            if (workbenchState.mode === 'craft') {
                document.getElementById('craftMode').style.display = 'block';
                renderCenterSlot(); renderIngredientsList(); renderResultSlot();
            } else if (workbenchState.mode === 'disassemble') {
                document.getElementById('disassembleMode').style.display = 'block';
                renderCenterSlot(); renderDisassembleResult();
            } else {
                document.getElementById('emptyMode').style.display = 'block';
                document.getElementById('centerSlot').innerHTML = `
            <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">Рецепт / Предмет</div>
            <div style="font-size:36px">?</div>
            <div style="font-size:11px;color:#888">Перетащи или кликни дважды</div>`;
            }
        }

        function renderCenterSlot() {
            const slot = document.getElementById('centerSlot');
            if (workbenchState.center) {
                slot.innerHTML = `
            <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">${workbenchState.mode === 'craft' ? 'Чертёж' : 'Предмет'}</div>
            <div style="font-size:36px">${getIcon(workbenchState.center.type)}</div>
            <div style="font-size:13px;font-weight:600;margin-top:5px">${workbenchState.center.name}</div>`;
            }
        }

        function renderIngredientsList() {
            if (!workbenchState.recipe) return;
            const qty = workbenchState.quantity;
            document.getElementById('ingredientsList').innerHTML = workbenchState.recipe.components.map(comp => {
                const needed = comp.quantity * qty;
                const invItem = GameState.inventory.find(i => i.template_id === comp.template_id);
                const available = invItem ? invItem.quantity : 0;
                const ok = available >= needed;
                return `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px;margin-bottom:6px;background:rgba(255,255,255,0.05);border-radius:6px">
            <div style="display:flex;align-items:center;gap:8px"><span style="font-size:20px">${getIcon('material')}</span><span style="font-size:13px">${comp.name}</span></div>
            <div style="text-align:right"><div style="font-size:14px;font-weight:700;color:${ok ? '#10b981' : '#ef4444'}">${available} / ${needed}</div>
            <div style="font-size:10px;color:#888">${ok ? '✅ Достаточно' : '❌ Не хватает'}</div></div></div>`;
            }).join('');
        }

        function renderResultSlot() {
            if (!workbenchState.recipe) return;
            const r = workbenchState.recipe.result;
            document.getElementById('resultSlot').innerHTML = `
        <div style="font-size:11px;color:#aaa;text-transform:uppercase;margin-bottom:5px">Будет создано</div>
        <div style="font-size:36px">${getIcon('equipment')}</div>
        <div style="font-size:13px;font-weight:600;margin-top:5px">${r.name}</div>
        <div style="font-size:14px;color:#fbbf24;font-weight:700">x${r.quantity * workbenchState.quantity}</div>`;
        }

        function renderDisassembleResult() {
            if (!workbenchState.center) return;
            const invItem = GameState.inventory.find(i => i.instance_id === workbenchState.center.instance_id);
            const container = document.getElementById('disassembleResult');
            if (!invItem) { container.innerHTML = '<div style="color:#888">Предмет не найден</div>'; return; }
            const disData = invItem.template_id === 3 ? { '1': 2 } : null;
            if (!disData) { container.innerHTML = '<div style="color:#888">Этот предмет нельзя разобрать</div>'; return; }
            container.innerHTML = Object.entries(disData).map(([tid, qty]) => {
                const t = GameState.inventory.find(i => i.template_id === parseInt(tid));
                return `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px;margin-bottom:6px;background:rgba(255,255,255,0.05);border-radius:6px">
            <div style="display:flex;align-items:center;gap:8px"><span style="font-size:20px">${getIcon('material')}</span><span style="font-size:13px">${t?.name || 'Неизвестно'}</span></div>
            <div style="font-size:14px;font-weight:700;color:#10b981">+${qty}</div></div>`;
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
            const last = await results[results.length - 1].json();
            if (last.error) { showMsg(last.error, 'error'); }
            else { showMsg(`✅ Создано: ${last.item.name} x${last.item.quantity * qty}`, 'success'); clearWorkbench(); }
        }

        async function disassembleItem() {
            if (!workbenchState.center) return;
            const res = await fetch('/api/disassemble', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ instance_id: workbenchState.center.instance_id, user_id: GameState.userId })
            });
            const data = await res.json();
            if (data.error) { showMsg(data.error, 'error'); }
            else { showMsg(`✅ ${data.message}`, 'success'); clearWorkbench(); }
        }

        // ================================================================
        //                         АУКЦИОН
        // ================================================================
        let auctionState = { currentTab: 'market', selectedItem: null, quantity: 1 };

        function initAuction() {
            document.querySelectorAll('.auction-tab').forEach(btn => {
                btn.addEventListener('click', (e) => switchAuctionTab(e.currentTarget.dataset.tab));
            });
            document.getElementById('filterType').addEventListener('change', loadMarket);
            document.getElementById('btnSubmitLot').addEventListener('click', submitLot);

            const aRange = document.getElementById('auctionQuantityRange');
            const aInput = document.getElementById('auctionQuantity');
            aRange.addEventListener('input', (e) => {
                const v = parseInt(e.target.value);
                aInput.value = v;
                document.getElementById('auctionQtyDisplay').textContent = v;
                auctionState.quantity = v;
                updateTotalPrice();
            });
            aInput.addEventListener('input', (e) => {
                let v = parseInt(e.target.value) || 1;
                const max = parseInt(aRange.max) || 1;
                v = Math.max(1, Math.min(v, max));
                aRange.value = v;
                document.getElementById('auctionQtyDisplay').textContent = v;
                auctionState.quantity = v;
                updateTotalPrice();
            });
            document.getElementById('listPrice').addEventListener('input', updateTotalPrice);

            switchAuctionTab('market');
        }

        function updateTotalPrice() {
            const price = parseInt(document.getElementById('listPrice').value) || 0;
            document.getElementById('totalPrice').textContent = (price * auctionState.quantity).toLocaleString();
        }

        function switchAuctionTab(tab) {
            auctionState.currentTab = tab;
            document.querySelectorAll('.auction-tab').forEach(t => {
                if (t.dataset.tab === tab) { t.style.background = 'linear-gradient(135deg,#667eea,#764ba2)'; t.style.color = 'white'; t.style.border = 'none'; }
                else { t.style.background = 'rgba(255,255,255,0.05)'; t.style.color = '#aaa'; t.style.border = '1px solid rgba(255,255,255,0.1)'; }
            });
            ['market', 'my', 'list'].forEach(t => {
                document.getElementById('auction-' + t).style.display = (t === tab) ? 'block' : 'none';
            });
            if (tab === 'market') loadMarket();
            if (tab === 'my') loadMyLots();
        }

        window.loadMarket = async function() {
            try {
                const type = document.getElementById('filterType').value;
                const res = await fetch(`/api/auction${type ? '?type=' + type : ''}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                const lots = data.lots || [];
                const el = document.getElementById('marketList');
                if (!lots.length) { el.innerHTML = '<div style="text-align:center;padding:40px;color:#666">🏪 Аукцион пуст</div>'; return; }
                el.innerHTML = lots.map(lot => `
            <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:15px;display:grid;grid-template-columns:50px 1fr auto auto;gap:15px;align-items:center">
                <div style="font-size:36px;text-align:center">${getIcon(lot.item_type)}</div>
                <div><div style="font-weight:600;font-size:14px">${lot.item_name} <span style="color:#fbbf24;font-weight:700">x${lot.quantity}</span></div>
                    <div style="font-size:11px;color:#888">Продавец: ${lot.seller_name}</div></div>
                <div style="text-align:right"><div style="font-size:18px;font-weight:700;color:#fbbf24">${lot.price} 💰</div>
                    <div style="font-size:10px;color:#888">продавец получит ${lot.seller_received}</div></div>
                <button onclick="buyLot(${lot.id})" ${lot.seller_id == GameState.userId ? 'disabled style="opacity:0.4"' : ''} style="padding:10px 16px;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">${lot.seller_id == GameState.userId ? 'Ваш лот' : 'Купить'}</button>
            </div>`).join('');
            } catch (e) { showMsg('Ошибка: ' + e.message, 'error'); }
        };

        window.loadMyLots = async function() {
            try {
                const res = await fetch(`/api/auction/my?user_id=${GameState.userId}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                const lots = data.lots || [];
                const el = document.getElementById('myLotsList');
                if (!lots.length) { el.innerHTML = '<div style="text-align:center;padding:40px;color:#666">У вас пока нет лотов</div>'; return; }
                el.innerHTML = lots.map(lot => {
                    const sc = { active: 'background:rgba(16,185,129,0.2);color:#10b981', sold: 'background:rgba(59,130,246,0.2);color:#3b82f6', cancelled: 'background:rgba(107,114,128,0.2);color:#9ca3af' }[lot.status];
                    const st = { active: 'Активен', sold: 'Продан', cancelled: 'Отменён' }[lot.status];
                    return `<div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:15px;display:grid;grid-template-columns:50px 1fr auto auto;gap:15px;align-items:center">
                <div style="font-size:36px;text-align:center">${getIcon(lot.item_type)}</div>
                <div><div style="font-weight:600;font-size:14px">${lot.item_name} <span style="color:#fbbf24;font-weight:700">x${lot.quantity}</span></div>
                    <div style="font-size:11px;color:#888"><span style="display:inline-block;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:700;${sc}">${st}</span>${lot.buyer_name ? ' • Покупатель: ' + lot.buyer_name : ''}</div></div>
                <div style="font-size:18px;font-weight:700;color:#fbbf24;text-align:right">${lot.price} 💰</div>
                ${lot.status === 'active' ? `<button onclick="cancelLot(${lot.id})" style="padding:10px 16px;background:rgba(239,68,68,0.2);color:#ef4444;border:1px solid rgba(239,68,68,0.3);border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Отменить</button>` : '<div></div>'}
            </div>`;
                }).join('');
            } catch (e) { showMsg('Ошибка: ' + e.message, 'error'); }
        };

        window.handleAuctionDrop = function(item) {
            auctionState.selectedItem = item;
            auctionState.quantity = 1;
            const box = document.getElementById('selectedItem');
            box.style.borderStyle = 'solid';
            box.style.background = 'rgba(168,85,247,0.2)';
            box.innerHTML = `<div style="font-size:32px">${getIcon(item.type)}</div><div style="font-size:13px;font-weight:600;margin-top:5px">${item.name}</div><div style="font-size:12px;color:#fbbf24">В инвентаре: x${item.quantity}</div>`;

            const qtyBlock = document.getElementById('auctionQuantityBlock');
            qtyBlock.style.display = 'block';
            const maxQty = item.quantity;
            document.getElementById('auctionQuantityRange').max = maxQty;
            document.getElementById('auctionQuantity').max = maxQty;
            document.getElementById('auctionQtyMax').textContent = maxQty;
            document.getElementById('auctionQuantityRange').value = 1;
            document.getElementById('auctionQuantity').value = 1;
            document.getElementById('auctionQtyDisplay').textContent = 1;
            updateTotalPrice();
            switchAuctionTab('list');
        };

        async function submitLot() {
            if (!auctionState.selectedItem) { showMsg('Выберите предмет', 'error'); return; }
            const price = parseInt(document.getElementById('listPrice').value);
            if (!price || price < 1) { showMsg('Укажите корректную цену', 'error'); return; }

            const freshItem = GameState.inventory.find(i => i.instance_id === auctionState.selectedItem.instance_id);
            if (!freshItem) {
                showMsg('⚠️ Предмет больше не в инвентаре', 'error');
                auctionState.selectedItem = null;
                resetSelectedItemBox();
                return;
            }

            try {
                const res = await fetch('/api/auction', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: GameState.userId, instance_id: auctionState.selectedItem.instance_id, price, quantity: auctionState.quantity })
                });
                const data = await res.json();
                if (!res.ok || data.error) {
                    let err = data.error || 'Ошибка';
                    if (data.errors) err = Object.values(data.errors).flat().join(', ');
                    if (data.message) err = data.message;
                    showMsg('❌ ' + err, 'error'); return;
                }
                showMsg(`✅ Лот выставлен: ${auctionState.selectedItem.name} x${auctionState.quantity} за ${price * auctionState.quantity} 💰`, 'success');
                auctionState.selectedItem = null;
                auctionState.quantity = 1;
                resetSelectedItemBox();
                document.getElementById('listPrice').value = 100;
                switchAuctionTab('my');
            } catch (e) { showMsg('Ошибка: ' + e.message, 'error'); }
        }

        function resetSelectedItemBox() {
            const box = document.getElementById('selectedItem');
            box.style.borderStyle = 'dashed';
            box.style.background = 'rgba(168,85,247,0.1)';
            box.innerHTML = '<div style="color:#888;font-size:12px">Перетащите предмет из инвентаря или кликните дважды</div>';
            document.getElementById('auctionQuantityBlock').style.display = 'none';
        }

        async function buyLot(lotId) {
            if (!confirm('Купить этот лот?')) return;
            try {
                const res = await fetch(`/api/auction/${lotId}/buy`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ user_id: GameState.userId }) });
                const data = await res.json();
                if (data.error) showMsg(data.error, 'error');
                else { showMsg(`✅ ${data.message}`, 'success'); loadMarket(); }
            } catch (e) { showMsg('Ошибка: ' + e.message, 'error'); }
        }

        async function cancelLot(lotId) {
            if (!confirm('Отменить лот?')) return;
            try {
                const res = await fetch(`/api/auction/${lotId}/cancel`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ user_id: GameState.userId }) });
                const data = await res.json();
                if (data.error) showMsg(data.error, 'error');
                else { showMsg(`✅ ${data.message}`, 'success'); loadMyLots(); }
            } catch (e) { showMsg('Ошибка: ' + e.message, 'error'); }
        }

        // ================================================================
        //                          ОБМЕН
        // ================================================================
        let tradeState = { currentTrade: null, trades: [], onlinePlayers: [] };

        function initTrade() {
            document.getElementById('btnRefreshPlayers').addEventListener('click', loadOnlinePlayers);
            document.getElementById('btnCloseTrade').addEventListener('click', closeTradeWindow);
            document.getElementById('btnSetGold').addEventListener('click', setMyGold);
            document.getElementById('btnAcceptTrade').addEventListener('click', acceptTrade);
            document.getElementById('btnCancelTrade').addEventListener('click', cancelTrade);

            loadOnlinePlayers();
            loadTrades();
            setInterval(loadOnlinePlayers, 5000);
            setInterval(loadTrades, 3000); // автообновление списка обменов
        }

        window.loadOnlinePlayers = async function() {
            try {
                const res = await fetch(`/api/players/online?user_id=${GameState.userId}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                tradeState.onlinePlayers = data.players || [];
                const el = document.getElementById('onlinePlayersList');
                document.getElementById('onlineCount').textContent = tradeState.onlinePlayers.length;

                if (!tradeState.onlinePlayers.length) {
                    el.innerHTML = '<div style="text-align:center;padding:30px;color:#666;font-size:13px">📭 Никого нет онлайн</div>';
                    return;
                }

                el.innerHTML = tradeState.onlinePlayers.map(p => `
            <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:12px 15px;display:flex;justify-content:space-between;align-items:center;cursor:pointer"
                 onclick="startTradeWith(${p.id}, '${p.name.replace(/'/g, "\\'")}')"
                 onmouseover="this.style.borderColor='#667eea'"
                 onmouseout="this.style.borderColor='rgba(255,255,255,0.1)'">
                <div style="display:flex;align-items:center;gap:12px">
                    <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px">${p.name.charAt(0).toUpperCase()}</div>
                    <div><div style="font-weight:600;font-size:14px">${p.name}</div><div style="font-size:11px;color:#888">ID: ${p.id} • 💰 ${p.gold}</div></div>
                </div>
                <button style="padding:8px 16px;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer">🤝 Обмен</button>
            </div>`).join('');
            } catch (e) { console.error(e); }
        };

        window.loadTrades = async function() {
            try {
                const res = await fetch(`/api/trade/active?user_id=${GameState.userId}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                tradeState.trades = data.trades || [];
                renderActiveTrades();
            } catch (e) { console.error(e); }
        };

        function renderActiveTrades() {
            const el = document.getElementById('activeTradesList');
            if (!tradeState.trades.length) {
                el.innerHTML = '<div style="text-align:center;padding:20px;color:#666;font-size:12px">Нет активных обменов</div>';
                return;
            }
            el.innerHTML = tradeState.trades.map(t => `
        <div style="background:rgba(168,85,247,0.1);border:1px solid rgba(168,85,247,0.3);border-radius:8px;padding:10px 12px;display:flex;justify-content:space-between;align-items:center;cursor:pointer"
             onclick="openTrade(${t.id})">
            <div style="font-size:13px">🤝 <b>${t.opponent_name}</b></div>
            <div style="font-size:11px;color:#10b981">Открыть →</div>
        </div>`).join('');
        }

        async function startTradeWith(partnerId, partnerName) {
            if (!confirm(`Начать обмен с ${partnerName}?`)) return;

            try {
                const res = await fetch('/api/trade', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ initiator_id: GameState.userId, partner_id: partnerId })
                });
                const data = await res.json();

                if (data.error) {
                    showMsg(data.error, 'error');
                    return;
                }

                showMsg(`✅ Обмен с ${partnerName} создан`, 'success');
                await loadTrades();
                await openTrade(data.trade_id);
            } catch (e) {
                showMsg('Ошибка: ' + e.message, 'error');
            }
        }

        async function openTrade(tradeId) {
            try {
                const res = await fetch(`/api/trade/${tradeId}?user_id=${GameState.userId}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();

                if (data.error) {
                    showMsg(data.error, 'error');
                    return;
                }

                tradeState.currentTrade = data.trade;
                renderTradeWindow();
            } catch (e) {
                showMsg('Ошибка: ' + e.message, 'error');
            }
        }

        function renderTradeWindow() {
            const t = tradeState.currentTrade;
            if (!t) return;

            document.getElementById('tradeListBlock').style.display = 'none';
            document.getElementById('tradeWindow').style.display = 'block';
            document.getElementById('tradeOpponent').textContent = t.opponent_name;

            const mySide = t[t.my_side];
            const partnerSide = t[t.my_side === 'initiator' ? 'partner' : 'initiator'];

            document.getElementById('myAcceptStatus').innerHTML = mySide.accepted
                                                                  ? '<span style="color:#10b981">✅ Подтверждено</span>'
                                                                  : '<span style="color:#888">⏳ Ожидает</span>';
            document.getElementById('partnerAcceptStatus').innerHTML = partnerSide.accepted
                                                                       ? '<span style="color:#10b981">✅ Подтверждено</span>'
                                                                       : '<span style="color:#888">⏳ Ожидает</span>';

            // Мои предметы
            const myItemsEl = document.getElementById('myOfferItems');
            if (!mySide.items.length) {
                myItemsEl.innerHTML = '<div style="text-align:center;padding:20px;color:#666;font-size:12px">Пусто. Двойной клик по предмету в инвентаре</div>';
            } else {
                myItemsEl.innerHTML = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(70px,1fr));gap:6px">' + mySide.items.map(i => `
            <div style="background:rgba(102,126,234,0.1);border:1px solid rgba(102,126,234,0.3);border-radius:6px;padding:6px;text-align:center;cursor:pointer" onclick="removeTradeItem(${i.id})">
                <div style="font-size:22px">${getIcon(i.type)}</div><div style="font-size:10px">${i.name}</div>${i.quantity > 1 ? `<div style="font-size:11px;color:#fbbf24;font-weight:700">x${i.quantity}</div>` : ''}
            </div>`).join('') + '</div>';
            }

            // Предметы партнёра
            const partnerItemsEl = document.getElementById('partnerOfferItems');
            if (!partnerSide.items.length) {
                partnerItemsEl.innerHTML = '<div style="text-align:center;padding:20px;color:#666;font-size:12px">Пусто</div>';
            } else {
                partnerItemsEl.innerHTML = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(70px,1fr));gap:6px">' + partnerSide.items.map(i => `
            <div style="background:rgba(249,115,22,0.1);border:1px solid rgba(249,115,22,0.3);border-radius:6px;padding:6px;text-align:center">
                <div style="font-size:22px">${getIcon(i.type)}</div><div style="font-size:10px">${i.name}</div>${i.quantity > 1 ? `<div style="font-size:11px;color:#fbbf24;font-weight:700">x${i.quantity}</div>` : ''}
            </div>`).join('') + '</div>';
            }

            document.getElementById('myGoldInput').value = mySide.gold;
            document.getElementById('partnerGold').textContent = partnerSide.gold;
        }

        function closeTradeWindow() {
            tradeState.currentTrade = null;
            document.getElementById('tradeListBlock').style.display = 'block';
            document.getElementById('tradeWindow').style.display = 'none';
        }

        async function setMyGold() {
            if (!tradeState.currentTrade) return;
            const amount = parseInt(document.getElementById('myGoldInput').value) || 0;
            try {
                const res = await fetch(`/api/trade/${tradeState.currentTrade.id}/gold`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: GameState.userId, amount })
                });
                const data = await res.json();
                if (data.error) showMsg(data.error, 'error');
                else { tradeState.currentTrade = data.trade; renderTradeWindow(); }
            } catch (e) { showMsg('Ошибка: ' + e.message, 'error'); }
        }

        async function removeTradeItem(tradeItemId) {
            if (!tradeState.currentTrade) return;
            try {
                const res = await fetch(`/api/trade/${tradeState.currentTrade.id}/item/${tradeItemId}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: GameState.userId })
                });
                const data = await res.json();
                if (data.error) showMsg(data.error, 'error');
                else { tradeState.currentTrade = data.trade; renderTradeWindow(); }
            } catch (e) { showMsg('Ошибка: ' + e.message, 'error'); }
        }

        async function acceptTrade() {
            if (!tradeState.currentTrade) return;
            if (!confirm('Подтвердить обмен?')) return;
            try {
                const res = await fetch(`/api/trade/${tradeState.currentTrade.id}/accept`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: GameState.userId })
                });
                const data = await res.json();
                if (data.error) {
                    showMsg(data.error, 'error');
                } else {
                    showMsg(`✅ ${data.message}`, 'success');
                    if (data.trade.status === 'completed') {
                        closeTradeWindow();
                        await loadTrades();
                    } else {
                        tradeState.currentTrade = data.trade;
                        renderTradeWindow();
                    }
                }
            } catch (e) { showMsg('Ошибка: ' + e.message, 'error'); }
        }

        async function cancelTrade() {
            if (!tradeState.currentTrade) return;
            if (!confirm('Отменить обмен?')) return;
            try {
                const res = await fetch(`/api/trade/${tradeState.currentTrade.id}/cancel`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: GameState.userId })
                });
                const data = await res.json();
                if (data.error) showMsg(data.error, 'error');
                else {
                    showMsg(`✅ ${data.message}`, 'success');
                    closeTradeWindow();
                    await loadTrades();
                }
            } catch (e) { showMsg('Ошибка: ' + e.message, 'error'); }
        }

        window.handleTradeDrop = async function(item) {
            if (!tradeState.currentTrade) {
                showMsg('Сначала откройте активный обмен', 'error');
                return;
            }
            try {
                const res = await fetch(`/api/trade/${tradeState.currentTrade.id}/item`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: GameState.userId, instance_id: item.instance_id, quantity: item.quantity })
                });
                const data = await res.json();
                if (data.error) showMsg(data.error, 'error');
                else { tradeState.currentTrade = data.trade; renderTradeWindow(); }
            } catch (e) { showMsg('Ошибка: ' + e.message, 'error'); }
        };

        // ================================================================
        //                     ИНИЦИАЛИЗАЦИЯ
        // ================================================================
        document.addEventListener('DOMContentLoaded', () => {
            initWorkbench();
            initAuction();
            initTrade();
        });
    </script>
@endpush
