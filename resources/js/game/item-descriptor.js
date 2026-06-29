/**
 * Нормализация предмета/ресурса в единый дескриптор для UI.
 */
export function normalizeDescriptor(raw) {
    let stage = raw.stage;
    if (stage === undefined || stage === null) {
        if (raw.is_resource || raw.template_type === 'material') {
            stage = '';
        } else if (raw.template_type === 'blueprint') {
            stage = 'blueprint';
        } else {
            stage = 'item';
        }
    }

    const quantity = parseInt(raw.quantity, 10) || 1;

    return {
        uuid: raw.uuid || null,
        template_slug: raw.template_slug || '',
        name: raw.name || raw.template_name || raw.template_slug || 'Предмет',
        icon: raw.icon || raw.template_icon || (stage === 'blueprint' ? '📜' : '📦'),
        description: raw.description || raw.template_description || '',
        quantity,
        max_stack: raw.max_stack ?? null,
        stage,
        stats: raw.stats || {},
        recipe_slug: raw.recipe_slug || '',
    };
}

export function escapeAttr(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
}

export function descriptorDataAttrs(descriptor) {
    const d = normalizeDescriptor(descriptor);
    return {
        'data-item-uuid': d.uuid || '',
        'data-name': d.name,
        'data-stage': d.stage || '',
        'data-template-slug': d.template_slug,
        'data-quantity': String(d.quantity),
        'data-description': d.description,
        'data-stats': JSON.stringify(d.stats || {}),
        'data-max-stack': d.max_stack != null ? String(d.max_stack) : '',
        'data-icon': d.icon,
    };
}

export function attrsToString(attrs) {
    return Object.entries(attrs)
        .map(([key, val]) => `${key}="${escapeAttr(val)}"`)
        .join(' ');
}

export function readDescriptorFromElement(el) {
    let stats = {};
    try {
        stats = JSON.parse(el.dataset.stats || '{}');
    } catch (_) {
        stats = {};
    }

    return normalizeDescriptor({
        uuid: el.dataset.itemUuid || null,
        template_slug: el.dataset.templateSlug || '',
        name: el.dataset.name,
        icon: el.dataset.icon,
        description: el.dataset.description,
        quantity: el.dataset.quantity,
        max_stack: el.dataset.maxStack ? parseInt(el.dataset.maxStack, 10) : null,
        stage: el.dataset.stage,
        stats,
    });
}
