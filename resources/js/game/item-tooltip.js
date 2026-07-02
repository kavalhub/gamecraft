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

function getRecipes() {
    return (window.GameState && window.GameState.recipes) || [];
}

function getTemplateDisplay(slug) {
    if (window.GameItemPresenter?.templateCache?.get(slug)) {
        const t = window.GameItemPresenter.templateCache.get(slug);
        return { icon: t.icon || '📦', name: t.name || slug };
    }
    if (window.GameItemPresenter?.descriptorFromSlug) {
        const d = window.GameItemPresenter.descriptorFromSlug(slug, 1);
        return { icon: d.icon || '📦', name: d.name || slug };
    }
    return { icon: '📦', name: slug };
}

function resolveBlueprintRecipeSlug(descriptor) {
    if (descriptor.recipe_slug) {
        return descriptor.recipe_slug;
    }
    if (descriptor.template_slug && window.GameItemPresenter?.templateCache) {
        const template = window.GameItemPresenter.templateCache.get(descriptor.template_slug);
        if (template?.recipe_slug) {
            return template.recipe_slug;
        }
    }
    return '';
}

function findRecipe(slug) {
    if (!slug) return null;
    return getRecipes().find((recipe) => recipe.slug === slug) || null;
}

function buildCraftRecipeHtml(recipe) {
    const formula = recipe?.craft_formula;
    if (!formula || typeof formula !== 'object' || Object.keys(formula).length === 0) {
        return '';
    }

    let html = '<div class="tooltip-recipe">';
    html += '<div class="tooltip-recipe-title">Рецепт крафта</div>';
    html += '<ul class="tooltip-recipe-list">';
    for (const [slug, qty] of Object.entries(formula)) {
        const material = getTemplateDisplay(slug);
        html += `<li><span class="tooltip-recipe-item">${material.icon} ${material.name}</span><span class="tooltip-recipe-qty">×${qty}</span></li>`;
    }
    html += '</ul>';

    if (recipe.result_template_slug) {
        const result = getTemplateDisplay(recipe.result_template_slug);
        const resultQty = recipe.result_quantity > 1 ? ` ×${recipe.result_quantity}` : '';
        html += `<div class="tooltip-recipe-result">Создаёт: ${result.icon} ${result.name}${resultQty}</div>`;
    }

    html += '</div>';
    return html;
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

    if (stage === 'blueprint') {
        const recipeSlug = resolveBlueprintRecipeSlug(d);
        const recipe = findRecipe(recipeSlug);
        if (recipe) {
            html += buildCraftRecipeHtml(recipe);
        }
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
