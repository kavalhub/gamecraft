<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Редактор зоны — Крафт-Мир</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; font-family: 'Segoe UI', sans-serif; background: #0f1419; color: #e2e8f0; }
        .editor-root { display: flex; height: 100vh; }
        .editor-sidebar {
            width: 280px; flex-shrink: 0; background: #151b24;
            border-right: 1px solid #2a3441; display: flex; flex-direction: column;
            overflow: hidden;
        }
        .editor-sidebar h1 { font-size: 16px; padding: 14px 16px 8px; color: #d4a574; }
        .editor-section { padding: 10px 16px; border-bottom: 1px solid #2a3441; }
        .editor-section label { display: block; font-size: 12px; color: #94a3b8; margin-bottom: 6px; }
        .editor-section select, .editor-section button {
            width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #334155;
            background: #1e293b; color: #e2e8f0; font-size: 13px;
        }
        .editor-section button { cursor: pointer; margin-top: 6px; }
        .editor-section button.primary { background: #4f46e5; border-color: #6366f1; font-weight: 600; }
        .editor-section button.primary:hover { background: #4338ca; }
        .editor-section button.danger { background: #7f1d1d; border-color: #991b1b; }
        .tool-row { display: flex; gap: 6px; flex-wrap: wrap; }
        .tool-btn {
            flex: 1; min-width: 70px; padding: 8px 4px !important; font-size: 11px !important;
            text-align: center;
        }
        .tool-btn.active { background: #4f46e5; border-color: #818cf8; }
        .sprite-grid {
            flex: 1; overflow-y: auto; padding: 8px 12px 16px;
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; align-content: start;
        }
        .sprite-item {
            border: 2px solid #334155; border-radius: 6px; padding: 4px; cursor: pointer;
            background: #1e293b; text-align: center;
        }
        .sprite-item.selected { border-color: #818cf8; background: #312e81; }
        .sprite-item img { width: 100%; height: 48px; object-fit: contain; display: block; }
        .sprite-item span { font-size: 9px; color: #94a3b8; display: block; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .editor-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .editor-toolbar {
            padding: 8px 14px; background: #151b24; border-bottom: 1px solid #2a3441;
            display: flex; align-items: center; gap: 16px; font-size: 13px; flex-wrap: wrap;
        }
        .editor-toolbar a { color: #818cf8; text-decoration: none; }
        .editor-toolbar a:hover { text-decoration: underline; }
        .editor-canvas-wrap { flex: 1; position: relative; overflow: hidden; background: #0a0e12; }
        #zoneEditorCanvas { display: block; width: 100%; height: 100%; cursor: crosshair; }
        .editor-status {
            position: absolute; bottom: 12px; left: 12px; padding: 6px 12px;
            background: rgba(0,0,0,0.7); border-radius: 6px; font-size: 12px; pointer-events: none;
        }
        .editor-hint {
            position: absolute; top: 12px; left: 12px; padding: 6px 12px;
            background: rgba(0,0,0,0.55); border-radius: 6px; font-size: 11px; color: #94a3b8; max-width: 420px;
        }
        .checkbox-row { display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 12px; }
        .checkbox-row input { width: auto; }
        .msg { padding: 8px 16px; font-size: 12px; display: none; }
        .msg.show { display: block; }
        .msg.ok { background: #14532d; color: #bbf7d0; }
        .msg.err { background: #7f1d1d; color: #fecaca; }
        .bounds-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .bounds-grid input {
            width: 100%; padding: 6px 8px; border-radius: 6px; border: 1px solid #334155;
            background: #1e293b; color: #e2e8f0; font-size: 12px;
        }
        .bounds-grid label { font-size: 10px; margin-bottom: 2px; }
        .bounds-meta { font-size: 11px; color: #64748b; margin-top: 6px; }
        .editor-section input[type="search"] {
            width: 100%; padding: 8px 10px; border-radius: 6px; border: 1px solid #334155;
            background: #1e293b; color: #e2e8f0; font-size: 13px; margin-bottom: 6px;
        }
        .stamp-meta { font-size: 11px; color: #64748b; margin-top: 6px; line-height: 1.4; }
        .stamp-actions { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }
        .stamp-actions button { margin-top: 0 !important; }
    </style>
</head>
<body>
<div class="editor-root">
    <aside class="editor-sidebar">
        <h1>🗺 Редактор зоны</h1>
        <div id="editorMsg" class="msg"></div>
        <div class="editor-section">
            <label for="zoneSelect">Зона</label>
            <select id="zoneSelect"></select>
        </div>
        <div class="editor-section">
            <label>Размер зоны (границы)</label>
            <div class="bounds-grid">
                <div><label for="boundMinX">min X</label><input type="number" id="boundMinX" step="1"></div>
                <div><label for="boundMaxX">max X</label><input type="number" id="boundMaxX" step="1"></div>
                <div><label for="boundMinZ">min Z</label><input type="number" id="boundMinZ" step="1"></div>
                <div><label for="boundMaxZ">max Z</label><input type="number" id="boundMaxZ" step="1"></div>
            </div>
            <div class="bounds-meta" id="boundsMeta">—</div>
            <button type="button" id="applyBoundsBtn">Применить размер</button>
        </div>
        <div class="editor-section">
            <label>Инструмент</label>
            <div class="tool-row">
                <button type="button" class="tool-btn active" data-tool="ground">🟩 Фон</button>
                <button type="button" class="tool-btn" data-tool="overlay">🏠 Объект</button>
            </div>
            <div class="tool-row" style="margin-top:6px">
                <button type="button" class="tool-btn" data-tool="block">🚫 Блок</button>
                <button type="button" class="tool-btn" data-tool="erase">🧹 Стереть</button>
            </div>
            <div class="tool-row" style="margin-top:6px">
                <button type="button" class="tool-btn" data-tool="select">📦 Выделить</button>
                <button type="button" class="tool-btn" data-tool="stamp">🏘 Штамп</button>
            </div>
            <div class="bounds-meta" style="margin-top:8px">Стереть: сначала объект, потом фон</div>
            <div class="checkbox-row">
                <input type="checkbox" id="showBlocked" checked>
                <label for="showBlocked">Показать непроходимые</label>
            </div>
        </div>
        <div class="editor-section">
            <button type="button" class="primary" id="saveBtn">💾 Сохранить тайлы</button>
            <button type="button" id="playBtn">▶ Играть</button>
            <button type="button" id="openSpritePickerBtn" style="margin-top:6px">🎨 Палитра (большие превью)</button>
        </div>
        <div class="editor-section">
            <label for="stampSelect">Группы (штампы)</label>
            <select id="stampSelect"></select>
            <div class="stamp-meta" id="stampMeta">Выделите область и сохраните как штамп</div>
            <div class="stamp-actions">
                <button type="button" id="saveStampBtn">💾 Сохранить выделение</button>
                <button type="button" id="deleteStampBtn" class="danger">🗑 Удалить штамп</button>
            </div>
        </div>
        <div class="editor-section" style="padding-bottom:0;border-bottom:none;">
            <label for="folderSelect">Папка спрайтов</label>
            <select id="folderSelect"></select>
            <input type="search" id="spriteSearch" placeholder="Поиск по имени…">
        </div>
        <div id="spriteGrid" class="sprite-grid"></div>
    </aside>
    <main class="editor-main">
        <div class="editor-toolbar">
            <span id="zoneTitle">—</span>
            <span id="cellInfo">Клетка: —</span>
            <a href="{{ url('/play') }}">← В игру</a>
        </div>
        <div class="editor-canvas-wrap">
            <canvas id="zoneEditorCanvas"></canvas>
            <div class="editor-hint">Штамп: сохраните группу спрайтов и вставляйте целиком · после вставки можно править клетки · ПКМ — проходимость · Space+ЛКМ — панорама</div>
            <div class="editor-status" id="editorStatus">Загрузка…</div>
        </div>
    </main>
</div>
<script>
    window.ZONE_EDITOR_INITIAL_SLUG = @json($slug ?? null);
    window.GAME_BASE = @json(config('game.base_path'));
</script>
<script src="{{ asset('js/game/iso-tile-render.js') }}?v=20260704f"></script>
<script src="{{ asset('js/game/game-paths.js') }}?v=20260709a"></script>
<script src="{{ asset('js/game/zone-editor-bridge.js') }}?v=20260704a"></script>
<script src="{{ asset('js/game/zone-editor.js') }}?v=20260704n"></script>
</body>
</html>
