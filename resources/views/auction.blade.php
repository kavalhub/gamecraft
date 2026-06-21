@extends('layouts.game')

@section('title', 'Аукцион')

@push('styles')
    <style>
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

        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab {
            padding: 12px 24px; border-radius: 10px; cursor: pointer;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: #aaa; font-size: 14px; font-weight: 600; transition: all 0.3s;
        }
        .tab.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-color: transparent; }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
        }

        .panel {
            background: rgba(255,255,255,0.05);
            border-radius: 15px; padding: 25px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .panel-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; }

        /* Список лотов */
        .lots-list { display: flex; flex-direction: column; gap: 10px; max-height: 600px; overflow-y: auto; }
        .lot {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; padding: 15px;
            display: grid;
            grid-template-columns: 50px 1fr auto auto;
            gap: 15px; align-items: center;
            transition: all 0.2s;
        }
        .lot:hover { border-color: #667eea; background: rgba(255,255,255,0.06); }
        .lot-icon { font-size: 36px; text-align: center; }
        .lot-info { display: flex; flex-direction: column; gap: 4px; }
        .lot-name { font-weight: 600; font-size: 14px; }
        .lot-meta { font-size: 11px; color: #888; }
        .lot-qty { color: #fbbf24; font-weight: 700; }
        .lot-price {
            font-size: 18px; font-weight: 700; color: #fbbf24;
            display: flex; flex-direction: column; align-items: flex-end;
        }
        .lot-price small { font-size: 10px; color: #888; font-weight: 400; }

        .btn {
            padding: 10px 16px; border: none; border-radius: 8px;
            font-size: 13px; font-weight: 700; cursor: pointer;
            transition: all 0.2s; text-transform: uppercase;
        }
        .btn-buy { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-buy:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16,185,129,0.4); }
        .btn-cancel { background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .btn-cancel:hover { background: rgba(239,68,68,0.3); }

        .status-badge {
            display: inline-block; padding: 3px 8px; border-radius: 4px;
            font-size: 10px; font-weight: 700; text-transform: uppercase;
        }
        .status-active { background: rgba(16,185,129,0.2); color: #10b981; }
        .status-sold { background: rgba(59,130,246,0.2); color: #3b82f6; }
        .status-cancelled { background: rgba(107,114,128,0.2); color: #9ca3af; }

        /* Форма выставления */
        .list-form { display: flex; flex-direction: column; gap: 15px; }
        .form-group label { display: block; font-size: 12px; color: #aaa; margin-bottom: 6px; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px; border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.3); color: #fff; font-size: 14px;
        }
        .selected-item {
            background: rgba(168,85,247,0.1); border: 1px dashed rgba(168,85,247,0.4);
            border-radius: 8px; padding: 12px; text-align: center; min-height: 80px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .selected-item.filled { border-style: solid; background: rgba(168,85,247,0.2); }
        .selected-item .item-icon { font-size: 32px; }
        .selected-item .item-name { font-size: 13px; font-weight: 600; margin-top: 5px; }
        .selected-item .item-qty { font-size: 12px; color: #fbbf24; }

        /* Инвентарь для выбора */
        .inventory-pick { display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 8px; }
        .pick-item {
            background: rgba(255,255,255,0.05); border: 2px solid rgba(255,255,255,0.1);
            border-radius: 8px; padding: 8px; text-align: center;
            cursor: pointer; transition: all 0.2s; user-select: none;
        }
        .pick-item:hover { border-color: #667eea; }
        .pick-item.selected { border-color: #a855f7; background: rgba(168,85,247,0.2); }
        .pick-item[data-type="recipe"] { opacity: 0.4; cursor: not-allowed; }
        .pick-item .item-icon { font-size: 24px; }
        .pick-item .item-name { font-size: 10px; margin-top: 3px; }
        .pick-item .item-qty { font-size: 11px; color: #fbbf24; font-weight: 700; }

        .empty { text-align: center; padding: 40px; color: #666; font-size: 13px; }
        .msg {
            padding: 12px; border-radius: 8px; margin-bottom: 15px;
            display: none; font-size: 14px; text-align: center;
        }
        .msg.success { background: rgba(16,185,129,0.2); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .msg.error { background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .msg.show { display: block; }

        .filter-bar { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .filter-bar select {
            padding: 8px 12px; border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.3); color: #fff; font-size: 13px;
        }
    </style>
@endpush

@section('content')
    <div class="container">
        <div class="header">
            <div class="player-info">
                <div class="player-name" id="playerName">Загрузка...</div>
                <div class="gold" id="playerGold">💰 0</div>
            </div>
            <a href="/inventory" class="btn" style="background:rgba(255,255,255,0.1);color:#aaa">← Инвентарь</a>
        </div>

        <div id="msg" class="msg"></div>

        <div class="tabs">
            <div class="tab active" onclick="switchTab('market')">🏪 Рынок</div>
            <div class="tab" onclick="switchTab('my')">📦 Мои лоты</div>
            <div class="tab" onclick="switchTab('list')">➕ Выставить</div>
        </div>

        <div class="main-grid">
            <!-- Основная панель -->
            <div class="panel">
                <!-- Рынок -->
                <div id="panel-market">
                    <div class="panel-title">🏪 Активные лоты</div>
                    <div class="filter-bar">
                        <select id="filterType" onchange="loadMarket()">
                            <option value="">Все типы</option>
                            <option value="material">Материалы</option>
                            <option value="equipment">Экипировка</option>
                            <option value="consumable">Расходники</option>
                        </select>
                    </div>
                    <div id="marketList" class="lots-list"></div>
                </div>

                <!-- Мои лоты -->
                <div id="panel-my" style="display:none">
                    <div class="panel-title">📦 Мои лоты</div>
                    <div id="myLotsList" class="lots-list"></div>
                </div>

                <!-- Выставить -->
                <div id="panel-list" style="display:none">
                    <div class="panel-title">➕ Выставить предмет</div>
                    <div class="list-form">
                        <div class="form-group">
                            <label>Выберите предмет из инвентаря:</label>
                            <div id="selectedItem" class="selected-item">
                                <div style="color:#888;font-size:12px">Предмет не выбран</div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Ваш инвентарь:</label>
                            <div id="pickInventory" class="inventory-pick"></div>
                        </div>
                        <div class="form-group">
                            <label>Цена (золото):</label>
                            <input type="number" id="listPrice" min="1" value="100" placeholder="Цена">
                        </div>
                        <button class="btn btn-buy" onclick="submitLot()" style="width:100%;padding:14px">
                            📢 Выставить на аукцион
                        </button>
                    </div>
                </div>
            </div>

            <!-- Боковая панель со статистикой -->
            <div class="panel">
                <div class="panel-title">📊 Статистика</div>
                <div id="stats" style="font-size:13px;color:#bbb;line-height:1.8">
                    <div>Активных лотов: <b id="statActive" style="color:#10b981">0</b></div>
                    <div>Ваших лотов: <b id="statMy" style="color:#667eea">0</b></div>
                    <div>Продано вами: <b id="statSold" style="color:#fbbf24">0</b></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const userId = localStorage.getItem('userId');
        if (!userId) { window.location.href = '/'; }

        let inventory = [];
        let selectedItem = null;
        let myLots = [];

        function showMsg(text, type) {
            const el = document.getElementById('msg');
            el.textContent = text;
            el.className = `msg ${type} show`;
            setTimeout(() => el.classList.remove('show'), 3000);
        }

        function getIcon(type) {
            return { material: '📦', equipment: '⚔️', consumable: '🧪', recipe: '📜' }[type] || '📦';
        }

        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            ['market', 'my', 'list'].forEach(t => {
                document.getElementById('panel-' + t).style.display = (t === tab) ? 'block' : 'none';
            });
            if (tab === 'market') loadMarket();
            if (tab === 'my') loadMyLots();
            if (tab === 'list') loadPickInventory();
        }

        async function loadUser() {
            try {
                const res = await fetch(`/api/inventory?user_id=${userId}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.user) {
                    document.getElementById('playerName').textContent = data.user.username;
                    document.getElementById('playerGold').textContent = '💰 ' + data.user.gold;
                }
                inventory = data.inventory || [];
            } catch (e) {
                console.error(e);
            }
        }

        async function loadMarket() {
            try {
                const type = document.getElementById('filterType').value;
                const url = `/api/auction${type ? '?type=' + type : ''}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                renderMarket(data.lots || []);
                document.getElementById('statActive').textContent = (data.lots || []).length;
            } catch (e) {
                showMsg('Ошибка загрузки: ' + e.message, 'error');
            }
        }

        async function loadMyLots() {
            try {
                const res = await fetch(`/api/auction/my?user_id=${userId}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                myLots = data.lots || [];
                renderMyLots(myLots);
                document.getElementById('statMy').textContent = myLots.filter(l => l.status === 'active').length;
                document.getElementById('statSold').textContent = myLots.filter(l => l.status === 'sold').length;
            } catch (e) {
                showMsg('Ошибка загрузки: ' + e.message, 'error');
            }
        }

        function renderMarket(lots) {
            const el = document.getElementById('marketList');
            if (!lots.length) {
                el.innerHTML = '<div class="empty">🏪 Аукцион пуст. Будь первым продавцом!</div>';
                return;
            }
            el.innerHTML = lots.map(lot => `
            <div class="lot">
                <div class="lot-icon">${getIcon(lot.item_type)}</div>
                <div class="lot-info">
                    <div class="lot-name">${lot.item_name} <span class="lot-qty">x${lot.quantity}</span></div>
                    <div class="lot-meta">Продавец: ${lot.seller_name}</div>
                </div>
                <div class="lot-price">
                    ${lot.price} 💰
                    <small>продавец получит ${lot.seller_received}</small>
                </div>
                <button class="btn btn-buy" onclick="buyLot(${lot.id})" ${lot.seller_id == userId ? 'disabled style="opacity:0.4"' : ''}>
                    ${lot.seller_id == userId ? 'Ваш лот' : 'Купить'}
                </button>
            </div>
        `).join('');
        }

        function renderMyLots(lots) {
            const el = document.getElementById('myLotsList');
            if (!lots.length) {
                el.innerHTML = '<div class="empty">У вас пока нет лотов</div>';
                return;
            }
            el.innerHTML = lots.map(lot => {
                const statusClass = 'status-' + lot.status;
                const statusText = { active: 'Активен', sold: 'Продан', cancelled: 'Отменён' }[lot.status];
                return `
                <div class="lot">
                    <div class="lot-icon">${getIcon(lot.item_type)}</div>
                    <div class="lot-info">
                        <div class="lot-name">${lot.item_name} <span class="lot-qty">x${lot.quantity}</span></div>
                        <div class="lot-meta">
                            <span class="status-badge ${statusClass}">${statusText}</span>
                            ${lot.buyer_name ? ' • Покупатель: ' + lot.buyer_name : ''}
                        </div>
                    </div>
                    <div class="lot-price">${lot.price} 💰</div>
                    ${lot.status === 'active'
                      ? `<button class="btn btn-cancel" onclick="cancelLot(${lot.id})">Отменить</button>`
                      : '<div></div>'}
                </div>
            `;
            }).join('');
        }

        function loadPickInventory() {
            const el = document.getElementById('pickInventory');
            const pickable = inventory.filter(i => i.type !== 'recipe');
            if (!pickable.length) {
                el.innerHTML = '<div class="empty">Инвентарь пуст</div>';
                return;
            }
            el.innerHTML = pickable.map(item => `
            <div class="pick-item"
                 data-instance-id="${item.instance_id}"
                 data-template-id="${item.template_id}"
                 data-type="${item.type}"
                 data-quantity="${item.quantity}"
                 data-name="${item.name}"
                 onclick="selectItem(this)">
                <div class="item-icon">${getIcon(item.type)}</div>
                <div class="item-name">${item.name}</div>
                ${item.quantity > 1 ? `<div class="item-qty">x${item.quantity}</div>` : ''}
            </div>
        `).join('');
        }

        function selectItem(el) {
            if (el.dataset.type === 'recipe') return;
            document.querySelectorAll('.pick-item').forEach(i => i.classList.remove('selected'));
            el.classList.add('selected');
            selectedItem = {
                instance_id: parseInt(el.dataset.instanceId),
                template_id: parseInt(el.dataset.templateId),
                quantity: parseInt(el.dataset.quantity),
                name: el.dataset.name,
                type: el.dataset.type,
            };
            const box = document.getElementById('selectedItem');
            box.classList.add('filled');
            box.innerHTML = `
            <div class="item-icon">${getIcon(selectedItem.type)}</div>
            <div class="item-name">${selectedItem.name}</div>
            <div class="item-qty">x${selectedItem.quantity}</div>
        `;
        }

        async function submitLot() {
            if (!selectedItem) {
                showMsg('Выберите предмет', 'error');
                return;
            }
            const price = parseInt(document.getElementById('listPrice').value);
            if (!price || price < 1) {
                showMsg('Укажите корректную цену', 'error');
                return;
            }
            try {
                const res = await fetch('/api/auction', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        user_id: userId,
                        instance_id: selectedItem.instance_id,
                        price: price,
                    })
                });
                const data = await res.json();
                if (data.error) {
                    showMsg(data.error, 'error');
                } else {
                    showMsg(`✅ Лот выставлен за ${price} 💰`, 'success');
                    selectedItem = null;
                    document.getElementById('selectedItem').classList.remove('filled');
                    document.getElementById('selectedItem').innerHTML = '<div style="color:#888;font-size:12px">Предмет не выбран</div>';
                    document.getElementById('listPrice').value = 100;
                    await Promise.all([loadUser(), loadMarket(), loadMyLots()]);
                }
            } catch (e) {
                showMsg('Ошибка: ' + e.message, 'error');
            }
        }

        async function buyLot(lotId) {
            if (!confirm('Купить этот лот?')) return;
            try {
                const res = await fetch(`/api/auction/${lotId}/buy`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: userId })
                });
                const data = await res.json();
                if (data.error) {
                    showMsg(data.error, 'error');
                } else {
                    showMsg(`✅ ${data.message}`, 'success');
                    await Promise.all([loadUser(), loadMarket(), loadMyLots()]);
                }
            } catch (e) {
                showMsg('Ошибка: ' + e.message, 'error');
            }
        }

        async function cancelLot(lotId) {
            if (!confirm('Отменить лот? Предмет вернётся в инвентарь.')) return;
            try {
                const res = await fetch(`/api/auction/${lotId}/cancel`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ user_id: userId })
                });
                const data = await res.json();
                if (data.error) {
                    showMsg(data.error, 'error');
                } else {
                    showMsg(`✅ ${data.message}`, 'success');
                    await Promise.all([loadUser(), loadMarket(), loadMyLots()]);
                }
            } catch (e) {
                showMsg('Ошибка: ' + e.message, 'error');
            }
        }

        // Запуск
        loadUser();
        loadMarket();
        window.GameEvents.start(userId, async () => {
            await loadUser();
            // Если мы на вкладке рынка — обновим
            if (document.getElementById('panel-market').style.display !== 'none') loadMarket();
            if (document.getElementById('panel-my').style.display !== 'none') loadMyLots();
            if (document.getElementById('panel-list').style.display !== 'none') loadPickInventory();
        });
    </script>
@endpush
