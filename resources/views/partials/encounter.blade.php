<div class="encounter-panel">
    <div class="encounter-layout">
        <div class="encounter-col encounter-col--list">
            <div class="encounter-section-title">Противники</div>
            <div id="encounterList" class="encounter-list"></div>
        </div>

        <div class="encounter-col encounter-col--combat">
            <div class="encounter-section-title">Бой</div>
            <div id="encounterCombatants" class="encounter-combatants encounter-combatants--idle">
                <div class="encounter-combatant encounter-combatant--enemy">
                    <div id="encounterEnemyIcon" class="encounter-combatant-icon">👹</div>
                    <div id="encounterEnemyName" class="encounter-combatant-name">Противник</div>
                    <div class="encounter-hp-bar">
                        <div id="encounterEnemyHpFill" class="encounter-hp-bar-fill encounter-hp-bar-fill--enemy" style="width:100%"></div>
                    </div>
                    <div id="encounterEnemyHpText" class="encounter-hp-text">— / —</div>
                </div>
                <div class="encounter-combatant encounter-combatant--player">
                    <div id="encounterPlayerIcon" class="encounter-combatant-icon">🛡️</div>
                    <div id="encounterPlayerName" class="encounter-combatant-name">Вы</div>
                    <div class="encounter-hp-bar">
                        <div id="encounterPlayerHpFill" class="encounter-hp-bar-fill encounter-hp-bar-fill--player" style="width:100%"></div>
                    </div>
                    <div id="encounterPlayerHpText" class="encounter-hp-text">— / —</div>
                </div>
            </div>
            <div id="encounterCombatLog" class="encounter-combat-log">
                <div class="encounter-combat-empty">Выберите противника и нажмите «В бой»</div>
            </div>
            <div class="encounter-actions encounter-actions--fight">
                <button type="button" id="btnEncounterFight" class="workbench-btn workbench-btn--craft">В бой</button>
            </div>
            <div id="encounterStatus" class="encounter-status"></div>
        </div>

        <div class="encounter-col encounter-col--loot">
            <div class="encounter-section-title">Добыча</div>
            <div id="encounterLootGrid" class="encounter-loot-grid"></div>
            <div class="encounter-actions encounter-actions--loot">
                <button type="button" id="btnEncounterClaim" class="workbench-btn" disabled>Забрать</button>
                <button type="button" id="btnEncounterRefuse" class="workbench-btn" disabled>Отказаться</button>
            </div>
        </div>
    </div>
</div>
