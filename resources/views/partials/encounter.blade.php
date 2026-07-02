<div class="encounter-panel">
    <div class="encounter-layout">
        <div class="encounter-col encounter-col--list">
            <div class="encounter-section-title">Противники</div>
            <div id="encounterList" class="encounter-list"></div>
        </div>

        <div class="encounter-col encounter-col--combat">
            <div class="encounter-section-title">Бой</div>
            <div id="encounterCombatLog" class="encounter-combat-log">
                <div class="encounter-combat-empty">Выберите противника и нажмите «В бой»</div>
            </div>
            <div class="encounter-actions">
                <button type="button" id="btnEncounterFight" class="workbench-btn workbench-btn--craft">В бой</button>
                <button type="button" id="btnEncounterClaim" class="workbench-btn" disabled>Забрать добычу</button>
            </div>
            <div id="encounterStatus" class="encounter-status"></div>
        </div>

        <div class="encounter-col encounter-col--loot">
            <div class="encounter-section-title">Добыча</div>
            <div id="encounterLootGrid" class="encounter-loot-grid"></div>
        </div>
    </div>
</div>
