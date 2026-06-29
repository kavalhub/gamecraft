<div id="resourceQuantityModal" class="rq-modal" style="display:none;">
    <div class="rq-modal-backdrop" onclick="ResourceQuantityModal.close()"></div>
    <div class="rq-modal-dialog">
        <div class="rq-modal-header">
            <span id="rqModalIcon" class="rq-modal-icon"></span>
            <span id="rqModalTitle">Количество</span>
            <button type="button" class="rq-modal-close" onclick="ResourceQuantityModal.close()">✕</button>
        </div>
        <div class="rq-modal-body">
            <p id="rqModalSubtitle" class="rq-modal-subtitle"></p>
            <div class="rq-modal-controls">
                <input type="range" id="rqModalRange" min="1" max="1" value="1" class="rq-modal-range">
                <input type="number" id="rqModalInput" min="1" max="1" value="1" class="rq-modal-input">
            </div>
            <p id="rqModalHint" class="rq-modal-hint"></p>
        </div>
        <div class="rq-modal-footer">
            <button type="button" class="btn btn-danger" onclick="ResourceQuantityModal.close()">Отмена</button>
            <button type="button" class="btn btn-success" id="rqModalConfirm">Добавить</button>
        </div>
    </div>
</div>

<style>
    .rq-modal {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .rq-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
    }
    .rq-modal-dialog {
        position: relative;
        background: #1e1e2e;
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 12px;
        width: 360px;
        max-width: 90vw;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        color: #eee;
    }
    .rq-modal-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 16px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        font-weight: 600;
    }
    .rq-modal-icon { font-size: 24px; }
    .rq-modal-close {
        margin-left: auto;
        background: none;
        border: none;
        color: #aaa;
        cursor: pointer;
        font-size: 18px;
    }
    .rq-modal-body { padding: 16px; }
    .rq-modal-subtitle { color: #aaa; font-size: 13px; margin-bottom: 16px; }
    .rq-modal-controls {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .rq-modal-range { width: 100%; }
    .rq-modal-input {
        width: 100%;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.2);
        background: #2a2a3a;
        color: #fff;
        font-size: 16px;
        text-align: center;
    }
    .rq-modal-hint { color: #888; font-size: 12px; margin-top: 12px; }
    .rq-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
</style>

<script>
window.ResourceQuantityModal = {
    _onConfirm: null,
    _maxStack: null,
    _pricePerUnit: null,

    open({ name, icon, available, maxStack, onConfirm, pricePerUnit = null, confirmLabel = 'Добавить', subtitle = null, defaultToMax = true, confirmDisabled = false }) {
        this._onConfirm = onConfirm;
        this._maxStack = maxStack;
        this._pricePerUnit = pricePerUnit;
        this._confirmDisabled = confirmDisabled;

        const modal = document.getElementById('resourceQuantityModal');
        const range = document.getElementById('rqModalRange');
        const input = document.getElementById('rqModalInput');
        const confirmBtn = document.getElementById('rqModalConfirm');

        const maxQty = Math.max(1, available);
        const canBuy = available >= 1 && !confirmDisabled;
        const initial = canBuy ? (defaultToMax ? available : 1) : 1;

        document.getElementById('rqModalIcon').textContent = icon || '📦';
        document.getElementById('rqModalTitle').textContent = name || 'Ресурс';
        document.getElementById('rqModalSubtitle').textContent = subtitle || `Доступно: ${available}`;

        range.min = 1;
        range.max = maxQty;
        range.value = Math.min(initial, maxQty);
        range.disabled = !canBuy;
        input.min = 1;
        input.max = maxQty;
        input.value = Math.min(initial, maxQty);
        input.disabled = !canBuy;

        confirmBtn.textContent = confirmLabel;
        confirmBtn.disabled = !canBuy;

        this._updateHint(parseInt(input.value, 10));

        range.oninput = () => {
            if (!canBuy) return;
            input.value = range.value;
            this._updateHint(parseInt(range.value, 10));
        };
        input.oninput = () => {
            if (!canBuy) return;
            let v = parseInt(input.value, 10) || 1;
            v = Math.max(1, Math.min(available, v));
            input.value = v;
            range.value = v;
            this._updateHint(v);
        };

        document.getElementById('rqModalConfirm').onclick = () => {
            if (!canBuy) return;
            const qty = parseInt(input.value, 10) || 1;
            const callback = this._onConfirm;
            this.close();
            if (callback) {
                callback(qty);
            }
        };

        modal.style.display = 'flex';
    },

    _updateHint(quantity) {
        const hint = document.getElementById('rqModalHint');
        let text = '';
        if (this._pricePerUnit) {
            text += `Итого: ${this._pricePerUnit * quantity} 💰`;
        }
        if (!this._maxStack || this._maxStack < 1) {
            hint.textContent = text || 'Займёт 1 слот';
            return;
        }
        const slots = Math.ceil(quantity / this._maxStack);
        const slotsText = `Займёт ${slots} слот(ов) (макс. ${this._maxStack} в стаке)`;
        hint.textContent = text ? `${text}. ${slotsText}` : slotsText;
    },

    close() {
        document.getElementById('resourceQuantityModal').style.display = 'none';
        this._onConfirm = null;
        this._pricePerUnit = null;
    },
};
</script>
