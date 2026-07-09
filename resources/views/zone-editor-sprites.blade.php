<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Палитра спрайтов — Редактор зоны</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; font-family: 'Segoe UI', sans-serif; background: #0f1419; color: #e2e8f0; }
        .picker-root { display: flex; height: 100vh; }
        .picker-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .picker-toolbar {
            padding: 12px 16px; background: #151b24; border-bottom: 1px solid #2a3441;
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
        }
        .picker-toolbar h1 { font-size: 16px; color: #d4a574; margin-right: auto; }
        .picker-toolbar a { color: #818cf8; text-decoration: none; font-size: 13px; }
        .picker-toolbar a:hover { text-decoration: underline; }
        .picker-toolbar select, .picker-toolbar input[type="search"] {
            padding: 8px 10px; border-radius: 6px; border: 1px solid #334155;
            background: #1e293b; color: #e2e8f0; font-size: 13px;
        }
        .picker-toolbar input[type="search"] { min-width: 200px; }
        .picker-grid-wrap { flex: 1; overflow-y: auto; padding: 16px; }
        .picker-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px;
            align-content: start;
        }
        .picker-item {
            border: 2px solid #334155; border-radius: 8px; padding: 8px; cursor: pointer;
            background: #1e293b; text-align: center; transition: border-color 0.12s, background 0.12s;
        }
        .picker-item:hover { border-color: #64748b; background: #273449; }
        .picker-item.selected { border-color: #818cf8; background: #312e81; box-shadow: 0 0 0 1px #818cf8; }
        .picker-item img {
            width: 100%; height: 120px; object-fit: contain; display: block; image-rendering: auto;
        }
        .picker-item span {
            display: block; margin-top: 6px; font-size: 11px; color: #94a3b8;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .picker-preview {
            width: 360px; flex-shrink: 0; background: #151b24; border-left: 1px solid #2a3441;
            display: flex; flex-direction: column; padding: 16px;
        }
        .picker-preview h2 { font-size: 13px; color: #94a3b8; margin-bottom: 12px; font-weight: 600; }
        .preview-stage {
            flex: 1; display: flex; align-items: center; justify-content: center;
            background: repeating-conic-gradient(#1a2230 0% 25%, #151b24 0% 50%) 50% / 24px 24px;
            border-radius: 8px; border: 1px solid #334155; min-height: 280px; padding: 16px;
        }
        .preview-stage img {
            max-width: 100%; max-height: 420px; object-fit: contain; display: block;
        }
        .preview-meta { margin-top: 14px; font-size: 12px; line-height: 1.5; }
        .preview-meta strong { color: #e2e8f0; word-break: break-all; }
        .preview-meta .path { color: #64748b; font-size: 11px; margin-top: 4px; word-break: break-all; }
        .preview-hint {
            margin-top: 12px; padding: 10px 12px; background: #1e293b; border-radius: 6px;
            font-size: 11px; color: #94a3b8; line-height: 1.45;
        }
        .empty-msg { padding: 24px; color: #94a3b8; font-size: 14px; }
        .status-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #22c55e; display: inline-block;
            margin-right: 6px;
        }
        .status-dot.off { background: #64748b; }
    </style>
</head>
<body>
<div class="picker-root">
    <div class="picker-main">
        <div class="picker-toolbar">
            <h1>🎨 Палитра спрайтов</h1>
            <span id="bridgeStatus" style="font-size:12px;color:#94a3b8"><span class="status-dot"></span>Связь с редактором</span>
            <select id="folderSelect" aria-label="Папка"></select>
            <input type="search" id="spriteSearch" placeholder="Поиск по имени…">
            <a href="{{ url('/zone-editor') }}" target="_blank">Редактор зоны ↗</a>
        </div>
        <div class="picker-grid-wrap">
            <div id="spriteGrid" class="picker-grid"></div>
        </div>
    </div>
    <aside class="picker-preview">
        <h2>Предпросмотр</h2>
        <div class="preview-stage">
            <img id="previewImg" src="" alt="" style="display:none">
            <span id="previewEmpty" style="color:#64748b;font-size:13px">Выберите спрайт</span>
        </div>
        <div class="preview-meta" id="previewMeta"></div>
        <div class="preview-hint">
            Клик по спрайту выбирает его в открытом редакторе зоны (как в боковой панели).
            Окно можно держать на втором мониторе — связь работает между вкладками и окнами этого же сайта.
        </div>
    </aside>
</div>
<script>window.GAME_BASE = @json(config('game.base_path'));</script>
<script src="{{ asset('js/game/game-paths.js') }}?v=20260709a"></script>
<script src="{{ asset('js/game/zone-editor-bridge.js') }}?v=20260704a"></script>
<script src="{{ asset('js/game/zone-editor-sprites.js') }}?v=20260704a"></script>
</body>
</html>
