import { normalizeDescriptor } from './item-descriptor.js';

const STAT_LABELS = {
    damage: 'Урон',
    attack: 'Атака',
    defense: 'Защита',
    health: 'Здоровье',
    durability: 'Прочность',
    level: 'Уровень',
    weight: 'Вес',
    value: 'Ценность',
    strength: 'Сила',
    attack_speed: 'Скорость атаки',
    crit_chance: 'Крит',
};

function formatStatValue(value) {
    if (value === null || value === undefined) return '';
    if (typeof value === 'object') {
        if ('min' in value && 'max' in value) {
            return `${value.min}–${value.max}`;
        }
        return JSON.stringify(value);
    }
    return String(value);
}

export function buildTooltipHtml(descriptor) {
    const d = normalizeDescriptor(descriptor);
    const stage = d.stage || '';
    const quantity = d.quantity || 1;

    let type = 'Предмет';
    if (stage === 'blueprint') type = 'Чертёж';
    else if (stage === 'item') type = 'Предмет';
    else if (quantity > 1 || stage === '') type = 'Ресурс';

    let html = `
        <div class="tooltip-header">
            <div class="tooltip-icon">${d.icon}</div>
            <div>
                <div class="tooltip-name">${d.name}</div>
                <div class="tooltip-type">${type}</div>
            </div>
        </div>
    `;

    if (d.description) {
        html += `<div class="tooltip-description">${d.description}</div>`;
    }

    const statEntries = Object.entries(d.stats || {}).filter(([, v]) => v != null && v !== '');
    if (statEntries.length > 0) {
        html += '<div class="tooltip-stats">';
        for (const [key, value] of statEntries) {
            const label = STAT_LABELS[key] || key;
            html += `
                <div class="tooltip-stat">
                    <span class="tooltip-stat-label">${label}:</span>
                    <span class="tooltip-stat-value">${formatStatValue(value)}</span>
                </div>
            `;
        }
        html += '</div>';
    }

    if (quantity > 1) {
        html += `<div class="tooltip-quantity">Количество: ${quantity}</div>`;
    }

    return html;
}

export const GameItemTooltip = {
    _el: null,

    init() {
        this._el = document.getElementById('itemTooltip');
    },

    show(e, descriptor) {
        if (!this._el) this.init();
        if (!this._el) return;

        this._el.innerHTML = buildTooltipHtml(descriptor);
        this._el.classList.add('visible');
        this.move(e);
    },

    hide() {
        if (!this._el) this.init();
        if (this._el) this._el.classList.remove('visible');
    },

    move(e) {
        if (!this._el || !this._el.classList.contains('visible')) return;

        const offsetX = 15;
        const offsetY = 15;
        let x = e.clientX + offsetX;
        let y = e.clientY + offsetY;

        const rect = this._el.getBoundingClientRect();
        if (x + rect.width > window.innerWidth) x = e.clientX - rect.width - offsetX;
        if (y + rect.height > window.innerHeight) y = e.clientY - rect.height - offsetY;

        this._el.style.left = `${x}px`;
        this._el.style.top = `${y}px`;
    },
};
