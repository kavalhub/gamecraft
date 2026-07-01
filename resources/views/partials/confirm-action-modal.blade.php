<div class="window window--confirm" id="window-confirm" data-window="confirm">
    <div class="window-header">
        <div class="window-title">
            <span class="icon" id="confirmWindowIcon">❓</span>
            <span id="confirmWindowTitle">Подтверждение</span>
        </div>
        <div class="window-controls">
            <div class="window-btn" onclick="ConfirmActionModal.close()">✕</div>
        </div>
    </div>
    <div class="window-body confirm-window-body">
        <p id="confirmWindowMessage" class="confirm-window-message"></p>
        <div class="confirm-window-actions">
            <button type="button" class="confirm-window-btn confirm-window-btn--yes" id="confirmWindowYes" title="Да">✓</button>
            <button type="button" class="confirm-window-btn confirm-window-btn--no" id="confirmWindowNo" title="Нет">✕</button>
        </div>
    </div>
</div>

<script>
window.ConfirmActionModal = {
    _onConfirm: null,
    _item: null,

    open({ title, message, messageHtml, icon, item, onConfirm }) {
        const titleEl = document.getElementById('confirmWindowTitle');
        const messageEl = document.getElementById('confirmWindowMessage');
        const iconEl = document.getElementById('confirmWindowIcon');
        if (!titleEl || !messageEl) return;

        titleEl.textContent = title || 'Подтверждение';
        if (messageHtml) {
            messageEl.innerHTML = messageHtml;
        } else {
            messageEl.textContent = message || '';
        }
        if (iconEl) iconEl.textContent = icon || '❓';

        this._onConfirm = onConfirm || null;
        this._item = item || null;

        if (window.WindowManager) {
            WindowManager.open('confirm');
        }

        if (item && window.bindItemTooltips) {
            bindItemTooltips(messageEl);
        }

        const yesBtn = document.getElementById('confirmWindowYes');
        const noBtn = document.getElementById('confirmWindowNo');
        if (yesBtn) yesBtn.onclick = () => this._handleConfirm();
        if (noBtn) noBtn.onclick = () => this.close();
    },

    close() {
        if (window.WindowManager) {
            WindowManager.close('confirm');
        }
        this._onConfirm = null;
        this._item = null;
    },

    async _handleConfirm() {
        const fn = this._onConfirm;
        this.close();
        if (typeof fn === 'function') {
            try {
                await fn();
            } catch (e) {
                if (typeof showMsg === 'function') showMsg(e.message || 'Ошибка', 'error');
            }
        }
    },
};
</script>
