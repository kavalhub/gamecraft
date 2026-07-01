<div class="quests-panel">
    <div class="quests-tabs game-tabs">
        <button type="button" class="game-tab active" data-quests-tab="available" onclick="QuestLog.switchTab('available')">Доступные</button>
        <button type="button" class="game-tab" data-quests-tab="active" onclick="QuestLog.switchTab('active')">Активные</button>
        <button type="button" class="game-tab" data-quests-tab="finished" onclick="QuestLog.switchTab('finished')">Завершённые</button>
    </div>
    <div id="questsList" class="quests-list">
        <div class="chat-placeholder">Загрузка квестов...</div>
    </div>
</div>
