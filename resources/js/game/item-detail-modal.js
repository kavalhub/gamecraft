import { buildTooltipHtml } from './item-tooltip.js';
import { normalizeDescriptor } from './item-descriptor.js';
import { GameItemTooltip } from './item-tooltip.js';

export const GameItemDetailModal = {
    _backdrop: null,
    _dialog: null,

    ensureDom() {
        if (this._dialog) return;

        const wrap = document.createElement('div');
        wrap.id = 'gameItemDetailModal';
        wrap.className = 'gid-modal';
        wrap.style.display = 'none';
        wrap.innerHTML = `
            <div class="gid-modal-backdrop"></div>
            <div class="gid-modal-dialog">
                <div class="gid-modal-header">
                    <span id="gidModalTitle">Предмет</span>
                    <button type="button" class="gid-modal-close" aria-label="Закрыть">✕</button>
                </div>
                <div id="gidModalBody" class="gid-modal-body"></div>
                <div class="gid-modal-footer">
                    <button type="button" class="btn btn-danger gid-modal-close-btn">Закрыть</button>
                </div>
            </div>
        `;
        document.body.appendChild(wrap);

        this._backdrop = wrap.querySelector('.gid-modal-backdrop');
        this._dialog = wrap;

        const close = () => this.close();
        this._backdrop.addEventListener('click', close);
        wrap.querySelector('.gid-modal-close').addEventListener('click', close);
        wrap.querySelector('.gid-modal-close-btn').addEventListener('click', close);
    },

    open(descriptor) {
        this.ensureDom();
        GameItemTooltip.hide();

        const d = normalizeDescriptor(descriptor);
        document.getElementById('gidModalTitle').textContent = d.name;
        const body = document.getElementById('gidModalBody');
        body.innerHTML = buildTooltipHtml(d);
        body.classList.add('gid-modal-tooltip-body');

        this._dialog.style.display = 'flex';
    },

    close() {
        if (this._dialog) this._dialog.style.display = 'none';
    },
};
