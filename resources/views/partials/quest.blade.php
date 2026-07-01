<div class="quest-panel" id="questPanel">
    <div id="questPanelHeader" class="quest-panel-header">
        <h3 id="questPanelTitle">Квест</h3>
        <p id="questPanelDescription" class="quest-panel-desc"></p>
    </div>
    <div id="questObjectives" class="quest-objectives"></div>
    <div class="quest-storage-section" id="questGrantSection">
        <div class="quest-section-title">Предметы для квеста</div>
        <div id="questGrantGrid" class="storage-grid quest-grant-grid"></div>
    </div>
    <div class="quest-storage-section" id="questTurnInSection">
        <div class="quest-section-title">Награда</div>
        <div id="questTurnInGrid" class="storage-grid quest-turnin-grid"></div>
    </div>
    <div class="quest-actions">
        <button type="button" id="btnQuestAccept" class="workbench-btn workbench-btn--craft" style="display:none">Принять</button>
        <button type="button" id="btnQuestTurnIn" class="workbench-btn workbench-btn--craft" style="display:none">Завершить</button>
    </div>
</div>

<script>
(function () {
    function getQuestSlots() {
        var qs = window.StorageManager && StorageManager.layout && StorageManager.layout.quest_storage;
        return qs || null;
    }

    window.getQuestGrantSlots = function () {
        var qs = getQuestSlots();
        return (qs && qs.grant_slots) ? qs.grant_slots : [];
    };

    window.getQuestTurnInSlots = function () {
        var qs = getQuestSlots();
        return (qs && qs.turnin_slots) ? qs.turnin_slots : [];
    };

    window.renderQuestPanel = function () {
        if (!window.QuestWindow || !QuestWindow.current) return;
        QuestWindow.render();
    };
})();
</script>
