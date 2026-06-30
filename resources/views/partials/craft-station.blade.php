<div class="workbench-panel craft-panel">
    <div class="workbench-grid">
        <div class="workbench-col workbench-col--materials">
            <div class="workbench-section-title">Материалы</div>
            <div id="craftMaterials" class="workbench-materials-grid"></div>
        </div>

        <div class="workbench-col workbench-col--center">
            <div class="workbench-section-title">Чертёж</div>
            <div id="craftBlueprintSlot" class="workbench-center-slot-wrap"></div>
            <div id="craftResultSlot" class="workbench-result-slot-wrap"></div>
        </div>

        <div class="workbench-col workbench-col--stats">
            <div class="workbench-section-title">Характеристики</div>
            <div id="craftStatsBody" class="workbench-stats-body">
                <div class="workbench-stats-empty">—</div>
            </div>
        </div>
    </div>

    <div id="craftItemMode" class="workbench-actions" style="display:none">
        <div class="workbench-craft-row">
            <div class="workbench-craft-name">
                <label class="workbench-label" for="craftCustomName">Название (можно изменить)</label>
                <input type="text" id="craftCustomName" placeholder="" class="workbench-input">
            </div>
            <button type="button" id="btnCraftItem" class="workbench-btn-icon workbench-btn-icon--craft" title="Создать" aria-label="Создать">🔨</button>
        </div>
    </div>

    <div id="craftResourceMode" class="workbench-actions" style="display:none">
        <button type="button" id="btnCraftResource" class="workbench-btn workbench-btn--craft">Преобразовать</button>
    </div>

    <div id="craftEmptyMode" class="workbench-empty-hint">
        Поместите чертёж или материалы для создания
    </div>
</div>
