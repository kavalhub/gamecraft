<div class="character-panel">
    <div class="character-equipment">
        <div id="equipmentSlots" class="equipment-slots"></div>
    </div>
    <div class="character-stats" id="characterStatsPanel">
        <div class="character-stats-title">Характеристики</div>
        <div id="characterStatsBody" class="character-stats-body">
            <div class="chat-placeholder">Загрузка...</div>
        </div>
    </div>
</div>

<script>
const EQUIPMENT_SLOT_LAYOUT = [
    { type: 'equipment_head', label: 'Голова' },
    { type: 'equipment_amulet', label: 'Шея' },
    { type: 'equipment_shoulders', label: 'Плечи' },
    { type: 'equipment_chest', label: 'Грудь' },
    { type: 'equipment_weapon', label: 'Оружие' },
    { type: 'equipment_offhand', label: 'Левая рука' },
    { type: 'equipment_legs', label: 'Ноги' },
    { type: 'equipment_ring', label: 'Кольцо 1', ringIndex: 0 },
    { type: 'equipment_ring', label: 'Кольцо 2', ringIndex: 1 },
];

function getOccupantFromSlot(slot) {
    if (!slot) return null;
    return slot.item || slot.resource || null;
}

window.renderCharacterPanel = function() {
    const container = document.getElementById('equipmentSlots');
    const statsBody = document.getElementById('characterStatsBody');
    if (!container) return;

    const storage = window.StorageManager && StorageManager.equipmentStorage;
    const slots = storage ? (storage.special_slots || storage.slots || []) : [];

    const ringCounters = {};
    let html = '';

    EQUIPMENT_SLOT_LAYOUT.forEach(function(def) {
        let slot = null;
        if (def.type === 'equipment_ring') {
            const idx = def.ringIndex || 0;
            const ringSlots = slots.filter(function(s) { return s.slot_type === 'equipment_ring'; });
            slot = ringSlots[idx] || null;
        } else {
            slot = slots.find(function(s) { return s.slot_type === def.type; }) || null;
        }

        const occ = getOccupantFromSlot(slot);
        const locked = occ && occ.locked;
        const slotClasses = 'storage-slot equipment-slot storage-slot--draggable' +
            (occ ? '' : ' storage-slot--empty') +
            (locked ? ' storage-slot--locked' : '');
        html += '<div class="equipment-slot-wrap">';
        html += '<div class="equipment-slot-label">' + def.label + '</div>';
        html += '<div class="' + slotClasses + '" ' +
            (slot ? 'data-slot-uuid="' + slot.uuid + '" data-slot-kind="regular" data-readonly="0"' : '') + '>';
        if (occ && window.GameItemPresenter) {
            html += GameItemPresenter.renderIcon(occ, 'storage-slot-item');
        }
        html += '</div></div>';
    });

    container.innerHTML = html;

    if (!container.dataset.dblBound) {
        container.dataset.dblBound = '1';
        container.addEventListener('dblclick', function(e) {
            const itemEl = e.target.closest('.game-item-interactive');
            const slotEl = e.target.closest('.storage-slot[data-slot-uuid]');
            if (!itemEl || !slotEl || !window.StorageQuickActions) return;
            const item = window.readInventoryItemFromElement
                ? window.readInventoryItemFromElement(itemEl)
                : null;
            if (!item || item.locked) return;
            StorageQuickActions.unequipFromSlot(slotEl.dataset.slotUuid);
        });
    }

    if (window.bindItemTooltips) {
        bindItemTooltips(container);
    }
    if (window.DragEngine) {
        DragEngine.registerGrid(container);
    }

    if (statsBody && StorageManager.characterStats) {
        renderCharacterStats(statsBody, StorageManager.characterStats);
    }
};

function renderCharacterStats(container, profile) {
    if (!container || !profile) return;
    const total = profile.total || {};
    const rows = [
        ['Уровень', profile.level || 1],
        ['Сила', total.strength || 0],
        ['Ловкость', total.agility || 0],
        ['Интеллект', total.intellect || 0],
        ['Выносливость', total.stamina || 0],
        ['Дух', total.spirit || 0],
        ['Урон', total.damage || 0],
        ['Защита', total.defense || 0],
        ['Здоровье', total.health || 0],
    ];

    container.innerHTML = rows.map(function(row) {
        return '<div class="character-stat-row"><span>' + row[0] + '</span><strong>' + row[1] + '</strong></div>';
    }).join('');
}

window.initCharacter = function() {
    if (!window.StorageManager || !GameState.characterUuid) return;
    StorageManager.load(GameState.characterUuid, 'inventory,equipment,stats').then(function() {
        renderCharacterPanel();
    });
};
</script>
