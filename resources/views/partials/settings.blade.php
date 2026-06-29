<div class="settings-panel" style="padding:16px;max-width:360px">
    <section style="margin-bottom:24px">
        <h3 style="font-size:14px;font-weight:600;margin:0 0 12px;color:#d4a574">Размер слотов</h3>
        <p style="font-size:12px;color:#888;margin:0 0 12px">Единый размер ячеек во всех хранилищах (инвентарь, обмен, аукцион).</p>
        <div style="display:flex;align-items:center;gap:12px">
            <input type="range" id="settingsSlotSize" min="32" max="72" step="4" value="44"
                   style="flex:1;accent-color:#667eea">
            <span id="settingsSlotSizeValue" style="min-width:48px;text-align:right;font-weight:700;color:#fbbf24">44 px</span>
        </div>
        <div id="settingsSlotPreview" style="margin-top:12px;display:flex;gap:5px"></div>
    </section>

    <section style="margin-bottom:8px">
        <h3 style="font-size:14px;font-weight:600;margin:0 0 12px;color:#d4a574">Окна интерфейса</h3>
        <p style="font-size:12px;color:#888;margin:0 0 12px">Сбросить позиции всех окон к значениям по умолчанию.</p>
        <button type="button" id="settingsResetWindows" class="btn btn-danger" style="width:100%;padding:10px">
            🔄 Сбросить позиции окон
        </button>
    </section>
</div>

<script>
window.initSettings = function() {
    const slider = document.getElementById('settingsSlotSize');
    const valueEl = document.getElementById('settingsSlotSizeValue');
    const preview = document.getElementById('settingsSlotPreview');
    const resetBtn = document.getElementById('settingsResetWindows');

    if (!slider || slider.dataset.bound === '1') return;
    slider.dataset.bound = '1';

    const size = window.GameSettings ? GameSettings.getSlotSize() : 44;
    slider.value = String(size);
    valueEl.textContent = size + ' px';
    renderSlotPreview(preview, size);

    slider.addEventListener('input', function() {
        const v = parseInt(slider.value, 10);
        valueEl.textContent = v + ' px';
        renderSlotPreview(preview, v);
        if (window.GameSettings) {
            GameSettings.setSlotSize(v, false);
        }
    });

    slider.addEventListener('change', function() {
        const v = parseInt(slider.value, 10);
        if (window.GameSettings) {
            GameSettings.setSlotSize(v, true);
            showMsg('Размер слотов: ' + v + ' px', 'success');
        }
    });

    resetBtn.addEventListener('click', function() {
        if (window.WindowManager && typeof WindowManager.resetPositions === 'function') {
            WindowManager.resetPositions();
        }
    });
};

function renderSlotPreview(container, size) {
    if (!container) return;
    const cell = 'width:' + size + 'px;height:' + size + 'px;border:2px solid rgba(255,255,255,0.15);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:' + Math.round(size * 0.5) + 'px';
    container.innerHTML = '<div style="' + cell + '">📦</div><div style="' + cell + '">🪵</div><div style="' + cell + '">💰</div>';
}
</script>
