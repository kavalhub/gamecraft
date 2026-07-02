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

function friendsApiUrl(path) {
    return `/api/friends/${playersState.characterUuid}${path}`;
}

function guildApiUrl(path) {
    return `/api/guild/${playersState.characterUuid}${path}`;
}

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
                            <span>${player.avatar_icon || '🧙'} ${player.name}</span>
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
    if (!playersState.characterUuid) playersState.characterUuid = window.characterUuid;
    resizePlayersWindow();

    GameApi.fetch(friendsApiUrl(''))
        .then(r => r.json())
        .then(data => {
            let html = '<div style="padding:12px;">';

            if ((data.incoming_requests || []).length) {
                html += '<p style="color:#aaa;font-size:12px;margin:0 0 8px;">Входящие заявки</p>';
                data.incoming_requests.forEach(req => {
                    const c = req.character || {};
                    html += `<div class="online-player-row" style="justify-content:space-between;">
                        <span>${c.avatar_icon || '🧙'} ${c.name || 'Игрок'}</span>
                        <button type="button" class="workbench-btn workbench-btn--craft" style="padding:4px 8px;font-size:11px;"
                            onclick="acceptFriend('${req.uuid}')">Принять</button>
                    </div>`;
                });
                html += '<hr style="border-color:#333;margin:12px 0;">';
            }

            const friends = data.friends || [];
            if (!friends.length) {
                html += '<p style="color:#888;text-align:center;padding:16px 0;">Список друзей пуст.<br>Добавьте игроков через контекстное меню.</p>';
            } else {
                html += '<p style="color:#aaa;font-size:12px;margin:0 0 8px;">Друзья (' + friends.length + ')</p>';
                friends.forEach(f => {
                    const safeName = (f.name || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                    html += `<div class="online-player-row" data-uuid="${f.uuid}" data-name="${(f.name || '').replace(/"/g, '&quot;')}"
                        oncontextmenu="openPlayerContextMenu(event, '${f.uuid}', '${safeName}'); return false;"
                        style="justify-content:space-between;">
                        <span>${f.avatar_icon || '🧙'} ${f.name}</span>
                        <button type="button" class="workbench-btn" style="padding:4px 8px;font-size:11px;"
                            onclick="removeFriend('${f.uuid}')">Удалить</button>
                    </div>`;
                });
            }

            html += '</div>';
            content.innerHTML = html;
        })
        .catch(err => {
            console.error('Friends load error:', err);
            content.innerHTML = '<div style="padding:24px;text-align:center;color:#888;">Ошибка загрузки друзей</div>';
        });
}

window.acceptFriend = function(friendshipUuid) {
    GameApi.fetch(friendsApiUrl('/accept'), {
        method: 'POST',
        body: JSON.stringify({ friendship_uuid: friendshipUuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (typeof showMsg === 'function') showMsg('Друг добавлен', 'success');
            renderFriendsView();
        } else {
            showMsg(data.error || 'Ошибка', 'error');
        }
    });
};

window.removeFriend = function(targetUuid) {
    GameApi.fetch(friendsApiUrl('/remove'), {
        method: 'POST',
        body: JSON.stringify({ target_uuid: targetUuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) renderFriendsView();
        else showMsg(data.error || 'Ошибка', 'error');
    });
};

window.requestFriend = function(targetUuid) {
    GameApi.fetch(friendsApiUrl('/request'), {
        method: 'POST',
        body: JSON.stringify({ target_uuid: targetUuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (typeof showMsg === 'function') showMsg('Заявка отправлена', 'success');
        } else {
            showMsg(data.error || 'Ошибка', 'error');
        }
    });
};

function renderGuildView() {
    const content = document.getElementById('playersContent');
    if (!content) return;
    if (!playersState.characterUuid) playersState.characterUuid = window.characterUuid;
    resizePlayersWindow();

    Promise.all([
        GameApi.fetch(guildApiUrl('')).then(r => r.json()),
        GameApi.fetch('/api/game/meta').then(r => r.json()),
    ])
    .then(([data, meta]) => {
        let html = '<div style="padding:12px;">';

        if ((data.invites || []).length) {
            html += '<p style="color:#aaa;font-size:12px;">Приглашения в гильдию</p>';
            data.invites.forEach(inv => {
                html += `<div class="duel-challenge-box" style="margin-bottom:8px;">
                    <div>${inv.guild_emblem_icon || '🛡️'} ${inv.guild_name}</div>
                    <div style="font-size:11px;color:#888;">от ${inv.inviter_name || 'игрока'}</div>
                    <div class="duel-challenge-actions">
                        <button type="button" class="workbench-btn workbench-btn--craft" onclick="joinGuild('${inv.guild_uuid}')">Вступить</button>
                        <button type="button" class="workbench-btn" onclick="declineGuildInvite('${inv.uuid}')">Отклонить</button>
                    </div>
                </div>`;
            });
            html += '<hr style="border-color:#333;margin:12px 0;">';
        }

        if (data.guild) {
            const g = data.guild;
            html += `<div style="text-align:center;margin-bottom:12px;">
                <div style="font-size:32px;">${g.emblem_icon || '🛡️'}</div>
                <div style="font-weight:600;font-size:16px;">${g.name}</div>
                <div style="font-size:12px;color:#888;">Участников: ${g.member_count || 0}</div>
            </div>`;
            html += '<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px;">';
            (g.members || []).forEach(m => {
                html += `<div class="online-player-row"><span>${m.avatar_icon || '🧙'} ${m.name} ${m.role === 'leader' ? '👑' : ''}</span></div>`;
            });
            html += '</div>';
            html += `<button type="button" class="workbench-btn" style="width:100%;" onclick="leaveGuild()">Покинуть гильдию</button>`;
            html += `<button type="button" class="workbench-btn workbench-btn--craft" style="width:100%;margin-top:8px;" onclick="openGuildBank()">Банк гильдии</button>`;
        } else {
            html += '<p style="color:#aaa;font-size:12px;margin-bottom:8px;">Создать гильдию</p>';
            html += '<input type="text" id="guildNameInput" maxlength="40" placeholder="Название" style="width:100%;margin-bottom:8px;padding:8px;border-radius:6px;border:1px solid #444;background:#1a1a2e;color:#eee;">';
            html += '<div id="guildEmblemGrid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:8px;"></div>';
            html += '<input type="hidden" id="guildEmblemInput" value="shield">';
            html += '<button type="button" class="workbench-btn workbench-btn--craft" style="width:100%;margin-bottom:16px;" onclick="createGuild()">Создать</button>';

            html += '<p style="color:#aaa;font-size:12px;margin-bottom:8px;">Или вступить</p>';
            const catalog = data.catalog || [];
            if (!catalog.length) {
                html += '<p style="color:#666;font-size:12px;">Пока нет гильдий</p>';
            } else {
                catalog.forEach(g => {
                    html += `<div class="online-player-row" style="justify-content:space-between;">
                        <span>${g.emblem_icon || '🛡️'} ${g.name} (${g.member_count})</span>
                        <button type="button" class="workbench-btn workbench-btn--craft" style="padding:4px 8px;font-size:11px;"
                            onclick="joinGuild('${g.uuid}')">Вступить</button>
                    </div>`;
                });
            }
        }

        html += '</div>';
        content.innerHTML = html;

        if (!data.guild && meta.guild_emblems) {
            const grid = document.getElementById('guildEmblemGrid');
            if (grid) {
                let selected = 'shield';
                grid.innerHTML = Object.entries(meta.guild_emblems).map(([key, em]) =>
                    `<button type="button" class="workbench-btn${key === selected ? ' workbench-btn--craft' : ''}" data-emblem="${key}" style="font-size:20px;padding:8px;">${em.icon}</button>`
                ).join('');
                grid.querySelectorAll('button').forEach(btn => {
                    btn.addEventListener('click', () => {
                        selected = btn.dataset.emblem;
                        document.getElementById('guildEmblemInput').value = selected;
                        grid.querySelectorAll('button').forEach(b => b.classList.remove('workbench-btn--craft'));
                        btn.classList.add('workbench-btn--craft');
                    });
                });
            }
        }
    })
    .catch(err => {
        console.error('Guild load error:', err);
        content.innerHTML = '<div style="padding:24px;text-align:center;color:#888;">Ошибка загрузки гильдии</div>';
    });
}

window.createGuild = function() {
    const name = document.getElementById('guildNameInput')?.value?.trim();
    const emblem = document.getElementById('guildEmblemInput')?.value || 'shield';
    if (!name) { showMsg('Введите название гильдии', 'error'); return; }
    GameApi.fetch(guildApiUrl('/create'), {
        method: 'POST',
        body: JSON.stringify({ name, emblem }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMsg('Гильдия создана!', 'success');
            renderGuildView();
        } else showMsg(data.error || 'Ошибка', 'error');
    });
};

window.joinGuild = function(guildUuid) {
    GameApi.fetch(guildApiUrl('/join'), {
        method: 'POST',
        body: JSON.stringify({ guild_uuid: guildUuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMsg('Вы вступили в гильдию', 'success');
            renderGuildView();
        } else showMsg(data.error || 'Ошибка', 'error');
    });
};

window.leaveGuild = function() {
    GameApi.fetch(guildApiUrl('/leave'), { method: 'POST' })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMsg('Вы покинули гильдию', 'info');
            renderGuildView();
        } else showMsg(data.error || 'Ошибка', 'error');
    });
};

window.declineGuildInvite = function(inviteUuid) {
    GameApi.fetch(guildApiUrl('/decline-invite'), {
        method: 'POST',
        body: JSON.stringify({ invite_uuid: inviteUuid }),
    })
    .then(r => r.json())
    .then(data => { if (data.success) renderGuildView(); });
};

window.inviteToGuild = function(targetUuid) {
    GameApi.fetch(guildApiUrl('/invite'), {
        method: 'POST',
        body: JSON.stringify({ target_uuid: targetUuid }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) showMsg('Приглашение отправлено', 'success');
        else showMsg(data.error || 'Ошибка', 'error');
    });
};

window.openGuildBank = function() {
    if (window.WindowManager) WindowManager.open('bank');
    if (typeof window.loadBankData === 'function') window.loadBankData();
};

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
