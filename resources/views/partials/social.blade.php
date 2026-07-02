<div id="playersTabs" class="game-tabs">
    <button type="button" class="game-tab active" data-tab="trade" onclick="switchPlayersTab('trade')">Обмен</button>
    <button type="button" class="game-tab" data-tab="friends" onclick="switchPlayersTab('friends')">Друзья</button>
    <button type="button" class="game-tab" data-tab="guild" onclick="switchPlayersTab('guild')">Гильдия</button>
</div>
<div id="playersContent"></div>

<script>
window.playersState = {
    characterUuid: null,
    activeTab: 'trade',
};

function tradeApiUrl(path) {
    return `/api/trade/${playersState.characterUuid}${path}`;
}

function duelApiUrl(path) {
    return `/api/duel/${playersState.characterUuid}${path}`;
}

function renderDuelChallengeBox(duel) {
    const content = document.getElementById('playersContent');
    if (!content || !duel) return;

    if (duel.is_challenger) {
        content.innerHTML = `
            <div class="duel-challenge-box" style="margin: 12px;">
                <div style="font-weight: 600;">Ожидание ответа</div>
                <div style="font-size: 12px; color: #aaa; margin-top: 6px;">
                    Вызов на дуэль: ${duel.foe_name || 'игрок'}
                </div>
                <div class="duel-challenge-actions">
                    <button type="button" class="workbench-btn" onclick="declineDuel('${duel.uuid}')">Отменить</button>
                </div>
            </div>
        `;
        return;
    }

    content.innerHTML = `
        <div class="duel-challenge-box" style="margin: 12px;">
            <div style="font-weight: 600;">Вызов на дуэль</div>
            <div style="font-size: 12px; color: #aaa; margin-top: 6px;">
                ${duel.challenger_name || 'Игрок'} вызывает вас на дуэль
            </div>
            <div class="duel-challenge-actions">
                <button type="button" class="workbench-btn workbench-btn--craft" onclick="acceptDuel('${duel.uuid}')">Принять</button>
                <button type="button" class="workbench-btn" onclick="declineDuel('${duel.uuid}')">Отклонить</button>
            </div>
        </div>
    `;
}

function switchPlayersTab(tab) {
    playersState.activeTab = tab;
    document.querySelectorAll('#playersTabs .game-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    if (tab === 'trade') renderTradeTabView();
    else if (tab === 'friends') renderFriendsView();
    else if (tab === 'guild') renderGuildView();
}

function resizePlayersWindow() {
    const win = document.getElementById('window-players');
    if (win) {
        win.style.width = '420px';
        win.style.height = '500px';
    }
}

window.openPlayersWindow = function() {
    if (!playersState.characterUuid) {
        playersState.characterUuid = window.characterUuid;
    }
    playersState.activeTab = 'trade';
    switchPlayersTab('trade');
};

function getTradePartner(trade) {
    if (!trade) return null;
    const myUuid = playersState.characterUuid || window.characterUuid;
    if (trade.initiator && trade.initiator.uuid === myUuid) {
        return trade.partner;
    }
    return trade.initiator;
}

function renderActiveTradePreview(trade) {
    const content = document.getElementById('playersContent');
    if (!content || !trade) return;
    resizePlayersWindow();

    const partner = getTradePartner(trade);
    const partnerName = partner?.name || 'Игрок';
    const partnerUuid = partner?.uuid || '';
    const safeName = partnerName.replace(/\\/g, '\\\\').replace(/'/g, "\\'");

    content.innerHTML = `
        <div class="trade-tab-preview" style="padding: 12px;">
            <p style="color: #aaa; font-size: 12px; margin: 0 0 12px;">Активный обмен</p>
            <div class="online-player-row"
                 data-uuid="${partnerUuid}"
                 data-name="${partnerName.replace(/"/g, '&quot;')}"
                 oncontextmenu="openPlayerContextMenu(event, '${partnerUuid}', '${safeName}'); return false;">
                <span>${partnerName}</span>
            </div>
            <button type="button" class="workbench-btn workbench-btn--craft" style="width: 100%; margin-top: 16px;"
                    onclick="openTradeWindowFromTab()">Открыть обмен</button>
        </div>
    `;
}

function renderTradeTabView() {
    const content = document.getElementById('playersContent');
    if (!content) return;
    if (!playersState.characterUuid) {
        playersState.characterUuid = window.characterUuid;
    }
    if (!playersState.characterUuid) return;

    resizePlayersWindow();

    GameApi.fetch(duelApiUrl('/current'))
        .then(r => r.json())
        .then(duelData => {
            if (duelData.duel && duelData.duel.status === 'pending') {
                renderDuelChallengeBox(duelData.duel);
                return null;
            }
            return GameApi.fetch(tradeApiUrl('/current'));
        })
        .then(function (tradeResponse) {
            if (!tradeResponse) return;
            return tradeResponse.json();
        })
        .then(function (data) {
            if (!data) return;
            if (data.trade) {
                if (window.tradeState) {
                    window.tradeState.currentTrade = typeof mergeTradeSlots === 'function'
                        ? mergeTradeSlots(data.trade)
                        : data.trade;
                }
                renderActiveTradePreview(data.trade);
            } else {
                if (window.tradeState) {
                    window.tradeState.currentTrade = null;
                }
                renderOnlineView();
            }
        })
        .catch(err => {
            console.error('Trade tab load error:', err);
            renderOnlineView();
        });
}

function renderOnlineView() {
    const content = document.getElementById('playersContent');
    if (!content) return;
    resizePlayersWindow();

    GameApi.fetch('/api/online')
        .then(r => r.json())
        .then(data => {
            const onlinePlayers = data.characters.filter(p => p.uuid !== playersState.characterUuid);

            let html = '<div class="online-players-list" style="padding: 12px;">';
            if (onlinePlayers.length === 0) {
                html += '<p style="color: #888;">Нет других игроков онлайн</p>';
            } else {
                html += '<div style="display: flex; flex-direction: column; gap: 8px;">';
                onlinePlayers.forEach(player => {
                    const safeName = player.name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                    html += `
                        <div class="online-player-row"
                             data-uuid="${player.uuid}"
                             data-name="${player.name.replace(/"/g, '&quot;')}"
                             oncontextmenu="openPlayerContextMenu(event, '${player.uuid}', '${safeName}'); return false;">
                            <span>${player.name}</span>
                        </div>
                    `;
                });
                html += '</div>';
            }
            html += '</div>';
            content.innerHTML = html;
        });
}

function renderFriendsView() {
    const content = document.getElementById('playersContent');
    if (!content) return;
    resizePlayersWindow();
    content.innerHTML = '<div style="padding:24px;text-align:center;color:#888;">Раздел «Друзья» в разработке</div>';
}

function renderGuildView() {
    const content = document.getElementById('playersContent');
    if (!content) return;
    resizePlayersWindow();
    content.innerHTML = '<div style="padding:24px;text-align:center;color:#888;">Раздел «Гильдия» в разработке</div>';
}

window.openPlayerContextMenu = function(event, uuid, name) {
    if (typeof window.showPlayerContextMenu === 'function') {
        window.showPlayerContextMenu(event, uuid, name);
    }
};

window.openTradeWindowFromTab = function() {
    if (typeof window.openTradeSession === 'function') {
        window.openTradeSession();
        return;
    }
    WindowManager.open('trade');
    if (typeof window.openTradeWindow === 'function') {
        window.openTradeWindow();
    }
};

window.startTrade = function(partnerUuid) {
    GameApi.fetch(tradeApiUrl('/create'), {
        method: 'POST',
        body: JSON.stringify({ partner_uuid: partnerUuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.trade) {
            if (window.tradeState) {
                window.tradeState.currentTrade = data.trade;
            }
            if (typeof window.openTradeSession === 'function') {
                window.openTradeSession();
            } else {
                WindowManager.open('trade');
                if (typeof window.openTradeWindow === 'function') {
                    window.openTradeWindow();
                }
            }
            if (typeof window.refreshTradeTabIfOpen === 'function') {
                window.refreshTradeTabIfOpen();
            }
        } else {
            showMsg(data.error || 'Ошибка создания обмена', 'error');
        }
    });
};

window.startDuel = function (opponentUuid) {
    GameApi.fetch(duelApiUrl('/challenge'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ opponent_uuid: opponentUuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (typeof showMsg === 'function') showMsg('Вызов на дуэль отправлен', 'success');
            if (typeof window.renderTradeTabView === 'function') window.renderTradeTabView();
        } else {
            showMsg(data.error || 'Ошибка вызова на дуэль', 'error');
        }
    });
};

window.acceptDuel = function (duelUuid) {
    GameApi.fetch(duelApiUrl('/accept'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ duel_uuid: duelUuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.error || 'Ошибка принятия дуэли');
        if (window.WindowManager) WindowManager.open('encounter');
        if (window.EncounterPanel && typeof EncounterPanel.playExternalBattle === 'function') {
            EncounterPanel.playExternalBattle(data);
        }
        if (typeof window.renderTradeTabView === 'function') window.renderTradeTabView();
    })
    .catch(function (e) {
        if (typeof showMsg === 'function') showMsg(e.message, 'error');
    });
};

window.declineDuel = function (duelUuid) {
    GameApi.fetch(duelApiUrl('/decline'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ duel_uuid: duelUuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (typeof showMsg === 'function') showMsg('Дуэль отменена', 'info');
            if (typeof window.renderTradeTabView === 'function') window.renderTradeTabView();
        } else {
            showMsg(data.error || 'Ошибка', 'error');
        }
    });
};

playersState.characterUuid = window.characterUuid;

window.initPlayers = function() {
    openPlayersWindow();
};

window.renderTradeTabView = renderTradeTabView;
window.renderOnlineView = renderOnlineView;
window.switchPlayersTab = switchPlayersTab;
</script>
