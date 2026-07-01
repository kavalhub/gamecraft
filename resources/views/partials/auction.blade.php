<div>
    <div id="auctionTabs" class="game-tabs" style="margin-bottom:16px">
        <button type="button" class="game-tab active" data-tab="market">🏪 Рынок</button>
        <button type="button" class="game-tab" data-tab="my">📦 Мои лоты</button>
        <button type="button" class="game-tab" data-tab="list">➕ Выставить</button>
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
                Выставить
            </button>
        </div>
    </div>
</div>

<script>
    let auctionState = {
        currentTab: 'market',
        selectedItem: null,
        marketLots: [],
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
        document.querySelectorAll('#auctionTabs .game-tab').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tab = e.currentTarget.dataset.tab;
                window.switchAuctionTab(tab);
            });
        });

        document.getElementById('btnSubmitLot').addEventListener('click', window.submitLot);

        window.switchAuctionTab('market');
    };

    window.switchAuctionTab = function(tab) {
        auctionState.currentTab = tab;

        document.querySelectorAll('#auctionTabs .game-tab').forEach(t => {
            t.classList.toggle('active', t.dataset.tab === tab);
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
            const res = await GameApi.fetch(`/api/auction/lots?character_uuid=${GameState.characterUuid}`);
            const data = await res.json();
            auctionState.marketLots = data.lots || [];
            window.renderMarket(auctionState.marketLots);
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

    function formatLotQuantity(lot) {
        if (lot.is_infinite) {
            return '∞';
        }
        return `×${lot.quantity}`;
    }

    function auctionLotTooltipAttrs(lot) {
        const isResource = lot.template_type === 'material';
        const stage = lot.template_type === 'blueprint' ? 'blueprint' : (isResource ? '' : 'item');
        const qty = lot.is_infinite ? 1 : (lot.quantity || 1);
        const desc = (lot.template_description || '').replace(/"/g, '&quot;');
        return `
            class="game-item-interactive"
            data-template-slug="${lot.template_slug || ''}"
            data-name="${(lot.template_name || '').replace(/"/g, '&quot;')}"
            data-icon="${lot.template_icon || '📦'}"
            data-stage="${stage}"
            data-quantity="${qty}"
            data-description="${desc}"
            data-stats="{}"
            data-max-stack="${lot.max_stack ?? ''}"
        `;
    }

    window.renderMarket = function(lots) {
        const el = document.getElementById('marketList');
        if (!lots.length) {
            el.innerHTML = '<div style="text-align:center;padding:40px;color:#666">🏪 Аукцион пуст</div>';
            return;
        }
        el.innerHTML = lots.map(lot => {
            const isOwn = lot.seller_uuid === GameState.characterUuid;
            const qtyLabel = formatLotQuantity(lot);
            return `
            <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:15px;display:grid;grid-template-columns:50px 1fr auto auto auto;gap:15px;align-items:center">
                <div style="font-size:36px;text-align:center;cursor:help"
                     ${auctionLotTooltipAttrs(lot)}>
                    ${lot.template_icon || '📦'}
                </div>
                <div>
                    <div style="font-weight:600;font-size:14px">${lot.template_name}</div>
                    <div style="font-size:11px;color:#888">Продавец: ${lot.seller_name}</div>
                </div>
                <div style="text-align:center;font-size:16px;font-weight:600;color:#ccc;min-width:40px">${qtyLabel}</div>
                <div style="text-align:right">
                    <div style="font-size:18px;font-weight:700;color:#fbbf24">${lot.price} 💰</div>
                    ${lot.is_infinite ? '<div style="font-size:10px;color:#888">за шт.</div>' : ''}
                </div>
                <button onclick="window.promptBuyLot('${lot.uuid}')" ${isOwn ? 'disabled style="opacity:0.4"' : ''} style="padding:10px 16px;background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">
                    ${isOwn ? 'Ваш лот' : 'Купить'}
                </button>
            </div>
        `;
        }).join('');

        window.bindItemTooltips = window.bindItemTooltips || function () {};

        window.bindItemTooltips(el);
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
            const qtyLabel = formatLotQuantity(lot);
            return `
                <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:15px;display:grid;grid-template-columns:50px 1fr auto auto auto;gap:15px;align-items:center">
                    <div style="font-size:36px;text-align:center;cursor:help"
                         ${auctionLotTooltipAttrs(lot)}>
                        ${lot.template_icon || '📦'}
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:14px">${lot.template_name}</div>
                        <div style="font-size:11px;color:#888">
                            <span style="display:inline-block;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:700;${statusClass}">${statusText}</span>
                        </div>
                    </div>
                    <div style="text-align:center;font-size:16px;font-weight:600;color:#ccc">${qtyLabel}</div>
                    <div style="font-size:18px;font-weight:700;color:#fbbf24;text-align:right">${lot.price} 💰</div>
                    ${lot.status === 'active' && !lot.is_infinite
                      ? `<button onclick="window.cancelLot('${lot.uuid}')" style="padding:10px 16px;background:rgba(239,68,68,0.2);color:#ef4444;border:1px solid rgba(239,68,68,0.3);border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Отменить</button>`
                      : '<div></div>'}
                </div>
            `;
        }).join('');

        window.bindItemTooltips = window.bindItemTooltips || function () {};

        window.bindItemTooltips(el);
    };

    window.promptBuyLot = async function(lotUuid) {
        const lot = auctionState.marketLots.find(l => l.uuid === lotUuid);
        if (!lot) return;

        const isInfinite = lot.is_infinite === true || lot.is_infinite === 1;
        const isBulkResource = isInfinite && lot.template_type === 'material';

        if (isBulkResource) {
            let info = { ...lot };
            try {
                const res = await GameApi.fetch(`/api/auction/${GameState.characterUuid}/lot/${lotUuid}/buy-info`);
                const body = await res.json();
                if (res.ok) {
                    info = { ...lot, ...body };
                } else {
                    showMsg(body.error || body.message || `Ошибка загрузки лимитов (${res.status})`, 'error');
                    if (body.max_purchasable == null && lot.max_purchasable == null) {
                        return;
                    }
                }
            } catch (e) {
                showMsg('Ошибка: ' + e.message, 'error');
                if (lot.max_purchasable == null) return;
            }

            const maxByGold = Number(info.max_by_gold ?? lot.max_by_gold ?? 0);
            const maxByInventory = Number(info.max_by_inventory ?? lot.max_by_inventory ?? 0);
            const maxPurchasable = Number(info.max_purchasable ?? lot.max_purchasable ?? 0);
            const price = Number(info.price ?? lot.price ?? 0);

            let limitsText = `Золото: до ${maxByGold} шт. · инвентарь: до ${maxByInventory} шт.`;
            let subtitle;
            if (maxPurchasable >= 1) {
                subtitle = `${limitsText} · макс. ${maxPurchasable} шт. · ${price} 💰/шт.`;
            } else if (maxByGold < 1) {
                subtitle = `Недостаточно золота (есть ${info.gold_available ?? 0} 💰, нужно ${price} 💰/шт.)`;
            } else if (maxByInventory < 1) {
                subtitle = `${limitsText}. Нет свободных слотов в инвентаре.`;
            } else {
                subtitle = `${limitsText}. Недостаточно золота или места в инвентаре.`;
            }

            ResourceQuantityModal.open({
                name: info.template_name || lot.template_name,
                icon: info.template_icon || lot.template_icon,
                available: Math.max(1, maxPurchasable),
                maxStack: info.max_stack ?? lot.max_stack,
                pricePerUnit: price,
                confirmLabel: 'Купить',
                subtitle,
                defaultToMax: false,
                confirmDisabled: maxPurchasable < 1,
                onConfirm: (qty) => window.buyLot(lotUuid, qty),
            });
            return;
        }

        if (isInfinite) {
            const maxPurchasable = Number(lot.max_purchasable ?? 0);
            if (maxPurchasable < 1) {
                showMsg('Нельзя купить: нет места в инвентаре, недостаточно золота или предмет уже есть', 'error');
                return;
            }
            window.buyLot(lotUuid, 1);
            return;
        }

        window.buyLot(lotUuid, lot.quantity || 1);
    };

    window.submitLot = async function() {
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
            const res = await GameApi.fetch(`/api/auction/${GameState.characterUuid}/list`, {
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
                showMsg(`Лот выставлен за ${price} 💰`, 'success');
                auctionState.selectedItem = null;
                document.getElementById('selectedItem').innerHTML = '<div style="color:#888;font-size:12px">Двойной клик на предмете из инвентаря</div>';
                document.getElementById('listPrice').value = 100;
                window.switchAuctionTab('my');
                if (typeof loadPlayerData === 'function') loadPlayerData();
            }
        } catch (e) {
            showMsg('Ошибка: ' + e.message, 'error');
        }
    };

    window.buyLot = async function(lotUuid, quantity = 1) {
        try {
            const res = await GameApi.fetch(`/api/auction/${GameState.characterUuid}/buy`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ lot_uuid: lotUuid, quantity })
            });
            const data = await res.json();
            if (data.error) {
                showMsg(data.error, 'error');
            } else {
                showMsg(`✅ Куплено${quantity > 1 ? ` ×${quantity}` : ''}!`, 'success');
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
