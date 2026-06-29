<div id="playersTabs" class="game-tabs">
    <button type="button" class="game-tab active" data-tab="online" onclick="switchPlayersTab('online')">Онлайн</button>
    <button type="button" class="game-tab" data-tab="friends" onclick="switchPlayersTab('friends')">Друзья</button>
    <button type="button" class="game-tab" data-tab="guild" onclick="switchPlayersTab('guild')">Гильдия</button>
</div>
<div id="playersContent"></div>

<script>
window.playersState = {
    characterUuid: null,
    activeTab: 'online',
};

function tradeApiUrl(path) {
    return `/api/trade/${playersState.characterUuid}${path}`;
}

function switchPlayersTab(tab) {
    playersState.activeTab = tab;
    document.querySelectorAll('#playersTabs .game-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    if (tab === 'online') renderOnlineView();
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
    playersState.activeTab = 'online';
    switchPlayersTab('online');
};

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
            WindowManager.open('trade');
            if (typeof window.openTradeWindow === 'function') {
                window.openTradeWindow();
            }
        } else {
            showMsg(data.error || 'Ошибка создания обмена', 'error');
        }
    });
};

playersState.characterUuid = window.characterUuid;

window.initPlayers = function() {
    openPlayersWindow();
};

window.renderOnlineView = renderOnlineView;
window.switchPlayersTab = switchPlayersTab;
</script>
