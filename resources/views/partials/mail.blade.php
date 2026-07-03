<div>
    <div id="mailTabs" class="game-tabs" style="margin-bottom:16px">
        <button type="button" class="game-tab" data-tab="compose">✉️ Новое письмо</button>
        <button type="button" class="game-tab" data-tab="inbox">📬 Входящие <span id="mailUnreadBadge" class="mail-badge" style="display:none"></span></button>
    </div>

    <div id="mail-compose" style="display:none">
        <div style="display:flex;flex-direction:column;gap:12px">
            <div>
                <label style="display:block;font-size:12px;color:#aaa;margin-bottom:6px">Получатель (ник персонажа):</label>
                <div style="position:relative">
                    <input type="text" id="mailRecipientName" maxlength="64" placeholder="Имя персонажа" autocomplete="off"
                        style="width:100%;padding:10px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:13px">
                    <div id="mailRecipientSuggest" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:4px;z-index:20;background:rgba(20,20,30,0.98);border:1px solid rgba(255,255,255,0.15);border-radius:8px;max-height:180px;overflow-y:auto"></div>
                </div>
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#aaa;margin-bottom:6px">Тема:</label>
                <input type="text" id="mailSubject" maxlength="120" style="width:100%;padding:10px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:13px">
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#aaa;margin-bottom:6px">Текст:</label>
                <textarea id="mailBody" rows="4" maxlength="2000" style="width:100%;padding:10px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-size:13px;resize:vertical"></textarea>
            </div>
            <div>
                <label style="display:block;font-size:12px;color:#aaa;margin-bottom:6px">Вложения (до 6, перетащите из инвентаря):</label>
                <div id="mailComposeSlots" style="min-height:80px"></div>
            </div>
            <button id="btnSendMail" style="width:100%;padding:12px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer">Отправить</button>
        </div>
    </div>

    <div id="mail-inbox" style="display:none">
        <div id="mailInboxList" style="display:flex;flex-direction:column;gap:8px;max-height:420px;overflow-y:auto"></div>

        <div id="mail-read" style="display:none">
            <div style="margin-bottom:12px">
                <button type="button" id="btnMailBack" style="padding:6px 12px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;cursor:pointer">← К списку</button>
            </div>
            <div id="mailReadHeader" style="margin-bottom:12px"></div>
            <div id="mailReadBody" style="font-size:13px;color:#ccc;margin-bottom:16px;white-space:pre-wrap"></div>
            <div id="mailParcelSlots" style="min-height:72px;margin-bottom:12px"></div>
            <div style="display:flex;gap:8px">
                <button id="btnClaimAllMail" style="flex:1;padding:10px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer">Забрать всё</button>
                <button id="btnDeleteMail" style="padding:10px 16px;background:rgba(239,68,68,0.2);color:#fca5a5;border:1px solid rgba(239,68,68,0.4);border-radius:8px;cursor:pointer">Удалить</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.mailState = {
        tab: 'compose',
        messages: [],
        unreadCount: 0,
        selectedMessage: null,
        composeRecipientUuid: null,
    };

    window.initMail = function () {
        if (!window._mailInited) {
            window._mailInited = true;
            document.querySelectorAll('#mailTabs .game-tab').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    window.switchMailTab(e.currentTarget.dataset.tab);
                });
            });
            document.getElementById('btnSendMail').addEventListener('click', window.sendMail);
            document.getElementById('btnMailBack').addEventListener('click', window.closeMailRead);
            document.getElementById('btnClaimAllMail').addEventListener('click', window.claimAllMail);
            document.getElementById('btnDeleteMail').addEventListener('click', window.deleteMail);
            window.initMailRecipientAutocomplete();
        }

        var finishInit = function () {
            var tab = window.mailState.pendingTab;
            window.mailState.pendingTab = null;
            if (!tab) {
                tab = window.mailState.unreadCount > 0 ? 'inbox' : 'compose';
            }
            window.switchMailTab(tab);
        };

        if (typeof window.refreshMailUnreadStatus === 'function') {
            window.refreshMailUnreadStatus().then(finishInit).catch(function () { finishInit(); });
        } else {
            finishInit();
        }
        if (typeof window.hookMailRealtimeEvents === 'function') {
            window.hookMailRealtimeEvents();
        }
    };

    window.switchMailTab = function (tab) {
        if (tab === 'read') tab = 'inbox';
        window.mailState.tab = tab;
        document.querySelectorAll('#mailTabs .game-tab').forEach(function (t) {
            t.classList.toggle('active', t.dataset.tab === tab);
        });
        document.getElementById('mail-compose').style.display = tab === 'compose' ? 'block' : 'none';
        document.getElementById('mail-inbox').style.display = tab === 'inbox' ? 'block' : 'none';
        if (tab === 'inbox') {
            window.closeMailRead();
            window.loadMailInbox();
        }
        if (tab === 'compose') window.loadMailCompose();
    };

    window.startMailCompose = function (recipientUuid, recipientName) {
        window.mailState.composeRecipientUuid = recipientUuid || null;
        var nameInput = document.getElementById('mailRecipientName');
        if (nameInput) nameInput.value = recipientName || '';
        window.hideMailRecipientSuggest();
        window.mailState.pendingTab = 'compose';
        if (window.WindowManager) {
            WindowManager.open('mail');
        } else {
            window.switchMailTab('compose');
        }
    };

    window.hideMailRecipientSuggest = function () {
        var suggest = document.getElementById('mailRecipientSuggest');
        if (suggest) suggest.style.display = 'none';
    };

    window.initMailRecipientAutocomplete = function () {
        if (window._mailRecipientAcInited) return;
        window._mailRecipientAcInited = true;

        var input = document.getElementById('mailRecipientName');
        var suggest = document.getElementById('mailRecipientSuggest');
        if (!input || !suggest) return;

        var debounceTimer = null;

        function renderSuggestions(characters) {
            if (!characters.length) {
                window.hideMailRecipientSuggest();
                return;
            }
            suggest.innerHTML = characters.map(function (c) {
                return '<button type="button" class="mail-recipient-option" data-uuid="' + c.uuid + '" data-name="' + c.name.replace(/"/g, '&quot;') + '" style="display:block;width:100%;text-align:left;padding:10px 12px;border:none;background:transparent;color:#fff;font-size:13px;cursor:pointer">' + c.name + '</button>';
            }).join('');
            suggest.style.display = 'block';
            suggest.querySelectorAll('.mail-recipient-option').forEach(function (btn) {
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    input.value = btn.dataset.name;
                    window.mailState.composeRecipientUuid = btn.dataset.uuid;
                    window.hideMailRecipientSuggest();
                });
            });
        }

        input.addEventListener('input', function () {
            window.mailState.composeRecipientUuid = null;
            var q = input.value.trim();
            clearTimeout(debounceTimer);
            if (q.length < 1) {
                window.hideMailRecipientSuggest();
                return;
            }
            debounceTimer = setTimeout(function () {
                GameApi.fetch('/api/mail/' + GameState.characterUuid + '/recipients?q=' + encodeURIComponent(q))
                    .then(function (res) { return res.json(); })
                    .then(function (data) { renderSuggestions(data.characters || []); })
                    .catch(function () { window.hideMailRecipientSuggest(); });
            }, 250);
        });

        input.addEventListener('blur', function () {
            setTimeout(window.hideMailRecipientSuggest, 150);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') window.hideMailRecipientSuggest();
        });
    };

    window.closeMailRead = function () {
        var list = document.getElementById('mailInboxList');
        var read = document.getElementById('mail-read');
        if (list) list.style.display = 'flex';
        if (read) read.style.display = 'none';
        window.mailState.selectedMessage = null;
        if (window.mailState.tab === 'inbox') {
            window.renderMailInbox();
        }
    };

    window.syncMailUnreadUi = function (count) {
        count = count || 0;
        window.mailState.unreadCount = count;

        var badge = document.getElementById('mailUnreadBadge');
        if (badge) {
            if (count > 0) {
                badge.style.display = 'inline';
                badge.textContent = count > 9 ? '9+' : String(count);
            } else {
                badge.style.display = 'none';
            }
        }

        document.querySelectorAll('.play-panel-chip[data-action="mail"]').forEach(function (chip) {
            chip.classList.toggle('play-panel-chip--mail-unread', count > 0);
        });
    };

    window.hookMailRealtimeEvents = function () {
        if (window._mailEventHooked || !window.EventPoller) return;
        window._mailEventHooked = true;
        EventPoller.on(function (events) {
            var incoming = events.filter(function (e) { return e.type === 'mail.received'; });
            if (!incoming.length) return;
            if (typeof window.refreshMailUnreadStatus === 'function') {
                window.refreshMailUnreadStatus();
            }
        });
    };

    window.updateMailBadge = function (count) {
        window.syncMailUnreadUi(count);
    };

    window.refreshMailUnreadStatus = async function () {
        try {
            var res = await GameApi.fetch('/api/mail/' + GameState.characterUuid + '/inbox');
            var data = await res.json();
            window.mailState.messages = data.messages || [];
            window.syncMailUnreadUi(data.unread_count || 0);
            if (window.mailState.tab === 'inbox' && WindowManager && WindowManager.isOpen('mail')) {
                window.renderMailInbox();
            }
            return data;
        } catch (e) {
            console.error('refreshMailUnreadStatus:', e);
            return null;
        }
    };

    window.loadMailInbox = async function () {
        try {
            var data = await window.refreshMailUnreadStatus();
            if (!data) return;
        } catch (e) {
            if (typeof showMsg === 'function') showMsg('Ошибка почты: ' + e.message, 'error');
        }
    };

    window.renderMailInbox = function () {
        var list = document.getElementById('mailInboxList');
        if (!list) return;
        if (!window.mailState.messages.length) {
            list.innerHTML = '<div style="color:#888;text-align:center;padding:24px">Нет писем</div>';
            return;
        }
        list.innerHTML = window.mailState.messages.map(function (m) {
            var unread = m.status === 'unread';
            return '<div class="mail-inbox-item' + (unread ? ' unread' : '') + '" data-uuid="' + m.uuid + '" style="padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);background:rgba(0,0,0,0.25);cursor:pointer">' +
                '<div style="display:flex;justify-content:space-between;gap:8px"><strong style="font-size:13px">' + (m.subject || 'Без темы') + '</strong>' +
                (m.has_attachments ? '<span title="Есть вложения">📎</span>' : '') + '</div>' +
                '<div style="font-size:11px;color:#888;margin-top:4px">От: ' + (m.sender_name || '?') + '</div></div>';
        }).join('');
        list.querySelectorAll('.mail-inbox-item').forEach(function (el) {
            el.addEventListener('click', function () { window.openMailMessage(el.dataset.uuid); });
        });
    };

    window.openMailMessage = async function (messageUuid) {
        try {
            var res = await GameApi.fetch('/api/mail/' + GameState.characterUuid + '/' + messageUuid);
            var data = await res.json();
            window.mailState.selectedMessage = data.message;
            if (data.layout && window.StorageManager) StorageManager.applyLayout(data.layout);

            var readRes = await GameApi.fetch('/api/mail/' + GameState.characterUuid + '/' + messageUuid + '/read', { method: 'POST' });
            var readData = await readRes.json();
            if (readData.error) throw new Error(readData.error);
            if (readData.unread_count != null) {
                window.syncMailUnreadUi(readData.unread_count);
            }
            if (window.mailState.messages) {
                window.mailState.messages.forEach(function (m) {
                    if (m.uuid === messageUuid) m.status = 'read';
                });
            }
            if (window.mailState.selectedMessage && window.mailState.selectedMessage.uuid === messageUuid) {
                window.mailState.selectedMessage.status = 'read';
            }

            document.getElementById('mailInboxList').style.display = 'none';
            document.getElementById('mail-read').style.display = 'block';

            var m = data.message || {};
            document.getElementById('mailReadHeader').innerHTML = '<div style="font-size:15px;font-weight:700">' + (m.subject || '') + '</div><div style="font-size:11px;color:#888;margin-top:4px">От: ' + (m.sender_name || '') + '</div>';
            document.getElementById('mailReadBody').textContent = m.body || '';
            var parcelEl = document.getElementById('mailParcelSlots');
            if (m.has_attachments) {
                parcelEl.style.display = 'block';
                window.renderMailParcel(messageUuid, data.layout);
            } else {
                parcelEl.style.display = 'none';
                parcelEl.innerHTML = '';
            }
            document.getElementById('btnClaimAllMail').style.display = m.has_attachments ? 'block' : 'none';
        } catch (e) {
            if (typeof showMsg === 'function') showMsg(e.message, 'error');
        }
    };

    window.renderMailParcel = function (messageUuid, layout) {
        var container = document.getElementById('mailParcelSlots');
        if (!container || !window.StorageGrid) return;
        var storage = (layout && layout.post_inbox)
            || (window.StorageManager && StorageManager.postInboxStorage)
            || (layout && layout.storages || []).find(function (s) {
                return s.storage_type === 'post_inbox' && (!messageUuid || s.message_uuid === messageUuid);
            });
        if (!storage || !storage.slots || !storage.slots.length) {
            container.innerHTML = '<div style="color:#888;font-size:12px">Вложения уже получены</div>';
            return;
        }
        StorageGrid.mount(container, storage, {
            cols: storage.cols || 6,
            draggable: true,
            gridId: 'mail-inbox-grid',
            compact: true,
        });
        if (window.DragEngine) DragEngine.registerGrid(container);
        window.bindMailGridDblclick(container, 'claim');
    };

    window.renderMailOutbox = function (layout) {
        var container = document.getElementById('mailComposeSlots');
        if (!container || !window.StorageGrid) return;
        var storage = (layout && layout.post_outbox)
            || (window.StorageManager && StorageManager.postOutboxStorage)
            || (layout && layout.storages || []).find(function (s) {
                return s.storage_type === 'post_outbox';
            });
        if (!storage) {
            container.innerHTML = '<div style="color:#888;font-size:12px">Не удалось загрузить слоты</div>';
            return;
        }
        StorageGrid.mount(container, storage, {
            cols: storage.cols || 6,
            draggable: true,
            gridId: 'mail-outbox-grid',
            compact: true,
        });
        if (window.DragEngine) DragEngine.registerGrid(container);
        window.bindMailGridDblclick(container, 'return');
    };

    window.bindMailGridDblclick = function (container, mode) {
        if (!container || container.dataset.mailDblBound) return;
        container.dataset.mailDblBound = '1';
        container.addEventListener('dblclick', function (e) {
            var itemEl = e.target.closest('.game-item-interactive');
            var slotEl = e.target.closest('.storage-slot[data-slot-uuid]');
            if (!itemEl || !slotEl || !window.StorageQuickActions) return;
            if (window.GameItemTooltip) GameItemTooltip.hide();
            if (window.GameItemPreview) GameItemPreview.close();
            var slotUuid = slotEl.dataset.slotUuid;
            if (mode === 'claim') {
                StorageManager.quickMove(slotUuid, 'mail_claim', { silent: true }).then(function () {
                    if (typeof window.refreshAfterStorageChange === 'function') {
                        window.refreshAfterStorageChange();
                    }
                }).catch(function (err) {
                    if (typeof showMsg === 'function') showMsg(err.message, 'error');
                });
            } else {
                StorageQuickActions.returnToInventory(slotUuid);
            }
        });
    };

    window.refreshMailGrids = async function (layoutOrData) {
        if (!window.WindowManager || !WindowManager.isOpen('mail')) return;

        var layout = layoutOrData;
        if (!layout && window.StorageManager) layout = StorageManager.layout;

        if (window.mailState.tab === 'compose') {
            if (!layout || !layout.post_outbox) {
                try {
                    var composeRes = await GameApi.fetch('/api/mail/' + GameState.characterUuid + '/compose-layout');
                    layout = await composeRes.json();
                    layout = layout.layout || layout;
                    if (layout && window.StorageManager) StorageManager.applyLayout(layout);
                } catch (e) { /* ignore */ }
            }
            window.renderMailOutbox(layout);
            return;
        }

        var readEl = document.getElementById('mail-read');
        var m = window.mailState.selectedMessage;
        if (readEl && readEl.style.display !== 'none' && m && m.uuid) {
            try {
                var res = await GameApi.fetch('/api/mail/' + GameState.characterUuid + '/' + m.uuid);
                var data = await res.json();
                if (data.layout && window.StorageManager) {
                    StorageManager.applyLayout(data.layout);
                }
                if (data.message) {
                    window.mailState.selectedMessage = data.message;
                }
                window.renderMailParcel(m.uuid, data.layout);
            } catch (e) {
                console.error('refreshMailGrids:', e);
            }
        }
    };

    window.loadMailCompose = async function () {
        try {
            var res = await GameApi.fetch('/api/mail/' + GameState.characterUuid + '/compose-layout');
            var data = await res.json();
            if (data.layout && window.StorageManager) StorageManager.applyLayout(data.layout);
            window.renderMailOutbox(data.layout);
        } catch (e) {
            if (typeof showMsg === 'function') showMsg(e.message, 'error');
        }
    };

    window.sendMail = async function () {
        var recipientName = document.getElementById('mailRecipientName').value.trim();
        var subject = document.getElementById('mailSubject').value.trim();
        var body = document.getElementById('mailBody').value.trim();
        if (!recipientName && !window.mailState.composeRecipientUuid) {
            if (typeof showMsg === 'function') showMsg('Укажите ник получателя', 'error');
            return;
        }
        if (!subject) {
            if (typeof showMsg === 'function') showMsg('Укажите тему письма', 'error');
            return;
        }
        var payload = { subject: subject, body: body };
        if (window.mailState.composeRecipientUuid) {
            payload.recipient_uuid = window.mailState.composeRecipientUuid;
        }
        if (recipientName) {
            payload.recipient_name = recipientName;
        }
        try {
            var res = await GameApi.fetch('/api/mail/' + GameState.characterUuid + '/send', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            var data = await res.json();
            if (data.error) throw new Error(data.error);
            if (typeof showMsg === 'function') showMsg('Письмо отправлено', 'success');
            document.getElementById('mailSubject').value = '';
            document.getElementById('mailBody').value = '';
            document.getElementById('mailRecipientName').value = '';
            window.mailState.composeRecipientUuid = null;
            if (data.layout) window.renderMailOutbox(data.layout);
            if (typeof refreshAfterStorageChange === 'function') refreshAfterStorageChange();
            window.switchMailTab('compose');
        } catch (e) {
            if (typeof showMsg === 'function') showMsg(e.message, 'error');
        }
    };

    window.claimAllMail = async function () {
        var m = window.mailState.selectedMessage;
        if (!m) return;
        try {
            var res = await GameApi.fetch('/api/mail/' + GameState.characterUuid + '/' + m.uuid + '/claim-all', { method: 'POST' });
            var data = await res.json();
            if (data.error) throw new Error(data.error);
            if (typeof showMsg === 'function') showMsg('Вложения получены', 'success');
            if (typeof refreshAfterStorageChange === 'function') refreshAfterStorageChange();
            window.openMailMessage(m.uuid);
        } catch (e) {
            if (typeof showMsg === 'function') showMsg(e.message, 'error');
        }
    };

    window.deleteMail = async function () {
        var m = window.mailState.selectedMessage;
        if (!m) return;
        try {
            var res = await GameApi.fetch('/api/mail/' + GameState.characterUuid + '/' + m.uuid, { method: 'DELETE' });
            var data = await res.json();
            if (data.error) throw new Error(data.error);
            window.updateMailBadge(data.unread_count || 0);
            window.closeMailRead();
            window.loadMailInbox();
        } catch (e) {
            if (typeof showMsg === 'function') showMsg(e.message, 'error');
        }
    };
</script>
