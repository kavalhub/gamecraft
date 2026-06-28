<div>
    <div style="display:flex;gap:10px;margin-bottom:20px">
        <button class="auction-tab" data-tab="market" style="padding:10px 20px;border-radius:8px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer">
            🏪 Рынок
        </button>
        <button class="auction-tab" data-tab="my" style="padding:10px 20px;border-radius:8px;background:rgba(255,255,255,0.05);color:#aaa;border:1px solid rgba(255,255,255,0.1);font-size:13px;font-weight:600;cursor:pointer">
            📦 Мои лоты
        </button>
        <button class="auction-tab" data-tab="list" style="padding:10px 20px;border-radius:8px;background:rgba(255,255,255,0.05);color:#aaa;border:1px solid rgba(255,255,255,0.1);font-size:13px;font-weight:600;cursor:pointer">
            ➕ Выставить
        </button>
    </div>

    <div id="auction-market">
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
                    <div style="color:#888;font-size:12px">Двойной клик на предмете из инвентаря</div>
                </div>
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#aaa;margin-bottom:6px">Цена (золото):</label>
                <input type="number" id="listPrice" min="1" value="100" style="width:100%;padding:10px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:14px">
            </div>
            <button id="btnSubmitLot" style="width:100%;padding:12px;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-transform:uppercase">
                📢 Подготовить лот
            </button>
            <button id="btnConfirmLot" style="width:100%;padding:12px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;text-transform:uppercase;display:none">
                ✅ Подтвердить выставление
            </button>
        </div>
    </div>
</div>

<script>
    let auctionState = {
        currentTab: 'market',
        selectedItem: null,
        preparedLot: null,
    };

    window.handleAuctionDrop = function(item) {
        auctionState.selectedItem = item;
        const box = document.getElementById('selectedItem');
        box.style.borderStyle = 'solid';
        box.style.background = 'rgba(168,85,247,0.2)';
        box.innerHTML = `
            <div style="font-size:32px">${item.icon || (item.stage === 'blueprint' ? '📜' : '⚔️')}</div>
            <div style="font-size:13px;font-weight:600;margin-top:5px">${item.name}</div>
            <div style="font-size:10px;color:#888;margin-top:3px">${item.stage === 'blueprint' ? 'Чертёж' : 'Предмет'}</div>
        `;

        window.switchAuctionTab('list');
    };

    window.initAuction = function() {
        // Навешиваем обработчики на вкладки
        document.querySelectorAll('.auction-tab').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tab = e.currentTarget.dataset.tab;
                window.switchAuctionTab(tab);
            });
        });

        document.getElementById('btnSubmitLot').addEventListener('click', window.prepareLot);
        document.getElementById('btnConfirmLot').addEventListener('click', window.confirmLot);

        window.switchAuctionTab('market');
    };

    window.switchAuctionTab = function(tab) {
        auctionState.currentTab = tab;

        document.querySelectorAll('.auction-tab').forEach(t => {
            if (t.dataset.tab === tab) {
                t.style.background = 'linear-gradient(135deg,#667eea,#764ba2)';
                t.style.color = 'white';
                t.style.border = 'none';
            } else {
                t.style.background = 'rgba(255,255,255,0.05)';
                t.style.color = '#aaa';
                t.style.border = '1px solid rgba(255,255,255,0.1)';
            }
        });

        ['market', 'my', 'list'].forEach(t => {
            const el = document.getElementById('auction-' + t);
            if (el) el.style.display = (t === tab) ? 'block' : 'none';
        });

        if (tab === 'market') window.loadMarket();
        if (tab === 'my') window.loadMyLots();
    };

    window.loadMarket = async function() {
        try {
            const res = await GameApi.fetch('/api/auction/lots');
            const data = await res.json();
            window.renderMarket(data.lots || []);
        } catch (e) {
            showMsg('Ошибка загрузки: ' + e.message, 'error');
        }
    };

    window.loadMyLots = async function() {
        try {
            const res = await GameApi.fetch(`/api/auction/${GameState.characterUuid}/my-lots`);
            const data = await res.json();
            window.renderMyLots(data.lots || []);
        } catch (e) {
            showMsg('Ошибка загрузки: ' + e.message, 'error');
        }
    };

    window.renderMarket = function(lots) {
        const el = document.getElementById('marketList');
        if (!lots.length) {
            el.innerHTML = '<div style="text-align:center;padding:40px;color:#666">🏪 Аукцион пуст</div>';
            return;
        }
        el.innerHTML = lots.map(lot => `
            <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:15px;display:grid;grid-template-columns:50px 1fr auto auto;gap:15px;align-items:center">
                <div style="font-size:36px;text-align:center">${lot.template_icon || '📦'}</div>
                <div>
                    <div style="font-weight:600;font-size:14px">${lot.template_name}</div>
                    <div style="font-size:11px;color:#888">Продавец: ${lot.seller_name}</div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:18px;font-weight:700;color:#fbbf24">${lot.price} 💰</div>
                </div>
                <button onclick="window.buyLot('${lot.uuid}')" ${lot.seller_uuid === GameState.characterUuid ? 'disabled style="opacity:0.4"' : ''} style="padding:10px 16px;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">
                    ${lot.seller_uuid === GameState.characterUuid ? 'Ваш лот' : 'Купить'}
                </button>
            </div>
        `).join('');
    };

    window.renderMyLots = function(lots) {
        const el = document.getElementById('myLotsList');
        if (!lots.length) {
            el.innerHTML = '<div style="text-align:center;padding:40px;color:#666">У вас пока нет лотов</div>';
            return;
        }
        el.innerHTML = lots.map(lot => {
            const statusClass = { active: 'background:rgba(16,185,129,0.2);color:#10b981', sold: 'background:rgba(59,130,246,0.2);color:#3b82f6', cancelled: 'background:rgba(107,114,128,0.2);color:#9ca3af' }[lot.status];
            const statusText = { active: 'Активен', sold: 'Продан', cancelled: 'Отменён' }[lot.status];
            return `
                <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:15px;display:grid;grid-template-columns:50px 1fr auto auto;gap:15px;align-items:center">
                    <div style="font-size:36px;text-align:center">${lot.template_icon || '📦'}</div>
                    <div>
                        <div style="font-weight:600;font-size:14px">${lot.template_name}</div>
                        <div style="font-size:11px;color:#888">
                            <span style="display:inline-block;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:700;${statusClass}">${statusText}</span>
                        </div>
                    </div>
                    <div style="font-size:18px;font-weight:700;color:#fbbf24;text-align:right">${lot.price} 💰</div>
                    ${lot.status === 'active'
                      ? `<button onclick="window.cancelLot('${lot.uuid}')" style="padding:10px 16px;background:rgba(239,68,68,0.2);color:#ef4444;border:1px solid rgba(239,68,68,0.3);border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Отменить</button>`
                      : '<div></div>'}
                </div>
            `;
        }).join('');
    };

    window.prepareLot = async function() {
        if (!auctionState.selectedItem) {
            showMsg('Выберите предмет', 'error');
            return;
        }
        const price = parseInt(document.getElementById('listPrice').value);
        if (!price || price < 1) {
            showMsg('Укажите корректную цену', 'error');
            return;
        }
        try {
            const res = await GameApi.fetch(`/api/auction/${GameState.characterUuid}/prepare`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    item_uuid: auctionState.selectedItem.uuid,
                    price: price,
                })
            });
            const data = await res.json();
            if (data.error) {
                showMsg(data.error, 'error');
            } else {
                auctionState.preparedLot = { item_uuid: auctionState.selectedItem.uuid, price };
                showMsg(`✅ Лот подготовлен. Подтвердите выставление.`, 'success');
                document.getElementById('btnConfirmLot').style.display = 'block';
            }
        } catch (e) {
            showMsg('Ошибка: ' + e.message, 'error');
        }
    };

    window.confirmLot = async function() {
        if (!auctionState.preparedLot) return;
        try {
            const res = await GameApi.fetch(`/api/auction/${GameState.characterUuid}/confirm`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(auctionState.preparedLot)
            });
            const data = await res.json();
            if (data.error) {
                showMsg(data.error, 'error');
            } else {
                showMsg(`✅ Лот выставлен за ${auctionState.preparedLot.price} 💰`, 'success');
                auctionState.selectedItem = null;
                auctionState.preparedLot = null;
                document.getElementById('selectedItem').innerHTML = '<div style="color:#888;font-size:12px">Двойной клик на предмете из инвентаря</div>';
                document.getElementById('listPrice').value = 100;
                document.getElementById('btnConfirmLot').style.display = 'none';
                window.switchAuctionTab('my');
            }
        } catch (e) {
            showMsg('Ошибка: ' + e.message, 'error');
        }
    };

    window.buyLot = async function(lotUuid) {
        try {
            const res = await GameApi.fetch(`/api/auction/${GameState.characterUuid}/buy`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ lot_uuid: lotUuid })
            });
            const data = await res.json();
            if (data.error) {
                showMsg(data.error, 'error');
            } else {
                showMsg(`✅ Куплено!`, 'success');
                window.loadMarket();
                loadPlayerData();
            }
        } catch (e) {
            showMsg('Ошибка: ' + e.message, 'error');
        }
    };

    window.cancelLot = async function(lotUuid) {
        try {
            const res = await GameApi.fetch(`/api/auction/${GameState.characterUuid}/cancel`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ lot_uuid: lotUuid })
            });
            const data = await res.json();
            if (data.error) {
                showMsg(data.error, 'error');
            } else {
                showMsg(`✅ Лот отменён`, 'success');
                window.loadMyLots();
                loadPlayerData();
            }
        } catch (e) {
            showMsg('Ошибка: ' + e.message, 'error');
        }
    };
</script>
