<!-- Trade Window -->
<div id="tradeWindow" class="window" data-window="trade" style="width: 800px; height: 500px;">
    <div class="window-header">
        <span class="window-icon">🤝</span>
        <span class="window-title">Обмен</span>
        <button class="window-close" onclick="WindowManager.close('trade')">✕</button>
    </div>
    <div class="window-content" id="tradeContent">
        <!-- Контент будет динамически загружаться -->
    </div>
</div>

<script>
window.tradeState = {
    characterUuid: null,
    currentTrade: null,
    view: 'online',
};

function tradeApiUrl(path) {
    return `/api/trade/${tradeState.characterUuid}${path}`;
}

window.openTradeWindow = function() {
    if (!tradeState.characterUuid) {
        tradeState.characterUuid = window.characterUuid;
    }

    GameApi.fetch(tradeApiUrl('/current'))
        .then(r => r.json())
        .then(data => {
            if (data.trade) {
                tradeState.currentTrade = data.trade;
                tradeState.view = 'trade';
                renderTradeView();
            } else {
                tradeState.view = 'online';
                renderOnlineView();
            }
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
            tradeState.view = 'online';
        });
    }
};

function renderOnlineView() {
    const content = document.getElementById('tradeContent');

    GameApi.fetch('/api/online')
        .then(r => r.json())
        .then(data => {
            const onlinePlayers = data.characters.filter(p => p.uuid !== tradeState.characterUuid);

            let html = '<div style="padding: 20px;">';
            html += '<h3 style="margin-bottom: 20px;">Игроки онлайн</h3>';

            if (onlinePlayers.length === 0) {
                html += '<p style="color: #888;">Нет других игроков онлайн</p>';
            } else {
                html += '<div style="display: flex; flex-direction: column; gap: 10px;">';
                onlinePlayers.forEach(player => {
                    html += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #2a2a2a; border-radius: 4px;">
                            <span>${player.name}</span>
                            <button onclick="startTrade('${player.uuid}')" class="btn btn-primary">
                                🤝 Обмен
                            </button>
                        </div>
                    `;
                });
                html += '</div>';
            }

            html += '</div>';
            content.innerHTML = html;
        });
}

window.startTrade = function(partnerUuid) {
    GameApi.fetch(tradeApiUrl('/create'), {
        method: 'POST',
        body: JSON.stringify({ partner_uuid: partnerUuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.trade) {
            tradeState.currentTrade = data.trade;
            tradeState.view = 'trade';
            renderTradeView();
        } else {
            showMsg(data.error || 'Ошибка создания обмена', 'error');
        }
    });
};

function renderTradeView() {
    const content = document.getElementById('tradeContent');
    const trade = tradeState.currentTrade;

    const isInitiator = trade.initiator.uuid === tradeState.characterUuid;
    const partner = isInitiator ? trade.partner : trade.initiator;
    const me = isInitiator ? trade.initiator : trade.partner;

    const myAccepted = isInitiator ? trade.initiator_accepted : trade.partner_accepted;
    const partnerAccepted = isInitiator ? trade.partner_accepted : trade.initiator_accepted;

    let html = `
        <div style="display: flex; flex-direction: column; height: 100%;">
            <div style="padding: 15px; background: #2a2a2a; border-bottom: 1px solid #444;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>${me.name}</strong>
                        <span style="margin-left: 10px;">${myAccepted ? '✅' : '⏳'}</span>
                    </div>
                    <div style="font-size: 24px;">⇄</div>
                    <div>
                        <strong>${partner.name}</strong>
                        <span style="margin-left: 10px;">${partnerAccepted ? '✅' : '⏳'}</span>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex: 1; overflow: hidden;">
                <div style="flex: 1; padding: 15px; border-right: 1px solid #444; overflow-y: auto;">
                    <h4 style="margin-bottom: 10px;">Предметы ${partner.name}</h4>
                    <div id="partnerItems" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 5px;">
                        ${renderTradeItems(trade.items.filter(i => i.character_uuid === partner.uuid))}
                    </div>
                </div>

                <div style="flex: 1; padding: 15px; overflow-y: auto;">
                    <h4 style="margin-bottom: 10px;">Мои предметы</h4>
                    <div id="myItems" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 5px;">
                        ${renderTradeItems(trade.items.filter(i => i.character_uuid === me.uuid))}
                    </div>
                </div>
            </div>

            <div style="padding: 15px; background: #2a2a2a; border-top: 1px solid #444; display: flex; justify-content: space-between;">
                <button onclick="cancelTrade()" class="btn btn-danger" style="padding: 10px 20px;">✕ Отменить</button>
                <button onclick="confirmTrade()" class="btn btn-success" style="padding: 10px 30px;">✅ Принять</button>
            </div>
        </div>
    `;

    content.innerHTML = html;
}

function renderTradeItems(items) {
    if (items.length === 0) {
        return '<p style="color: #888; grid-column: 1/-1;">Пока пусто</p>';
    }

    return items.map(item => {
        const icon = item.item_uuid ? '📦' : '📊';
        const name = item.template_slug || 'Предмет';
        const qty = item.quantity > 1 ? ` ×${item.quantity}` : '';

        return `
            <div class="trade-item" style="padding: 10px; background: #3a3a3a; border-radius: 4px; text-align: center;" title="${name}">
                <div style="font-size: 24px;">${icon}</div>
                <div style="font-size: 11px; margin-top: 5px;">${name}${qty}</div>
            </div>
        `;
    }).join('');
}

window.handleTradeDrop = function(item) {
    if (!tradeState.currentTrade) {
        showMsg('Сначала начните обмен с игроком', 'error');
        return;
    }

    if (item.template_slug && item.quantity !== undefined && !item.stage) {
        GameApi.fetch(tradeApiUrl('/add-resource'), {
            method: 'POST',
            body: JSON.stringify({
                trade_uuid: tradeState.currentTrade.uuid,
                template_slug: item.template_slug,
                quantity: 1,
            }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                refreshCurrentTrade();
                showMsg('Ресурс добавлен в обмен', 'success');
            } else {
                showMsg(data.error || 'Ошибка', 'error');
            }
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
    .then(data => {
        if (data.success) {
            refreshCurrentTrade();
            showMsg('Предмет добавлен в обмен', 'success');
        } else {
            showMsg(data.error || 'Ошибка', 'error');
        }
    });
};

function refreshCurrentTrade() {
    GameApi.fetch(tradeApiUrl('/current'))
        .then(r => r.json())
        .then(data => {
            if (data.trade) {
                tradeState.currentTrade = data.trade;
                renderTradeView();
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
                tradeState.currentTrade = data.trade;
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
            tradeState.view = 'online';
            renderOnlineView();
            showMsg('Обмен отменён', 'info');
        } else {
            showMsg(data.error || 'Ошибка отмены', 'error');
        }
    });
};

window.loadTrades = function() {
    refreshCurrentTrade();
};

tradeState.characterUuid = window.characterUuid;

window.initTrade = function() {
    openTradeWindow();
};
</script>
