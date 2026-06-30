<div class="workbench-panel disassemble-panel">
    <div class="workbench-grid workbench-grid--disassemble">
        <div class="workbench-col workbench-col--center">
            <div class="workbench-section-title">Предмет</div>
            <div id="disassembleItemSlot" class="workbench-center-slot-wrap"></div>
        </div>

        <div class="workbench-col workbench-col--materials">
            <div class="workbench-section-title">Вернётся</div>
            <div id="disassembleReturns" class="workbench-materials-grid"></div>
        </div>

        <div class="workbench-col workbench-col--stats">
            <div class="workbench-section-title">Характеристики</div>
            <div id="disassembleStatsBody" class="workbench-stats-body">
                <div class="workbench-stats-empty">—</div>
            </div>
        </div>
    </div>

    <div id="disassembleActionMode" class="workbench-actions" style="display:none">
        <button type="button" id="btnDisassembleItem" class="workbench-btn workbench-btn--disassemble">Разобрать</button>
    </div>

    <div id="disassembleEmptyMode" class="workbench-empty-hint">
        Поместите предмет для разборки
    </div>
</div>
