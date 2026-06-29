<div id="tradeContent"></div>

<script>
window.tradeState = {
    characterUuid: null,
    currentTrade: null,
};

const SLOT_GAP = 5;

function getTradeSlotSize() {
    return (window.GameSettings && typeof window.GameSettings.getSlotSize === 'function')
        ? window.GameSettings.getSlotSize() : 44;
}

function tradeApiUrl(path) {
    return `/api/trade/${tradeState.characterUuid}${path}`;
}

function mergeTradeSlots(trade) {
    if (window.StorageManager) {
        if (StorageManager.myTradeSlots) trade.my_trade_slots = StorageManager.myTradeSlots;
        if (StorageManager.partnerTradeSlots) trade.partner_trade_slots = StorageManager.partnerTradeSlots;
    }
    return trade;
}

function resizeTradeWindow(cols, rows) {
    const win = document.getElementById('window-trade');
    if (!win) return;
    const slotSize = getTradeSlotSize();
    const headerH = win.querySelector('.window-header')?.offsetHeight || 32;
    const barH = win.querySelector('.trade-participants-bar')?.offsetHeight || 40;
    const footerH = win.querySelector('.trade-footer')?.offsetHeight || 52;
    const gridW = cols * slotSize + (cols - 1) * SLOT_GAP;
    const gridH = rows * slotSize + (rows - 1) * SLOT_GAP;
    const bodyW = gridW * 2 + 24 + 32;
    win.style.width = Math.max(360, bodyW) + 'px';
    win.style.height = (headerH + barH + gridH + 16 + footerH) + 'px';

    if (window.WindowResizer) {
        WindowResizer.register('trade', function() {
            if (tradeState.currentTrade) {
                const slots = tradeState.currentTrade.my_trade_slots || { slots: [], cols: 4 };
                const c = slots.cols || 4;
                const r = Math.ceil((slots.slots || []).length / c) || 5;
                resizeTradeWindow(c, r);
            }
        });
    }
}

window.openTradeWindow = function() {
    if (!tradeState.characterUuid) {
        tradeState.characterUuid = window.characterUuid;
    }

    GameApi.fetch(tradeApiUrl('/current'))
        .then(r => r.json())
        .then(data => {
            if (data.trade) {
                tradeState.currentTrade = mergeTradeSlots(data.trade);
                return refreshTradeData().then(() => renderTradeView());
            }
            tradeState.currentTrade = null;
            renderTradeEmptyView();
        })
        .catch(err => console.error('Fetch error:', err));
};

window.closeTradeWindow = function() {
    if (tradeState.currentTrade) {
        GameApi.fetch(tradeApiUrl('/cancel'), {
            method: 'POST',
            body: JSON.stringify({ trade_uuid: tradeState.currentTrade.uuid }),
        }).then(() => {
            tradeState.currentTrade = null;
        });
    }
};

function renderTradeEmptyView() {
    const content = document.getElementById('tradeContent');
    if (!content) return;
    const win = document.getElementById('window-trade');
    if (win) { win.style.width = '360px'; win.style.height = '200px'; }
    content.innerHTML = '<div style="padding:24px;text-align:center;color:#888;">Нет активного обмена</div>';
}

window.refreshTradeData = function() {
    if (!tradeState.characterUuid) return Promise.resolve();
    return StorageManager.load(tradeState.characterUuid, 'inventory,trade').then(function() {
        if (tradeState.currentTrade) {
            mergeTradeSlots(tradeState.currentTrade);
        }
    });
};

window.renderTradeView = function() {
    const content = document.getElementById('tradeContent');
    const trade = tradeState.currentTrade;
    if (!trade) {
        renderTradeEmptyView();
        return;
    }

    const isInitiator = trade.initiator.uuid === tradeState.characterUuid;
    const partner = isInitiator ? trade.partner : trade.initiator;
    const me = isInitiator ? trade.initiator : trade.partner;

    const myAccepted = isInitiator ? trade.initiator_accepted : trade.partner_accepted;
    const partnerAccepted = isInitiator ? trade.partner_accepted : trade.initiator_accepted;

    const partnerSlots = trade.partner_trade_slots || { slots: [], cols: 4 };
    const mySlots = trade.my_trade_slots || { slots: [], cols: 4 };
    const cols = mySlots.cols || 4;
    const rows = Math.ceil((mySlots.slots || []).length / cols) || 5;

    let html = `
        <div class="trade-layout" style="display:flex;flex-direction:column;height:100%;">
            <div class="trade-participants-bar" style="padding:8px 12px;background:#2a2a2a;border-bottom:1px solid #444;display:flex;justify-content:space-between;align-items:center;font-size:13px;">
                <div><strong>${partner.name}</strong> <span>${partnerAccepted ? '✅' : '⏳'}</span></div>
                <div style="font-size:18px;opacity:0.7;">⇄</div>
                <div><strong>${me.name}</strong> <span>${myAccepted ? '✅' : '⏳'}</span></div>
            </div>

            <div style="display:flex;flex:1;overflow:hidden;padding:8px 12px;gap:12px;align-items:flex-start;">
                <div style="flex:1;display:flex;justify-content:center;min-width:0;">
                    <div id="partnerTradeGrid"></div>
                </div>
                <div style="flex:1;display:flex;justify-content:center;min-width:0;">
                    <div id="myTradeGrid"></div>
                </div>
            </div>

            <div class="trade-footer" style="padding:10px 12px;background:#2a2a2a;border-top:1px solid #444;display:flex;justify-content:space-between;">
                <button onclick="cancelTrade()" class="btn btn-danger" style="padding:8px 16px;">✕ Отменить</button>
                <button onclick="confirmTrade()" class="btn btn-success" style="padding:8px 20px;">✅ Принять</button>
            </div>
        </div>
    `;

    content.innerHTML = html;

    if (window.StorageGrid) {
        StorageGrid.mount(document.getElementById('partnerTradeGrid'), partnerSlots, {
            readonly: true,
            draggable: false,
            gridId: 'partner-trade-grid',
            compact: true,
        });

        StorageGrid.mount(document.getElementById('myTradeGrid'), mySlots, {
            draggable: true,
            gridId: 'my-trade-grid',
            compact: true,
        });
    }

    resizeTradeWindow(cols, rows);
};

function isResourceItem(item) {
    if (!item.template_slug) return false;
    if (item.stage === 'blueprint' || item.stage === 'item') return false;
    if (item.recipe_slug) return false;
    return item.quantity != null && item.quantity > 0;
}

function addResourceToTrade(item, quantity) {
    GameApi.fetch(tradeApiUrl('/add-resource'), {
        method: 'POST',
        body: JSON.stringify({
            trade_uuid: tradeState.currentTrade.uuid,
            template_slug: item.template_slug,
            quantity: quantity,
        }),
    })
    .then(async (r) => {
        const data = await r.json();
        if (r.ok && data.success) {
            if (data.trade) tradeState.currentTrade = mergeTradeSlots(data.trade);
            await refreshTradeData();
            renderTradeView();
            loadPlayerData();
            showMsg(`Добавлено: ${item.name} ×${quantity}`, 'success');
        } else {
            showMsg(data.error || data.message || 'Ошибка добавления ресурса', 'error');
        }
    })
    .catch(err => {
        console.error('addResourceToTrade error:', err);
        showMsg('Ошибка сети при добавлении ресурса', 'error');
    });
}

window.handleTradeDrop = function(item, options) {
    options = options || {};
    if (!tradeState.currentTrade) {
        showMsg('Сначала начните обмен с игроком', 'error');
        return;
    }

    if (isResourceItem(item)) {
        if (options.fullStack) {
            addResourceToTrade(item, item.quantity);
            return;
        }
        ResourceQuantityModal.open({
            name: item.name,
            icon: item.icon,
            available: item.quantity,
            maxStack: item.max_stack,
            onConfirm: (qty) => addResourceToTrade(item, qty),
        });
        return;
    }

    GameApi.fetch(tradeApiUrl('/add-item'), {
        method: 'POST',
        body: JSON.stringify({
            trade_uuid: tradeState.currentTrade.uuid,
            item_uuid: item.uuid,
        }),
    })
    .then(r => r.json())
    .then(async data => {
        if (data.success) {
            if (data.trade) tradeState.currentTrade = mergeTradeSlots(data.trade);
            await refreshTradeData();
            renderTradeView();
            loadPlayerData();
            showMsg('Предмет добавлен в обмен', 'success');
        } else {
            showMsg(data.error || 'Ошибка', 'error');
        }
    });
};

function refreshCurrentTrade() {
    return Promise.all([
        GameApi.fetch(tradeApiUrl('/current')).then(r => r.json()),
        refreshTradeData(),
    ]).then(([data]) => {
        if (data.trade) {
            tradeState.currentTrade = mergeTradeSlots(data.trade);
            if (!WindowManager.isOpen('trade')) {
                WindowManager.open('trade');
            }
            renderTradeView();
        } else if (tradeState.currentTrade) {
            tradeState.currentTrade = null;
            if (WindowManager.isOpen('trade')) {
                renderTradeEmptyView();
            }
            loadPlayerData();
        }
    });
}

window.confirmTrade = function() {
    if (!tradeState.currentTrade) return;

    GameApi.fetch(tradeApiUrl('/confirm'), {
        method: 'POST',
        body: JSON.stringify({ trade_uuid: tradeState.currentTrade.uuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.trade.status === 'completed') {
                showMsg('🎉 Обмен завершён!', 'success');
                tradeState.currentTrade = null;
                WindowManager.close('trade');
                loadPlayerData();
            } else {
                tradeState.currentTrade = mergeTradeSlots(data.trade);
                renderTradeView();
                showMsg('Вы приняли обмен. Ожидание партнёра...', 'info');
            }
        } else {
            showMsg(data.error || 'Ошибка подтверждения', 'error');
        }
    });
};

window.cancelTrade = function() {
    if (!tradeState.currentTrade) return;

    GameApi.fetch(tradeApiUrl('/cancel'), {
        method: 'POST',
        body: JSON.stringify({ trade_uuid: tradeState.currentTrade.uuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            tradeState.currentTrade = null;
            WindowManager.close('trade');
            loadPlayerData();
            showMsg('Обмен отменён', 'info');
        } else {
            showMsg(data.error || 'Ошибка отмены', 'error');
        }
    });
};

window.onTradeCompleted = function() {
    tradeState.currentTrade = null;
    if (WindowManager.isOpen('trade')) {
        WindowManager.close('trade');
    }
    loadPlayerData();
    showMsg('🎉 Обмен завершён!', 'success');
};

window.loadTrades = function() {
    refreshCurrentTrade();
};

tradeState.characterUuid = window.characterUuid;

window.initTrade = function() {
    openTradeWindow();
};
</script>
