import {
    normalizeDescriptor,
    descriptorDataAttrs,
    attrsToString,
    readDescriptorFromElement,
} from './item-descriptor.js';
import { GameItemTooltip } from './item-tooltip.js';
import { GameItemDetailModal } from './item-detail-modal.js';

const templateCache = new Map();

export const GameItemPresenter = {
    templateCache,

    async loadTemplateCache() {
        if (templateCache.size > 0) return;
        try {
            const res = await window.GameApi.fetch('/api/templates');
            const data = await res.json();
            (data.templates || []).forEach((t) => templateCache.set(t.slug, t));
        } catch (e) {
            console.warn('Template cache load failed', e);
        }
    },

    descriptorFromSlug(templateSlug, quantity = 1) {
        const t = templateCache.get(templateSlug);
        if (!t) {
            return normalizeDescriptor({
                template_slug: templateSlug,
                name: templateSlug,
                quantity,
            });
        }
        return normalizeDescriptor({
            template_slug: t.slug,
            name: t.name,
            icon: t.icon,
            description: t.description,
            max_stack: t.max_stack,
            stage: t.type === 'blueprint' ? 'blueprint' : (t.type === 'material' ? '' : 'item'),
            quantity,
        });
    },

    renderIcon(descriptor, extraClass = '') {
        const d = normalizeDescriptor(descriptor);
        const attrs = attrsToString(descriptorDataAttrs(d));
        const qty = d.quantity > 1
            ? `<div class="item-qty">x${d.quantity}</div>`
            : '';

        return `
            <div class="item game-item-interactive ${extraClass}"
                 ${attrs}>
                <div class="item-icon">${d.icon}</div>
                ${qty}
            </div>
        `;
    },

    renderLink(descriptor, extraClass = '') {
        const d = normalizeDescriptor(descriptor);
        const attrs = attrsToString(descriptorDataAttrs(d));
        const qtySuffix = d.quantity > 1 ? ` ×${d.quantity}` : '';

        return `<span class="game-item-link game-item-interactive ${extraClass}" ${attrs}>${d.name}${qtySuffix}</span>`;
    },

    applyItemInteractions(container) {
        if (!container) return;

        container.querySelectorAll('.game-item-interactive').forEach((el) => {
            if (el.dataset.itemBound === '1') return;
            el.dataset.itemBound = '1';

            el.addEventListener('mouseenter', (e) => {
                GameItemTooltip.show(e, readDescriptorFromElement(el));
            });
            el.addEventListener('mouseleave', () => GameItemTooltip.hide());
            el.addEventListener('mousemove', (e) => GameItemTooltip.move(e));

            el.addEventListener('click', (e) => {
                if (e.detail !== 1) return;
                clearTimeout(el._itemClickTimer);
                el._itemClickTimer = setTimeout(() => {
                    GameItemDetailModal.open(readDescriptorFromElement(el));
                }, 250);
            });

            el.addEventListener('dblclick', () => {
                clearTimeout(el._itemClickTimer);
                GameItemTooltip.hide();
            });
        });
    },
};

// Обратная совместимость с существующим кодом
window.bindItemTooltips = (container) => GameItemPresenter.applyItemInteractions(container);
window.showItemTooltip = null;
window.hideItemTooltip = () => GameItemTooltip.hide();
window.moveItemTooltip = (e) => GameItemTooltip.move(e);
