import './item-presenter.js';
import { GameItemPresenter } from './item-presenter.js';
import { GameItemTooltip } from './item-tooltip.js';
import { GameItemDetailModal } from './item-detail-modal.js';
import { normalizeDescriptor } from './item-descriptor.js';

window.GameItemPresenter = GameItemPresenter;
window.GameItemTooltip = GameItemTooltip;
window.GameItemDetailModal = GameItemDetailModal;
window.normalizeItemDescriptor = normalizeDescriptor;

// CSS для detail modal и ссылок
const style = document.createElement('style');
style.textContent = `
    .game-item-link {
        color: #a5b4fc;
        cursor: pointer;
        text-decoration: underline;
        text-decoration-style: dotted;
    }
    .game-item-link:hover { color: #c4b5fd; }
    .gid-modal {
        position: fixed; inset: 0; z-index: 10001;
        display: flex; align-items: center; justify-content: center;
    }
    .gid-modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.65); }
    .gid-modal-dialog {
        position: relative; background: #1e1e2e;
        border: 1px solid rgba(255,255,255,0.15); border-radius: 12px;
        width: 420px; max-width: 92vw; color: #eee;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    }
    .gid-modal-header {
        display: flex; justify-content: space-between; align-items: center;
        padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: 700;
    }
    .gid-modal-close {
        background: none; border: none; color: #aaa; cursor: pointer; font-size: 18px;
    }
    .gid-modal-body { padding: 16px; }
    .gid-modal-tooltip-body .tooltip-header { margin-bottom: 8px; }
    .gid-modal-footer {
        padding: 16px; border-top: 1px solid rgba(255,255,255,0.1);
        display: flex; justify-content: flex-end;
    }
`;
document.head.appendChild(style);

export { GameItemPresenter };
