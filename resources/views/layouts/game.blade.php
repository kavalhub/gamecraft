<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Крафт-Мир')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh; color: #eee; overflow: hidden;
        }

        :root {
            --slot-size: 44px;
        }

        .game-canvas {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 60px;
            overflow: hidden;
        }

        .game-background {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(102,126,234,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(168,85,247,0.1) 0%, transparent 50%),
                linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }

        .window {
            position: absolute;
            background: rgba(20, 20, 35, 0.98);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            display: none;
            flex-direction: column;
            min-width: 300px;
            min-height: 200px;
            overflow: hidden;
        }

        .window.active {
            display: flex;
            animation: windowAppear 0.2s ease-out;
        }

        @keyframes windowAppear {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .window.focused {
            border-color: rgba(102,126,234,0.5);
            box-shadow: 0 15px 50px rgba(0,0,0,0.7), 0 0 0 1px rgba(102,126,234,0.3);
        }

        .window-header {
            padding: 6px 10px;
            min-height: 32px;
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: move;
            user-select: none;
        }

        .window-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #d4a574;
        }

        .window-title .icon {
            font-size: 15px;
        }

        .window-controls {
            display: flex;
            gap: 4px;
        }

        .window-btn {
            width: 24px; height: 24px;
            border-radius: 5px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: #aaa;
            font-size: 14px;
        }

        .window-btn:hover {
            background: rgba(239,68,68,0.3);
            border-color: rgba(239,68,68,0.5);
            color: #ef4444;
        }

        .window-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

        .window.dragging {
            opacity: 0.8;
        }

        .window.dragging .window-header {
            cursor: grabbing;
        }

        #window-journal {
            width: 340px;
            height: calc(100% - 40px);
        }

        #window-journal .window-body {
            padding: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        #window-players {
            width: 420px;
            height: 500px;
        }

        #window-players .window-body {
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        #playersContent {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }

        #window-inventory {
            width: 220px;
            height: auto;
            max-height: calc(100% - 40px);
        }

        .storage-grid { display: grid; width: max-content; max-width: 100%; }
        .storage-slot {
            width: var(--slot-size, 44px);
            height: var(--slot-size, 44px);
            min-height: unset;
            aspect-ratio: unset;
            background: rgba(0,0,0,0.25);
            border: 2px solid rgba(255,255,255,0.12);
            border-radius: 6px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
            touch-action: none;
            box-sizing: border-box;
        }
        .storage-slot--empty {
            border-style: dashed;
            border-color: rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.03);
            opacity: 1;
        }
        .storage-slot--draggable { cursor: grab; }
        .storage-slot--readonly { opacity: 0.85; pointer-events: none; }
        .storage-slot--drag-over { border-color: #667eea; background: rgba(102,126,234,0.15); }
        .storage-slot .item {
            width: 100%; height: 100%; border: none; background: transparent;
            padding: 4px; aspect-ratio: auto;
        }
        .storage-slot .item-icon { font-size: 28px; }
        .storage-slot .item-qty {
            position: absolute; bottom: 2px; right: 4px;
            font-size: 11px; font-weight: 700; color: #fbbf24;
        }

        #window-inventory .window-body {
            padding: 10px;
            overflow: visible;
        }

        #inventoryContent.items {
            display: block;
        }

        #window-inventory .player-bar {
            padding: 12px 16px;
            background: rgba(255,255,255,0.03);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #window-inventory .player-name { font-size: 16px; font-weight: 600; }
        #window-inventory .player-bar { gap: 8px; flex-wrap: wrap; }
        #window-inventory .player-resources { display: flex; gap: 10px; align-items: center; }
        #window-inventory .gold { font-size: 15px; color: #fbbf24; font-weight: 700; }
        #window-inventory .experience { font-size: 15px; color: #a78bfa; font-weight: 700; }

        #window-confirm {
            width: 340px;
            min-height: unset;
            min-width: unset;
        }
        #window-confirm .confirm-window-body {
            padding: 16px 18px 18px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        #window-confirm .confirm-window-message {
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
            color: #ddd;
        }
        #window-confirm .confirm-window-message .confirm-item-link {
            color: #93c5fd;
            cursor: pointer;
            text-decoration: underline;
        }
        #window-confirm .confirm-window-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        #window-confirm .confirm-window-btn {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            border: 2px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.06);
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, border-color 0.15s;
        }
        #window-confirm .confirm-window-btn--yes:hover {
            background: rgba(16,185,129,0.25);
            border-color: #10b981;
        }
        #window-confirm .confirm-window-btn--no:hover {
            background: rgba(239,68,68,0.25);
            border-color: #ef4444;
        }

        .quest-list-item--ready {
            border-color: #10b981 !important;
            background: rgba(16,185,129,0.08);
        }
        .quest-list-item--ready::after {
            content: 'Готов к сдаче';
            display: block;
            font-size: 10px;
            color: #34d399;
            margin-top: 4px;
        }

        #window-craft, #window-disassemble {
            width: 720px;
            height: auto;
            min-height: 480px;
        }

        #window-craft .window-body,
        #window-disassemble .window-body {
            padding: 16px;
        }

        #window-quests, #window-quest {
            width: 360px;
            max-height: 70vh;
        }

        #window-quests .window-body, #window-quest .window-body {
            padding: 10px;
            overflow-y: auto;
        }

        .quests-list { display: flex; flex-direction: column; gap: 6px; max-height: 400px; overflow-y: auto; }
        .quest-list-item {
            padding: 8px 10px;
            border: 1px solid #333;
            border-radius: 6px;
            cursor: pointer;
            background: rgba(0,0,0,0.25);
        }
        .quest-list-item:hover { border-color: #10b981; }
        .quest-list-item strong { display: block; font-size: 13px; }
        .quest-list-item span { font-size: 11px; color: #aaa; }

        .quest-panel-desc { font-size: 12px; color: #bbb; margin: 6px 0 10px; }
        .quest-objectives { margin-bottom: 10px; font-size: 12px; }
        .quest-objective-row { padding: 4px 0; }
        .quest-objective-row.is-done { color: #10b981; }
        .quest-storage-section { margin-bottom: 10px; }
        .quest-section-title { font-size: 11px; color: #888; margin-bottom: 4px; text-transform: uppercase; }
        .quest-grant-grid, .quest-turnin-grid { grid-template-columns: repeat(6, var(--slot-size, 44px)); gap: 4px; }
        .quest-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px; }
        .item-icon .quest-badge {
            position: absolute; top: -2px; right: -2px; font-size: 10px; line-height: 1;
        }
        .item-icon { position: relative; }

        .workbench-panel { width: 100%; margin: 0 auto; }
        .workbench-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 96px minmax(0, 1fr);
            gap: 14px;
            align-items: stretch;
            margin-bottom: 12px;
            min-height: 260px;
        }
        .workbench-col {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .workbench-col--center {
            align-items: center;
            justify-content: flex-start;
            gap: 16px;
            padding-top: 24px;
        }
        .workbench-col--stats,
        .workbench-col--materials {
            background: rgba(0,0,0,0.15);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 10px;
        }
        .workbench-section-title {
            font-size: 11px;
            color: #aaa;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 0.04em;
            flex-shrink: 0;
        }
        .workbench-materials-grid {
            display: grid;
            grid-template-columns: repeat(4, var(--slot-size, 44px));
            gap: 8px;
            justify-content: center;
        }
        .workbench-ingredient-cell { text-align: center; }
        .workbench-storage-slot {
            width: var(--slot-size, 44px);
            height: var(--slot-size, 44px);
            margin: 0 auto;
            position: relative;
        }
        .workbench-center-slot-wrap,
        .workbench-result-slot-wrap {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .workbench-center-slot-wrap .workbench-storage-slot {
            width: calc(var(--slot-size, 44px) * 1.35);
            height: calc(var(--slot-size, 44px) * 1.35);
        }
        .workbench-result-preview {
            width: calc(var(--slot-size, 44px) * 1.35);
            height: calc(var(--slot-size, 44px) * 1.35);
            margin: 0 auto;
            opacity: 0.85;
        }
        .workbench-formula-qty {
            font-size: 10px;
            margin-top: 4px;
            font-weight: 700;
        }
        .workbench-formula-qty.is-enough { color: #10b981; }
        .workbench-formula-qty.is-short { color: #ef4444; }
        .workbench-formula-qty.is-return { color: #a5b4fc; }
        .workbench-placeholder {
            font-size: 24px;
            color: #666;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }
        .workbench-slot-caption {
            margin-top: 4px;
            font-size: 9px;
            color: #888;
            text-align: center;
        }
        .workbench-stats-body {
            flex: 1;
            font-size: 12px;
            overflow-y: auto;
            min-height: 120px;
        }
        .workbench-stats-empty {
            color: #666;
            font-style: italic;
            padding: 8px 0;
        }
        .workbench-stat-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            color: #ccc;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .workbench-stat-row strong { color: #10b981; }
        .workbench-label { display: block; font-size: 12px; color: #aaa; margin-bottom: 6px; }
        .workbench-input {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-size: 14px;
            box-sizing: border-box;
        }
        .workbench-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            margin-top: 8px;
        }
        .workbench-btn--craft { background: linear-gradient(135deg,#10b981,#059669); color: #fff; }
        .workbench-btn--disassemble { background: linear-gradient(135deg,#ef4444,#dc2626); color: #fff; }
        .workbench-craft-row {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            margin: 12px 0;
        }
        .workbench-craft-name { flex: 1; min-width: 0; }
        .workbench-craft-row .workbench-btn { flex-shrink: 0; margin-bottom: 0; }
        .workbench-btn-icon {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border: none;
            border-radius: 8px;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .workbench-btn-icon--craft {
            background: linear-gradient(135deg,#10b981,#059669);
            color: #fff;
        }
        .workbench-btn-icon--craft:hover { filter: brightness(1.08); }
        .workbench-empty-hint {
            text-align: center;
            font-size: 12px;
            color: #888;
            font-style: italic;
            padding: 12px 0;
        }
        .workbench-empty-formula { font-size: 12px; color: #888; }

        .chat-compose {
            display: flex;
            gap: 8px;
            padding: 8px 12px;
            border-top: 1px solid rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.15);
            flex-shrink: 0;
        }
        .chat-compose input {
            flex: 1;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-size: 13px;
        }
        .chat-compose button {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .chat-message {
            font-size: 12px;
            line-height: 1.45;
            padding: 4px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .chat-message-author { color: #a5b4fc; font-weight: 600; }
        .chat-message-time { color: #666; font-size: 10px; margin-right: 6px; }

        #window-journal .window-body {
            display: flex;
            flex-direction: column;
            padding: 0;
            overflow: hidden;
        }
        #window-journal .game-tabs { flex-shrink: 0; }

        #window-auction {
            width: 900px;
            height: 650px;
        }

        #window-settings {
            width: 400px;
            height: auto;
        }

        #window-trade .window-body {
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .game-tabs {
            display: flex;
            gap: 4px;
            padding: 8px 12px 0;
            background: rgba(0,0,0,0.15);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }

        .game-tab {
            padding: 6px 14px;
            border: none;
            border-radius: 6px 6px 0 0;
            background: rgba(255,255,255,0.05);
            color: #aaa;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .game-tab.active {
            background: rgba(102,126,234,0.25);
            color: #fff;
        }

        .chat-panel-content {
            flex: 1;
            overflow-y: auto;
            padding: 10px 12px;
            min-height: 0;
        }

        #chatGeneral {
            display: none;
            flex-direction: column;
            overflow: hidden;
            padding: 0;
        }
        #generalChatList {
            flex: 1;
            overflow-y: auto;
            padding: 10px 12px;
            min-height: 0;
        }

        .chat-journal-entry {
            font-size: 12px;
            line-height: 1.5;
            color: #ccc;
            padding: 4px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .chat-journal-entry .journal-time {
            color: #888;
            margin-right: 6px;
        }

        #window-item-preview {
            width: 340px;
            height: auto;
            max-height: 80%;
        }

        #window-item-preview .window-body {
            padding: 12px 15px;
        }

        #window-item-preview .tooltip-header {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        #window-character {
            width: 520px;
            height: auto;
            min-height: 420px;
        }

        #window-character .window-body {
            padding: 12px;
        }

        .character-panel {
            display: flex;
            gap: 16px;
            min-height: 380px;
        }

        .character-equipment {
            flex: 1;
            min-width: 0;
        }

        .equipment-slots {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .equipment-slot-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .equipment-slot-label {
            font-size: 10px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .equipment-slot {
            width: var(--slot-size, 44px);
            height: var(--slot-size, 44px);
        }

        .character-stats {
            width: 180px;
            flex-shrink: 0;
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 12px;
        }

        .character-stats-title {
            font-size: 13px;
            font-weight: 700;
            color: #d4a574;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .character-stat-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 4px 0;
            color: #ccc;
        }

        .character-stat-row strong {
            color: #10b981;
        }

        .chat-placeholder {
            padding: 24px;
            text-align: center;
            color: #888;
            font-size: 13px;
        }

        .online-player-row {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            background: #2a2a2a;
            border-radius: 4px;
            cursor: context-menu;
        }

        .online-player-row:hover {
            background: #333;
        }

        .player-context-menu {
            position: fixed;
            z-index: 100050;
            min-width: 180px;
            background: rgba(20, 20, 35, 0.98);
            border: 2px solid rgba(102, 126, 234, 0.6);
            border-radius: 8px;
            padding: 6px 0;
            display: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        }

        .player-context-menu.visible { display: block; }

        .player-context-menu button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 8px 14px;
            border: none;
            background: transparent;
            color: #eee;
            font-size: 13px;
            cursor: pointer;
        }

        .player-context-menu button:hover {
            background: rgba(102,126,234,0.2);
        }

        .player-context-menu button:disabled {
            opacity: 0.45;
            cursor: default;
        }

        .inventory-context-menu {
            position: fixed;
            z-index: 100050;
            min-width: 180px;
            background: rgba(20, 20, 35, 0.98);
            border: 2px solid rgba(102, 126, 234, 0.6);
            border-radius: 8px;
            padding: 6px 0;
            display: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        }

        .inventory-context-menu.visible { display: block; }

        .inventory-context-menu button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 8px 14px;
            border: none;
            background: transparent;
            color: #eee;
            font-size: 13px;
            cursor: pointer;
        }

        .inventory-context-menu button:hover {
            background: rgba(102,126,234,0.2);
        }

        #window-trade {
            width: 360px;
            height: auto;
            max-height: calc(100% - 40px);
        }

        #tradeContent {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            height: 100%;
        }

        .play-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 8px 12px;
            pointer-events: none;
        }

        .play-panel-grid {
            display: grid;
            gap: 5px;
            pointer-events: auto;
        }

        .play-panel-slot.storage-slot--empty {
            padding: 0;
        }

        .play-panel-chip {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 4px;
            background: rgba(255,255,255,0.06);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            cursor: grab;
            color: #eee;
            transition: all 0.15s;
            box-sizing: border-box;
        }

        .play-panel-chip:hover {
            background: rgba(255,255,255,0.12);
        }

        .play-panel-chip.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            box-shadow: 0 4px 16px rgba(102,126,234,0.35);
        }

        .play-panel-chip .pp-icon {
            font-size: calc(var(--slot-size, 44px) * 0.45);
            line-height: 1;
        }

        .play-panel-slot--drag-over {
            border-color: #667eea !important;
            background: rgba(102,126,234,0.15) !important;
        }

        .play-panel-drag-ghost {
            position: fixed;
            z-index: 10003;
            pointer-events: none;
            opacity: 0.9;
            padding: 6px 10px;
            background: rgba(30,30,46,0.96);
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 12px;
        }

        .event-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-left: 3px solid #667eea;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 12px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .event-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .event-icon { font-size: 16px; margin-right: 6px; }
        .event-title { font-weight: 600; font-size: 12px; }
        .event-time { font-size: 10px; color: #888; font-family: monospace; }
        .event-body { color: #bbb; font-size: 11px; line-height: 1.5; margin-top: 4px; }
        .event-body b { color: #fbbf24; }

        .events-empty { text-align: center; padding: 40px 20px; color: #666; font-size: 12px; }

        .toolbar {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 80px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            padding: 0 30px;
            z-index: 10000;
        }

        .tool-btn {
            width: 60px; height: 60px;
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            gap: 4px;
            color: #eee;
        }
        .tool-btn:hover {
            background: rgba(255,255,255,0.1);
            border-color: #667eea;
            transform: translateY(-3px);
        }
        .tool-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        .tool-btn .icon { font-size: 24px; }
        .tool-btn .label { font-size: 10px; font-weight: 600; }

        .toolbar-separator {
            width: 1px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            margin: 0 8px;
        }

        .msg {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 100000;
            display: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            max-width: 700px;
            min-width: 300px;
        }
        .msg.success { background: rgba(16,185,129,0.95); color: white; }
        .msg.error { background: rgba(239,68,68,0.95); color: white; }
        .msg.show { display: flex; animation: slideDown 0.3s ease-out; }

        @keyframes slideDown {
            from { opacity: 0; transform: translate(-50%, -20px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* Tooltip */
        .tooltip {
            position: fixed;
            background: rgba(20, 20, 35, 0.98);
            border: 2px solid rgba(102, 126, 234, 0.6);
            border-radius: 8px;
            padding: 12px 15px;
            max-width: 300px;
            z-index: 99999;
            pointer-events: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            display: none;
            font-size: 13px;
        }
        .tooltip.visible {
            display: block;
            animation: tooltipFadeIn 0.15s ease-out;
        }
        @keyframes tooltipFadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tooltip-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .tooltip-icon { font-size: 28px; }
        .tooltip-name { font-weight: 700; font-size: 15px; color: #fff; }
        .tooltip-type { font-size: 11px; color: #888; text-transform: uppercase; margin-top: 2px; }
        .tooltip-description { color: #bbb; font-size: 12px; line-height: 1.4; margin-bottom: 8px; font-style: italic; }
        .tooltip-stats { display: flex; flex-direction: column; gap: 4px; }
        .tooltip-stat { display: flex; justify-content: space-between; align-items: center; font-size: 12px; }
        .tooltip-stat-label { color: #888; }
        .tooltip-stat-value { color: #10b981; font-weight: 600; }
        .tooltip-quantity { margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.1); text-align: center; font-size: 14px; color: #fbbf24; font-weight: 700; }

        /* Mobile adaptation */
        @media (max-width: 768px) {
            .game-canvas {
                bottom: 60px;
            }
            .toolbar {
                height: 60px;
                gap: 8px;
                padding: 0 10px;
            }
            .tool-btn {
                width: 50px;
                height: 50px;
            }
            .tool-btn .icon { font-size: 20px; }
            .tool-btn .label { font-size: 9px; }
            .toolbar-separator { height: 30px; margin: 0 4px; }

            .window {
                width: 100% !important;
                height: calc(100% - 60px) !important;
                left: 0 !important;
                top: 0 !important;
                right: 0 !important;
                border-radius: 0;
            }
            .window-header {
                cursor: default;
            }
            #window-journal, #window-inventory {
                width: 100% !important;
                height: calc(100% - 60px) !important;
            }
        }
    </style>
    @stack('styles')
</head>
<body>

<div class="game-canvas" id="gameCanvas">
    <div class="game-background"></div>

    <div class="window" id="window-journal" data-window="journal">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">💬</span>
                <span>Чат</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('journal')">✕</div>
            </div>
        </div>
        <div class="window-body">
            <div id="chatTabs" class="game-tabs">
                <button type="button" class="game-tab active" data-tab="general" onclick="ChatPanel.switchTab('general')">Общий</button>
                <button type="button" class="game-tab" data-tab="guild" onclick="ChatPanel.switchTab('guild')">Гильдия</button>
                <button type="button" class="game-tab" data-tab="journal" onclick="ChatPanel.switchTab('journal')">Журнал</button>
            </div>
            <div id="chatGeneral" class="chat-panel-content">
                <div id="generalChatList"></div>
                <div id="chatComposeGeneral" class="chat-compose">
                    <input type="text" id="generalChatInput" maxlength="500" placeholder="Сообщение..." autocomplete="off">
                    <button type="button" id="generalChatSend">Отправить</button>
                </div>
            </div>
            <div id="chatGuild" class="chat-panel-content" style="display:none">
                <div class="chat-placeholder">Чат гильдии — скоро</div>
            </div>
            <div id="chatJournal" class="chat-panel-content" style="display:none">
                <div id="eventsList">
                    <div class="chat-placeholder">Загрузка...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="window" id="window-inventory" data-window="inventory">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">🎒</span>
                <span>Инвентарь</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('inventory')">✕</div>
            </div>
        </div>
        <div class="player-bar">
            <div class="player-name" id="playerName">Загрузка...</div>
            <div class="player-resources">
                <div class="gold" id="playerGold">💰 0</div>
                <div class="experience" id="playerExperience">⭐ 0</div>
            </div>
        </div>
        <div class="window-body">
            <div id="inventoryContent" class="items"></div>
        </div>
    </div>

    <div class="window" id="window-character" data-window="character">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">🛡️</span>
                <span>Персонаж</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('character')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.equipment')
        </div>
    </div>

    <div class="window" id="window-craft" data-window="craft">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">🔨</span>
                <span>Создание предмета</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('craft')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.craft-station')
        </div>
    </div>

    <div class="window" id="window-disassemble" data-window="disassemble">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">🧩</span>
                <span>Разбор предмета</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('disassemble')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.disassemble-station')
        </div>
    </div>

    <div class="window" id="window-quests" data-window="quests">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">📜</span>
                <span>Квесты</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('quests')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.quests')
        </div>
    </div>

    <div class="window" id="window-quest" data-window="quest">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">❗</span>
                <span id="questWindowTitle">Квест</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('quest')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.quest')
        </div>
    </div>

    <div class="window" id="window-auction" data-window="auction">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">🏪</span>
                <span>Аукцион</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('auction')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.auction')
        </div>
    </div>

    <div class="window" id="window-players" data-window="players">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">👥</span>
                <span>Общение</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('players')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.social')
        </div>
    </div>

    <div class="window" id="window-trade" data-window="trade">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">⇄</span>
                <span>Обмен</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('trade')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.trade')
        </div>
    </div>

    <div class="window" id="window-settings" data-window="settings">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">⚙️</span>
                <span>Настройки</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="WindowManager.close('settings')">✕</div>
            </div>
        </div>
        <div class="window-body">
            @include('partials.settings')
        </div>
    </div>

    <div class="window" id="window-item-preview" data-window="item-preview">
        <div class="window-header">
            <div class="window-title">
                <span class="icon">🔍</span>
                <span id="itemPreviewTitle">Предмет</span>
            </div>
            <div class="window-controls">
                <div class="window-btn" onclick="GameItemPreview.close()">✕</div>
            </div>
        </div>
        <div class="window-body" id="itemPreviewBody"></div>
    </div>

    @include('partials.confirm-action-modal')
</div>

<div id="playPanel" class="play-panel"></div>

<div id="playerContextMenu" class="player-context-menu">
    <button type="button" data-action="trade">Предложить обмен</button>
    <button type="button" data-action="friend">Добавить друга</button>
    <button type="button" data-action="guild">Пригласить в гильдию</button>
</div>

<div id="inventoryContextMenu" class="inventory-context-menu">
    <div id="inventoryContextMenuFormulaActions"></div>
    <button type="button" data-action="drop" style="display:none">Выбросить</button>
</div>

<div id="msg" class="msg"></div>
<div id="itemTooltip" class="tooltip"></div>
@include('partials.resource-quantity-modal')
<script src="{{ asset('js/game/game.bundle.js') }}?v=20260702g"></script>

<script>
    window.GameApi = {
        get token() {
            return localStorage.getItem('authToken');
        },
        headers(extra = {}) {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...extra,
            };
            if (this.token) {
                headers['Authorization'] = `Bearer ${this.token}`;
            }
            return headers;
        },
        async fetch(url, options = {}) {
            const response = await fetch(url, {
                ...options,
                headers: this.headers(options.headers || {}),
            });
            if (response.status === 401) {
                localStorage.removeItem('authToken');
                localStorage.removeItem('characterUuid');
                localStorage.removeItem('username');
                window.location.href = '/';
            }
            return response;
        },
        setToken(token) {
            localStorage.setItem('authToken', token);
        },
    };

    window.GameState = {
        characterUuid: null,
        inventory: [],
        recipes: [],
    };

    window.GameSettings = {
        slotSize: 44,
        _saveTimer: null,

        getSlotSize() {
            return this.slotSize || 44;
        },

        apply() {
            document.documentElement.style.setProperty('--slot-size', this.getSlotSize() + 'px');
            if (window.WindowResizer) WindowResizer.resizeAll();
            if (window.PlayPanelManager) PlayPanelManager.resize();
            if (typeof renderInventory === 'function' && window.StorageManager?.inventoryStorage) {
                renderInventory();
            }
            if (window.tradeState?.currentTrade && typeof window.renderTradeView === 'function') {
                window.renderTradeView();
            }
        },

        async load(characterUuid) {
            if (!characterUuid) return;
            try {
                const res = await GameApi.fetch(`/api/settings/${characterUuid}`);
                const data = await res.json();
                const prefs = data.settings?.ui_preferences || {};
                if (prefs.slot_size) {
                    this.slotSize = parseInt(prefs.slot_size, 10) || 44;
                }
            } catch (e) {
                console.warn('GameSettings load error:', e);
            }
            this.apply();
        },

        setSlotSize(size, persist) {
            this.slotSize = Math.max(32, Math.min(72, parseInt(size, 10) || 44));
            this.apply();
            if (!persist || !GameState.characterUuid) return;
            clearTimeout(this._saveTimer);
            this._saveTimer = setTimeout(() => this.save(), 400);
        },

        async save() {
            if (!GameState.characterUuid) return;
            try {
                await GameApi.fetch(`/api/settings/${GameState.characterUuid}/multiple`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        settings: {
                            ui_preferences: { slot_size: this.getSlotSize() },
                        },
                    }),
                });
            } catch (e) {
                console.error('GameSettings save error:', e);
            }
        },
    };

    window.WindowManager = {
        windows: {},
        zIndex: 100,
        activeWindow: null,
        positions: {},

        defaults: {
            journal:    { x: 20,  y: 20 },
            inventory:  { x: null, y: 20, right: 20 },
            character:  { center: true, verticalCenter: true },
            craft:      { center: true, verticalCenter: true },
            disassemble: { center: true, verticalCenter: true },
            quests:     { x: 20, y: 120 },
            quest:      { center: true, verticalCenter: true },
            auction:    { center: true, verticalCenter: true },
            players:    { center: true, verticalCenter: true },
            trade:      { center: true, verticalCenter: true },
            'item-preview': { center: true, verticalCenter: true },
            confirm: { center: true, verticalCenter: true },
            settings:   { x: null, y: null, right: 20, bottom: 80 },
        },

        init() {
            console.log('WindowManager.init() called');
            document.querySelectorAll('.window').forEach(win => {
                const name = win.dataset.window;
                this.windows[name] = win;
                this.positionWindow(name);
                this.makeDraggable(win);
                win.addEventListener('mousedown', () => this.focus(name));
            });
        },

        positionWindow(name) {
            const win = this.windows[name];
            const defaults = this.defaults[name];
            if (!win || !defaults) return;

            const canvas = document.getElementById('gameCanvas');
            const canvasRect = canvas.getBoundingClientRect();

            if (defaults.center) {
                // Получаем размеры окна
                const rect = win.getBoundingClientRect();
                const winWidth = rect.width || parseInt(win.style.width) || 900;
                const winHeight = rect.height || parseInt(win.style.height) || 650;
                
                // Центрируем по горизонтали
                const left = Math.max(0, (canvasRect.width - winWidth) / 2);
                win.style.left = left + 'px';
                
                // Центрируем по вертикали с проверкой границ
                if (defaults.verticalCenter) {
                    // Если окно слишком большое - размещаем сверху с отступом
                    if (winHeight > canvasRect.height - 40) {
                        win.style.top = '20px';
                    } else {
                        const top = Math.max(0, (canvasRect.height - winHeight) / 2);
                        win.style.top = top + 'px';
                    }
                } else {
                    win.style.top = '20px';
                }
                
                // Убираем right если он был установлен
                win.style.right = '';
            } else {
                if (defaults.right !== undefined) {
                    win.style.right = defaults.right + 'px';
                    win.style.left = '';
                } else if (defaults.x !== null && defaults.x !== undefined) {
                    win.style.left = defaults.x + 'px';
                }
                if (defaults.bottom !== undefined) {
                    win.style.bottom = defaults.bottom + 'px';
                    win.style.top = '';
                } else if (defaults.y !== null && defaults.y !== undefined) {
                    win.style.top = defaults.y + 'px';
                }
            }
        },

        makeDraggable(win) {
            const header = win.querySelector('.window-header');
            if (!header) {
                console.warn('No header for window:', win.dataset.window);
                return;
            }

            let isDragging = false;
            let startX, startY, startLeft, startTop;
            const winName = win.dataset.window;

            console.log('Making draggable:', winName);

            header.addEventListener('mousedown', (e) => {
                if (e.target.closest('.window-btn')) return;

                isDragging = true;
                win.classList.add('dragging');
                
                const rect = win.getBoundingClientRect();
                const canvasRect = document.getElementById('gameCanvas').getBoundingClientRect();
                
                startX = e.clientX;
                startY = e.clientY;
                startLeft = rect.left - canvasRect.left;
                startTop = rect.top - canvasRect.top;

                if (win.style.right) {
                    win.style.left = startLeft + 'px';
                    win.style.right = '';
                }

                console.log('Drag start:', winName, 'at', startLeft, startTop);
                e.preventDefault();
            });

            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;

                const canvasRect = document.getElementById('gameCanvas').getBoundingClientRect();
                let newX = startLeft + (e.clientX - startX);
                let newY = startTop + (e.clientY - startY);

                const rect = win.getBoundingClientRect();
                newX = Math.max(0, Math.min(newX, canvasRect.width - rect.width));
                newY = Math.max(0, Math.min(newY, canvasRect.height - rect.height));

                win.style.left = newX + 'px';
                win.style.top = newY + 'px';
            });

            document.addEventListener('mouseup', () => {
                if (!isDragging) return;
                
                isDragging = false;
                win.classList.remove('dragging');
                console.log('Drag end:', winName, '- saving positions');
                
                setTimeout(() => {
                    console.log('Calling savePositions for', winName);
                    window.WindowManager.savePositions();
                }, 100);
            });
        },

        toggle(name) {
            const win = this.windows[name];
            if (!win) return;

            if (win.classList.contains('active')) {
                this.close(name);
            } else {
                this.open(name);
            }
        },

        open(name) {
            const win = this.windows[name];
            if (!win) return;

            const keepOpen = ['journal', 'inventory', 'settings', 'item-preview', 'quests', 'confirm'];
            if (!keepOpen.includes(name)) {
                Object.keys(this.windows).forEach(n => {
                    if (!keepOpen.includes(n) && n !== name) {
                        this.close(n);
                    }
                });
            }

            win.classList.add('active');
            this.focus(name);
            this.updateToolbar();

            // Применяем сохранённую позицию если есть
            if (this.positions[name]) {
                const pos = this.positions[name];
                win.style.left = pos.left + 'px';
                win.style.top = pos.top + 'px';
                win.style.right = '';
            }

            if (name === 'auction' && typeof window.initAuction === 'function') {
                setTimeout(() => window.initAuction(), 50);
            }
            if (name === 'craft' && typeof window.initCraftStation === 'function') {
                setTimeout(function () {
                    window.initCraftStation();
                    if (typeof window.loadCraftPanel === 'function') {
                        window.loadCraftPanel();
                    }
                }, 50);
            }
            if (name === 'disassemble' && typeof window.initDisassembleStation === 'function') {
                setTimeout(function () {
                    window.initDisassembleStation();
                    if (typeof window.loadDisassemblePanel === 'function') {
                        window.loadDisassemblePanel();
                    }
                }, 50);
            }
            if (name === 'players' && typeof window.initPlayers === 'function') {
                setTimeout(() => window.initPlayers(), 50);
            }
            if (name === 'character' && typeof window.initCharacter === 'function') {
                setTimeout(() => window.initCharacter(), 50);
            }
            if (name === 'trade' && typeof window.initTrade === 'function') {
                setTimeout(() => window.initTrade(), 50);
            }
            if (name === 'settings' && typeof window.initSettings === 'function') {
                setTimeout(() => window.initSettings(), 50);
            }
            if (name === 'quests' && window.QuestLog) {
                setTimeout(() => QuestLog.refresh(), 50);
            }
            if (name === 'quest' && window.QuestWindow && QuestWindow.current) {
                setTimeout(() => QuestWindow.render(), 50);
            }
        },

        close(name) {
            const win = this.windows[name];
            if (!win) return;

            if (name === 'quest' && window.GameState && window.GameApi) {
                GameApi.fetch('/api/storage/' + GameState.characterUuid + '/clear-quest', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                }).then(function (res) { return res.json(); }).then(function (data) {
                    if (data.layout && window.StorageManager) {
                        StorageManager.layout = data.layout;
                        StorageManager.questStorage = data.layout.quest_storage || null;
                        StorageManager.inventoryStorage = (data.layout.storages || []).find(function (s) {
                            return s.storage_type === 'inventory';
                        }) || StorageManager.inventoryStorage;
                        StorageManager.syncGameStateInventory();
                    }
                    if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                    if (window.QuestWindow) QuestWindow.current = null;
                }).catch(function (e) {
                    console.error('clear-quest error:', e);
                });
            }

            if (name === 'craft' && window.GameState && window.GameApi) {
                GameApi.fetch('/api/storage/' + GameState.characterUuid + '/clear-craft-station', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                }).then(function (res) { return res.json(); }).then(function (data) {
                    if (data.layout && window.StorageManager) {
                        StorageManager.applyLayout(data.layout);
                    }
                    if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                    if (typeof loadPlayerData === 'function') loadPlayerData();
                }).catch(function (e) {
                    console.error('clear-craft-station error:', e);
                });
            }

            if (name === 'disassemble' && window.GameState && window.GameApi) {
                GameApi.fetch('/api/storage/' + GameState.characterUuid + '/clear-disassemble-station', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                }).then(function (res) { return res.json(); }).then(function (data) {
                    if (data.layout && window.StorageManager) {
                        StorageManager.applyLayout(data.layout);
                    }
                    if (typeof window.refreshStorageGrids === 'function') window.refreshStorageGrids();
                    if (typeof loadPlayerData === 'function') loadPlayerData();
                }).catch(function (e) {
                    console.error('clear-disassemble-station error:', e);
                });
            }

            win.classList.remove('active');
            win.classList.remove('focused');
            this.updateToolbar();
        },

        focus(name) {
            const win = this.windows[name];
            if (!win || !win.classList.contains('active')) return;

            Object.values(this.windows).forEach(w => w.classList.remove('focused'));
            win.classList.add('focused');
            win.style.zIndex = ++this.zIndex;
            this.activeWindow = name;
        },

        updateToolbar() {
            if (window.PlayPanelManager && typeof PlayPanelManager.refreshActiveState === 'function') {
                PlayPanelManager.refreshActiveState();
            }
        },

        isOpen(name) {
            const win = this.windows[name];
            return win && win.classList.contains('active');
        },

        async resetPositions() {
            if (!confirm('Сбросить все позиции окон?')) return;
            
            // Очищаем сохранённые позиции
            this.positions = {};
            
            // Сбрасываем стили
            Object.keys(this.windows).forEach(name => {
                const win = this.windows[name];
                win.style.left = '';
                win.style.top = '';
                win.style.right = '';
                this.positionWindow(name);
            });
            
            // Сохраняем пустые позиции
            await this.savePositions();
            
            showMsg('Позиции окон сброшены', 'success');
        },

        async loadPositions() {
            if (!GameState.characterUuid) {
                console.warn('Cannot load positions: characterUuid not set');
                return;
            }

            try {
                const res = await GameApi.fetch(`/api/settings/${GameState.characterUuid}`);
                const data = await res.json();
                this.positions = data.settings?.window_positions || {};

                if (this.positions.trade && !this.positions.players) {
                    this.positions.players = this.positions.trade;
                }
                
                console.log('Loaded positions:', this.positions);
                
                // Применяем сохранённые позиции только для открытых окон
                Object.keys(this.positions).forEach(name => {
                    const win = this.windows[name];
                    if (!win) return;
                    const pos = this.positions[name];
                    
                    // Применяем только если координаты валидные
                    if (pos && typeof pos.left === 'number' && typeof pos.top === 'number') {
                        win.style.left = pos.left + 'px';
                        win.style.top = pos.top + 'px';
                        // Убираем right если он был установлен
                        win.style.right = '';
                    }
                });
            } catch (e) {
                console.error('Load positions error:', e);
            }
        },

        async savePositions() {
            if (!GameState.characterUuid) {
                console.warn('Cannot save positions: characterUuid not set');
                return;
            }

            const positions = {};
            Object.keys(this.windows).forEach(name => {
                const win = this.windows[name];
                // Сохраняем все окна, используя их текущие стили
                const left = parseInt(win.style.left);
                const top = parseInt(win.style.top);
                
                // Если окно позиционировано через left/top
                if (!isNaN(left) && !isNaN(top)) {
                    positions[name] = { left, top };
                } else if (win.classList.contains('active')) {
                    // Если окно открыто, используем getBoundingClientRect
                    const rect = win.getBoundingClientRect();
                    const canvasRect = document.getElementById('gameCanvas').getBoundingClientRect();
                    const calcLeft = Math.round(rect.left - canvasRect.left);
                    const calcTop = Math.round(rect.top - canvasRect.top);
                    
                    if (calcLeft >= 0 && calcTop >= 0) {
                        positions[name] = { left: calcLeft, top: calcTop };
                    }
                }
            });

            console.log('Saving positions:', positions);

            try {
                const res = await GameApi.fetch(`/api/settings/${GameState.characterUuid}/multiple`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ settings: { window_positions: positions } })
                });
                const data = await res.json();
                console.log('Save result:', data);
            } catch (e) {
                console.error('Save positions error:', e);
            }
        }
    };

    function showMsg(text, type) {
        const el = document.getElementById('msg');
        const copyId = 'msg_' + Date.now();
        el.innerHTML = `
            <div style="display:flex;align-items:center;gap:15px;flex:1;min-width:0">
                <span style="flex:1;word-break:break-word">${text}</span>
                <button onclick="copyMsgText('${copyId}')" style="padding:6px 12px;background:rgba(255,255,255,0.2);color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;white-space:nowrap;flex-shrink:0">📋 Копировать</button>
                <span onclick="this.parentElement.parentElement.classList.remove('show')" style="cursor:pointer;font-weight:bold;opacity:0.7;font-size:18px;flex-shrink:0">✕</span>
            </div>
            <textarea id="${copyId}" style="position:absolute;left:-9999px">${text.replace(/<[^>]*>/g, '')}</textarea>
        `;
        el.className = `msg ${type} show`;
        clearTimeout(window.msgTimeout);
        window.msgTimeout = setTimeout(() => el.classList.remove('show'), 10000);
    }

    function copyMsgText(textareaId) {
        const textarea = document.getElementById(textareaId);
        if (!textarea) return;
        textarea.select();
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textarea.value);
            } else {
                document.execCommand('copy');
            }
            const btn = document.querySelector('#msg button');
            if (btn) {
                const orig = btn.innerHTML;
                btn.innerHTML = '✅ Скопировано!';
                setTimeout(() => btn.innerHTML = orig, 1500);
            }
        } catch (e) { console.error(e); }
    }

    async function loadPlayerData() {
        try {
            if (window.StorageManager) {
                const data = await StorageManager.load(GameState.characterUuid, 'inventory,equipment,craft,disassemble,stats');
                document.getElementById('playerName').textContent = data.character_name || 'Игрок';
                const gold = data.gold != null ? data.gold : StorageManager.getGold();
                if (window.GoldChip && StorageManager.inventoryStorage) {
                    const goldEl = document.getElementById('playerGold');
                    if (goldEl) GoldChip.mount(goldEl, StorageManager.inventoryStorage, gold);
                } else {
                    document.getElementById('playerGold').textContent = '💰 ' + gold;
                }
                const xp = data.experience != null ? data.experience : 0;
                if (window.ExperienceChip && StorageManager.inventoryStorage) {
                    const xpEl = document.getElementById('playerExperience');
                    if (xpEl) ExperienceChip.mount(xpEl, StorageManager.inventoryStorage, xp);
                } else {
                    const xpEl = document.getElementById('playerExperience');
                    if (xpEl) xpEl.textContent = '⭐ ' + xp;
                }
                renderInventory();
                resizeInventoryWindow();
                if (WindowManager.isOpen('character') && typeof window.renderCharacterPanel === 'function') {
                    renderCharacterPanel();
                }
                if (WindowManager.isOpen('craft') && typeof window.renderCraftPanel === 'function') {
                    renderCraftPanel();
                }
                if (WindowManager.isOpen('disassemble') && typeof window.renderDisassemblePanel === 'function') {
                    renderDisassemblePanel();
                }
                return;
            }

            const res = await GameApi.fetch(`/api/inventory/${GameState.characterUuid}`);
            const data = await res.json();

            document.getElementById('playerName').textContent = data.character_name;

            const goldResource = data.resources.find(r => r.template_slug === 'gold');
            const gold = goldResource ? goldResource.quantity : 0;
            document.getElementById('playerGold').textContent = '💰 ' + gold;

            GameState.inventory = [...data.resources, ...data.items];
            renderInventory();
        } catch (e) {
            console.error('Player data load error:', e);
        }
    }

    function resizeInventoryWindow() {
        const win = document.getElementById('window-inventory');
        if (!win || !window.StorageManager?.inventoryStorage) return;
        const cols = StorageManager.inventoryStorage.cols || 4;
        const slots = StorageManager.inventoryStorage.grid_slots || StorageManager.inventoryStorage.slots || [];
        const rows = Math.ceil(slots.length / cols) || 9;
        const slotSize = (window.GameSettings ? GameSettings.getSlotSize() : 44);
        const gap = 5, pad = 24;
        const headerH = win.querySelector('.window-header')?.offsetHeight || 32;
        const playerBar = win.querySelector('.player-bar')?.offsetHeight || 40;
        const gridW = cols * slotSize + (cols - 1) * gap + pad;
        const gridH = rows * slotSize + (rows - 1) * gap;
        win.style.width = Math.max(220, gridW) + 'px';
        win.style.height = (headerH + playerBar + gridH + pad) + 'px';
    }

    if (window.WindowResizer) {
        WindowResizer.register('inventory', resizeInventoryWindow);
    }

    window.refreshStorageGrids = function() {
        if (window.StorageManager && StorageManager.inventoryStorage) {
            renderInventory();
            resizeInventoryWindow();
        }
        if (window.StorageManager && StorageManager.equipmentStorage && typeof window.renderCharacterPanel === 'function') {
            renderCharacterPanel();
        }
        if (typeof window.renderCraftPanel === 'function' && WindowManager.isOpen('craft')) {
            renderCraftPanel();
        }
        if (typeof window.renderDisassemblePanel === 'function' && WindowManager.isOpen('disassemble')) {
            renderDisassemblePanel();
        }
        if (typeof window.refreshTradeData === 'function' && window.tradeState?.currentTrade) {
            window.refreshTradeData().then(function() {
                if (typeof window.renderTradeView === 'function') window.renderTradeView();
            });
        }
    };

    function renderInventory() {
        const el = document.getElementById('inventoryContent');
        if (window.StorageGrid && window.StorageManager && StorageManager.inventoryStorage) {
            StorageGrid.mount(el, StorageManager.inventoryStorage, {
                draggable: true,
                gridId: 'inventory-grid',
                compact: true,
            });
            resizeInventoryWindow();
            return;
        }

        if (!GameState.inventory.length) {
            el.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:30px;color:#666">Инвентарь пуст</div>';
            return;
        }
        el.innerHTML = GameState.inventory.map(item => {
            if (window.GameItemPresenter) {
                return GameItemPresenter.renderIcon(item);
            }
            const icon = item.icon || (item.stage === 'blueprint' ? '📜' : '📦');
            const qty = item.quantity ? `<div class="item-qty">x${item.quantity}</div>` : '';
            return `<div class="item game-item-interactive" data-item-uuid="${item.uuid}" data-name="${item.name}"><div class="item-icon">${icon}</div>${qty}</div>`;
        }).join('');

        window.bindItemTooltips(el);
    }

    async function loadRecipes() {
        try {
            const res = await GameApi.fetch(`/api/crafting/${GameState.characterUuid}/recipes`);
            const data = await res.json();
            GameState.recipes = data.recipes || [];
        } catch (e) {
            console.error('Recipes load error:', e);
        }
    }

    // ================================================================
    //                     REALTIME EVENTS (WebSocket + HTTP fallback)
    // ================================================================
    window.EventPoller = {
        ws: null,
        listeners: [],
        reconnectTimer: null,
        reconnectAttempts: 0,
        lastEventId: 0,
        pollTimer: null,
        connectTimeout: null,
        pollIntervalMs: 3000,
        mode: 'ws',

        start(characterUuid) {
            window.characterUuid = characterUuid;
            this.characterUuid = characterUuid;
            this.connect();
            this.scheduleConnectFallback();
        },

        stop() {
            this.stopPoll();
            if (this.connectTimeout) {
                clearTimeout(this.connectTimeout);
                this.connectTimeout = null;
            }
            if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
            if (this.ws) { this.ws.close(); this.ws = null; }
        },

        on(callback) {
            this.listeners.push(callback);
        },

        trackEventId(eventId) {
            if (eventId != null && eventId > this.lastEventId) {
                this.lastEventId = eventId;
            }
        },

        dispatchEvents(events) {
            if (!events || !events.length) return;
            const sorted = events.slice().sort((a, b) => (a.id || 0) - (b.id || 0));
            sorted.forEach((event) => {
                if (event.id != null) {
                    this.trackEventId(event.id);
                }
            });
            this.listeners.forEach(cb => {
                try { cb(sorted); } catch (e) { console.error('Listener error:', e); }
            });
        },

        scheduleConnectFallback() {
            if (this.connectTimeout) clearTimeout(this.connectTimeout);
            this.connectTimeout = setTimeout(() => {
                if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
                    this.startPoll();
                }
            }, 5000);
        },

        startPoll() {
            if (this.pollTimer) return;
            this.mode = 'poll';
            console.log('EventPoller: HTTP polling mode (3s)');

            const poll = () => {
                if (!this.characterUuid || !window.GameApi) return;
                const afterId = this.lastEventId || 0;
                GameApi.fetch(`/api/events/${this.characterUuid}/latest?after_id=${afterId}`)
                    .then(r => r.json())
                    .then(data => {
                        this.dispatchEvents(data.events || []);
                    })
                    .catch(() => {});
            };

            poll();
            this.pollTimer = setInterval(poll, this.pollIntervalMs);
        },

        stopPoll() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        connect() {
            const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
            const lastId = this.lastEventId || 0;
            const url = `${protocol}//${location.host}/ws?character_uuid=${this.characterUuid}&last_event_id=${lastId}`;
            console.log('WebSocket connecting to:', url);

            this.ws = new WebSocket(url);

            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.reconnectAttempts = 0;
                this.mode = 'ws';
                if (this.connectTimeout) {
                    clearTimeout(this.connectTimeout);
                    this.connectTimeout = null;
                }
                this.stopPoll();
            };

            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    if (data.type === 'connected') return;
                    this.dispatchEvents([data]);
                } catch (e) {
                    console.error('WebSocket parse error:', e);
                }
            };

            this.ws.onerror = () => {
                console.error('WebSocket error');
            };

            this.ws.onclose = () => {
                console.log('WebSocket closed, reconnecting...');
                this.startPoll();
                const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
                this.reconnectAttempts++;
                this.reconnectTimer = setTimeout(() => this.connect(), delay);
            };
        }
    };

    // ================================================================
    //                     CHAT PANEL (journal tab = personal public events)
    // ================================================================
    window.ChatPanel = {
        activeTab: 'general',
        maxEntries: 20,
        lastEventId: 0,
        seenIds: new Set(),
        generalSeenIds: new Set(),
        generalLastId: 0,
        generalPollTimer: null,
        publicTypes: [
            'user.registered',
            'auction.listed',
            'auction.purchased',
            'auction.sold',
            'trade.completed',
            'item.crafted',
            'item.disassembled',
            'presence.changed',
        ],

        init() {
            EventPoller.on((events) => {
                events.forEach(e => {
                    if (!this.publicTypes.includes(e.type)) return;
                    if (e.id != null && e.id <= this.lastEventId) return;
                    this.addEvent(e, true);
                });
            });

            const sendBtn = document.getElementById('generalChatSend');
            const input = document.getElementById('generalChatInput');
            if (sendBtn) sendBtn.addEventListener('click', () => this.sendGeneralMessage());
            if (input) {
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') this.sendGeneralMessage();
                });
            }
        },

        itemLink(payload, slugKey, nameKey, quantityKey) {
            const p = payload || {};
            const slug = p[slugKey] || p.template_slug;
            if (!slug || !window.GameItemPresenter) {
                return `<b>${p[nameKey] || slug || '???'}</b>`;
            }
            const qty = quantityKey ? (p[quantityKey] || 1) : 1;
            let desc = GameItemPresenter.descriptorFromSlug(slug, qty);
            if (p[nameKey] || p.custom_name) {
                desc.name = p.custom_name || p[nameKey];
            }
            if (p.icon) desc.icon = p.icon;
            if (p.stats && Object.keys(p.stats).length) {
                desc.stats = p.stats;
            }
            desc.stage = 'item';
            return GameItemPresenter.renderLink(desc);
        },

        switchTab(tab) {
            this.activeTab = tab;
            document.querySelectorAll('#chatTabs .game-tab').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });
            document.getElementById('chatGeneral').style.display = tab === 'general' ? 'flex' : 'none';
            document.getElementById('chatGuild').style.display = tab === 'guild' ? 'block' : 'none';
            document.getElementById('chatJournal').style.display = tab === 'journal' ? 'block' : 'none';
            this.stopGeneralPoll();
            if (tab === 'general') {
                this.loadGeneralChat(false);
                this.startGeneralPoll();
            }
        },

        async loadGeneralChat(pollOnly = false) {
            try {
                const characterUuid = GameState.characterUuid || window.characterUuid;
                const afterParam = pollOnly && this.generalLastId ? `&after_id=${this.generalLastId}` : '';
                const res = await GameApi.fetch(
                    `/api/chat/${characterUuid}/messages?channel=general&limit=50${afterParam}`
                );
                const data = await res.json();
                const list = document.getElementById('generalChatList');
                if (!list) return;

                if (!pollOnly) {
                    list.innerHTML = '';
                    this.generalSeenIds.clear();
                    this.generalLastId = 0;
                }

                const messages = data.messages || [];
                if (!pollOnly && messages.length === 0) {
                    list.innerHTML = '<div class="chat-placeholder">Пока нет сообщений</div>';
                    return;
                }

                messages.forEach(m => this.appendGeneralMessage(m, pollOnly));
            } catch (e) {
                console.error('General chat load error:', e);
            }
        },

        appendGeneralMessage(message, isNew) {
            if (!message || message.id == null) return;
            if (this.generalSeenIds.has(message.id)) return;
            this.generalSeenIds.add(message.id);
            if (message.id > this.generalLastId) this.generalLastId = message.id;

            const list = document.getElementById('generalChatList');
            if (!list) return;
            const placeholder = list.querySelector('.chat-placeholder');
            if (placeholder) placeholder.remove();

            const el = document.createElement('div');
            el.className = 'chat-message';
            el.dataset.messageId = String(message.id);
            el.innerHTML = `<span class="chat-message-time">${message.created_at || ''}</span>` +
                `<span class="chat-message-author">${message.character_name || 'Игрок'}:</span> ` +
                this.escapeHtml(message.body || '');
            list.appendChild(el);
            if (isNew) list.scrollTop = list.scrollHeight;

            while (list.children.length > 50) {
                const removed = list.firstChild;
                if (removed && removed.dataset && removed.dataset.messageId) {
                    this.generalSeenIds.delete(Number(removed.dataset.messageId));
                }
                list.removeChild(removed);
            }
        },

        escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },

        async announceConnection() {
            try {
                const characterUuid = GameState.characterUuid || window.characterUuid;
                const res = await GameApi.fetch(`/api/chat/${characterUuid}/send`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ channel: 'general', message: 'онлайн' }),
                });
                const data = await res.json();
                if (data.message) this.appendGeneralMessage(data.message, true);
            } catch (e) {
                console.error('Chat connection announce error:', e);
            }
        },

        async sendGeneralMessage() {
            const input = document.getElementById('generalChatInput');
            if (!input) return;
            const text = input.value.trim();
            if (!text) return;

            try {
                const characterUuid = GameState.characterUuid || window.characterUuid;
                const res = await GameApi.fetch(`/api/chat/${characterUuid}/send`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ channel: 'general', message: text }),
                });
                const data = await res.json();
                if (data.error) {
                    showMsg(data.error, 'error');
                    return;
                }
                input.value = '';
                if (data.message) this.appendGeneralMessage(data.message, true);
            } catch (e) {
                showMsg('Ошибка отправки: ' + e.message, 'error');
            }
        },

        startGeneralPoll() {
            this.stopGeneralPoll();
            this.generalPollTimer = setInterval(() => {
                if (this.activeTab === 'general') this.loadGeneralChat(true);
            }, 3000);
        },

        stopGeneralPoll() {
            if (this.generalPollTimer) {
                clearInterval(this.generalPollTimer);
                this.generalPollTimer = null;
            }
        },

        async loadInitial() {
            try {
                const characterUuid = GameState.characterUuid || window.characterUuid;
                const res = await GameApi.fetch(
                    `/api/events/${characterUuid}/latest?visibility=public&limit=${this.maxEntries}`
                );
                const data = await res.json();
                const list = document.getElementById('eventsList');
                if (!list) return;

                if (data.events && data.events.length > 0) {
                    list.innerHTML = '';
                    this.seenIds.clear();
                    this.lastEventId = 0;
                    data.events.forEach(e => this.addEvent(e, false));
                    EventPoller.lastEventId = this.lastEventId;
                } else {
                    list.innerHTML = '<div class="chat-placeholder">Пока нет событий</div>';
                    this.lastEventId = 0;
                    EventPoller.lastEventId = 0;
                }
            } catch (e) {
                console.error('ChatPanel load error:', e);
            }
        },

        addEvent(event, isNew) {
            if (!this.publicTypes.includes(event.type)) return;
            if (event.id != null && this.seenIds.has(event.id)) return;
            if (event.id != null) {
                this.seenIds.add(event.id);
                if (event.id > this.lastEventId) {
                    this.lastEventId = event.id;
                }
                if (window.EventPoller) {
                    EventPoller.trackEventId(event.id);
                }
            }

            const list = document.getElementById('eventsList');
            if (!list) return;

            const placeholder = list.querySelector('.chat-placeholder');
            if (placeholder) placeholder.remove();

            const html = this.formatPublicEventHtml(event);
            if (!html) return;

            const el = document.createElement('div');
            el.className = 'chat-journal-entry';
            if (event.id != null) el.dataset.eventId = String(event.id);
            el.innerHTML = html;
            list.appendChild(el);
            if (window.GameItemPresenter) {
                GameItemPresenter.applyItemInteractions(el);
            }

            while (list.children.length > this.maxEntries) {
                const removed = list.firstChild;
                if (removed && removed.dataset && removed.dataset.eventId) {
                    this.seenIds.delete(Number(removed.dataset.eventId));
                }
                list.removeChild(removed);
            }
            if (isNew) list.scrollTop = list.scrollHeight;
        },

        formatPublicEventHtml(event) {
            const p = event.payload || {};
            const actor = event.actor_name || p.character_name || p.username || 'Игрок';
            const time = event.occurred_at || '';
            const timeHtml = `<span class="journal-time">${time}</span>`;

            switch (event.type) {
                case 'user.registered':
                    return `${timeHtml}${p.username || actor} зарегистрировался`;
                case 'auction.listed':
                    return `${timeHtml}Аукцион: выставлен ${this.itemLink(p, 'template_slug', 'name', 'quantity')}`;
                case 'auction.purchased':
                    return `${timeHtml}Аукцион: покупка ${this.itemLink(p, 'template_slug', 'name', 'quantity')}`;
                case 'auction.sold':
                    return `${timeHtml}Аукцион: продажа ${this.itemLink(p, 'template_slug', 'name', 'quantity')}`;
                case 'trade.completed':
                    return `${timeHtml}${actor} завершил обмен`;
                case 'item.crafted':
                    return `${timeHtml}Создан предмет ${this.itemLink(p, 'item_template_slug', 'custom_name')}`;
                case 'item.disassembled':
                    return `${timeHtml}Разобран предмет ${this.itemLink(p, 'item_template_slug', 'custom_name')}`;
                case 'presence.changed':
                    if (p.action === 'online') {
                        return `${timeHtml}${p.character_name || actor} вошёл в игру`;
                    }
                    return null;
                default:
                    return null;
            }
        },

        formatPublicEventText(event) {
            const el = document.createElement('div');
            el.innerHTML = this.formatPublicEventHtml(event) || '';
            return el.textContent;
        },
    };

    window.Journal = window.ChatPanel;

    // ================================================================
    //                     UI UPDATER
    // ================================================================
    window.UIUpdater = {
        init() {
            EventPoller.on((events) => this.handle(events));
        },

        handle(events) {
            let needsInventoryUpdate = false;
            let needsAuctionUpdate = false;
            let needsTradeUpdate = false;
            let needsOnlineUpdate = false;
            let tradeCompleted = false;
            let tradeCreated = false;

            events.forEach(e => {
                if (e.type === 'presence.changed') {
                    needsOnlineUpdate = true;
                }
                if (['item.received', 'item.removed', 'item.crafted', 'item.disassembled', 'resource.transferred'].includes(e.type)) {
                    needsInventoryUpdate = true;
                }
                if (['auction.listed', 'auction.purchased', 'auction.cancelled'].includes(e.type)) {
                    needsInventoryUpdate = true;
                    needsAuctionUpdate = true;
                }
                if ([
                    'trade.created', 'trade.updated', 'trade.item_added', 'trade.resource_added',
                    'trade.confirmed', 'trade.cancelled', 'trade.completed',
                ].includes(e.type)) {
                    needsTradeUpdate = true;
                    if (e.type === 'trade.completed') tradeCompleted = true;
                    if (e.type === 'trade.created') tradeCreated = true;
                }
            });

            if (needsOnlineUpdate && WindowManager.isOpen('players')
                && window.playersState?.activeTab === 'trade'
                && typeof window.renderTradeTabView === 'function') {
                setTimeout(() => window.renderTradeTabView(), 100);
            }

            if (needsTradeUpdate && WindowManager.isOpen('players')
                && window.playersState?.activeTab === 'trade'
                && typeof window.renderTradeTabView === 'function') {
                setTimeout(() => window.renderTradeTabView(), 100);
            }

            if (needsInventoryUpdate || needsTradeUpdate) {
                setTimeout(() => loadPlayerData(), 100);
            }
            if (needsAuctionUpdate && WindowManager.isOpen('auction')) {
                setTimeout(() => {
                    if (typeof window.loadMarket === 'function') window.loadMarket();
                    if (typeof window.loadMyLots === 'function') window.loadMyLots();
                }, 150);
            }
            if (needsTradeUpdate) {
                setTimeout(() => {
                    if (tradeCompleted && typeof window.onTradeCompleted === 'function') {
                        window.onTradeCompleted();
                    } else if (tradeCreated) {
                        WindowManager.open('trade');
                        if (typeof window.loadTrades === 'function') {
                            window.loadTrades();
                        }
                        if (typeof window.refreshTradeTabIfOpen === 'function') {
                            window.refreshTradeTabIfOpen();
                        }
                    } else if (typeof window.loadTrades === 'function') {
                        window.loadTrades();
                    }
                }, 150);
            }
        }
    };

    window.showPlayerContextMenu = function(event, uuid, name) {
        if (window.GameItemTooltip) GameItemTooltip.hide();
        const menu = document.getElementById('playerContextMenu');
        if (!menu) return;
        menu.dataset.playerUuid = uuid;
        menu.dataset.playerName = name;
        menu.style.left = event.clientX + 'px';
        menu.style.top = event.clientY + 'px';
        menu.classList.add('visible');
    };

    window.readInventoryItemFromElement = function(itemEl) {
        if (window.readItemDescriptorFromElement) {
            return window.readItemDescriptorFromElement(itemEl);
        }
        return {
            uuid: itemEl.dataset.itemUuid,
            name: itemEl.dataset.name,
            stage: itemEl.dataset.stage,
            recipe_slug: itemEl.dataset.recipeSlug || '',
            template_slug: itemEl.dataset.templateSlug,
            quantity: parseInt(itemEl.dataset.quantity, 10) || 1,
            max_stack: itemEl.dataset.maxStack ? parseInt(itemEl.dataset.maxStack, 10) : null,
            icon: itemEl.dataset.icon,
            slot_type: itemEl.dataset.slotType || '',
            locked: itemEl.dataset.locked === '1',
        };
    };

    window.showInventoryContextMenu = function(event, item, sourceSlotUuid) {
        if (window.GameItemTooltip) GameItemTooltip.hide();
        const menu = document.getElementById('inventoryContextMenu');
        if (!menu || !item) return;

        menu.dataset.itemJson = JSON.stringify(item);
        menu.dataset.sourceSlotUuid = sourceSlotUuid || item.slot_uuid || '';
        const formulaActionsEl = document.getElementById('inventoryContextMenuFormulaActions');
        const dropBtn = menu.querySelector('[data-action="drop"]');

        const craftActions = window.getCraftActions ? getCraftActions(item) : [];
        const disassembleActions = window.getDisassembleActions ? getDisassembleActions(item) : [];
        const isQuestItem = item.stage === 'quest_item' || Boolean(item.quest_slug);
        const canDrop = Boolean(item.uuid) && !item.locked
            && (item.stage === 'item' || item.stage === 'blueprint' || isQuestItem);

        if (formulaActionsEl) {
            const buttons = [];
            craftActions.forEach(function (action) {
                buttons.push(
                    '<button type="button" data-action="craft" data-recipe-slug="' + action.recipe_slug + '" data-mode="' + action.mode + '">' +
                    action.label + '</button>'
                );
            });
            disassembleActions.forEach(function (action) {
                buttons.push(
                    '<button type="button" data-action="disassemble" data-recipe-slug="' + action.recipe_slug + '" data-mode="' + action.mode + '">' +
                    action.label + '</button>'
                );
            });
            formulaActionsEl.innerHTML = buttons.join('');
        }

        if (dropBtn) dropBtn.style.display = canDrop ? 'block' : 'none';

        if (!craftActions.length && !disassembleActions.length && !canDrop) return;

        menu.style.left = event.clientX + 'px';
        menu.style.top = event.clientY + 'px';
        menu.classList.add('visible');
    };

    document.getElementById('inventoryContextMenu')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const menu = document.getElementById('inventoryContextMenu');
        const action = btn.dataset.action;
        let item = null;
        try {
            item = JSON.parse(menu?.dataset.itemJson || 'null');
        } catch (err) {
            item = null;
        }
        menu.classList.remove('visible');

        if (!item) return;

        const sourceSlotUuid = menu?.dataset.sourceSlotUuid || item.slot_uuid || '';

        if (window.ItemDispatcher) {
            ItemDispatcher.handleContextAction(action, item, sourceSlotUuid, {
                recipeSlug: btn.dataset.recipeSlug || null,
                mode: btn.dataset.mode || null,
            });
            return;
        }
    });

    document.addEventListener('click', () => {
        document.getElementById('playerContextMenu')?.classList.remove('visible');
        document.getElementById('inventoryContextMenu')?.classList.remove('visible');
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.getElementById('playerContextMenu')?.classList.remove('visible');
            document.getElementById('inventoryContextMenu')?.classList.remove('visible');
        }
    });

    document.getElementById('playerContextMenu')?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const menu = document.getElementById('playerContextMenu');
        const uuid = menu?.dataset.playerUuid;
        const action = btn.dataset.action;
        menu.classList.remove('visible');

        if (action === 'trade' && uuid) {
            startTrade(uuid);
        } else if (action === 'friend' || action === 'guild') {
            showMsg('Скоро', 'info');
        }
    });

    document.addEventListener('DOMContentLoaded', async () => {
        const characterUuid = localStorage.getItem('characterUuid');
        const authToken = localStorage.getItem('authToken');
        if (!characterUuid || !authToken) {
            window.location.href = '/';
            return;
        }

        GameState.characterUuid = characterUuid;

        WindowManager.init();
        await WindowManager.loadPositions();

        await Promise.all([
            loadPlayerData(),
            loadRecipes(),
            GameSettings.load(characterUuid),
            window.PlayPanelManager ? PlayPanelManager.load(characterUuid) : Promise.resolve(),
            window.GameItemPresenter ? GameItemPresenter.loadTemplateCache() : Promise.resolve(),
        ]);

        WindowManager.open('journal');
        WindowManager.open('inventory');

        // Запускаем polling событий
        window.characterUuid = characterUuid;
        if (window.playersState) {
            window.playersState.characterUuid = characterUuid;
        }
        if (window.tradeState) {
            window.tradeState.characterUuid = characterUuid;
        }
        EventPoller.characterUuid = characterUuid;
        ChatPanel.init();
        ChatPanel.switchTab('general');
        await ChatPanel.loadInitial();
        await ChatPanel.announceConnection();
        EventPoller.start(characterUuid);
        UIUpdater.init();

        // Heartbeat каждые 30 секунд
        setInterval(() => {
            GameApi.fetch(`/api/heartbeat/${characterUuid}`, { method: 'POST' }).catch(() => {});
        }, 30000);
        GameApi.fetch(`/api/heartbeat/${characterUuid}`, { method: 'POST' }).catch(() => {});

        const inventoryContent = document.getElementById('inventoryContent');
        if (!inventoryContent) return;

        inventoryContent.addEventListener('contextmenu', (e) => {
            const itemEl = e.target.closest('.game-item-interactive');
            if (!itemEl) return;
            e.preventDefault();
            const slotEl = itemEl.closest('.storage-slot[data-slot-uuid]');
            const fullItem = readInventoryItemFromElement(itemEl);
            showInventoryContextMenu(e, fullItem, slotEl ? slotEl.dataset.slotUuid : '');
        });

        document.addEventListener('contextmenu', (e) => {
            if (e.target.closest('#inventoryContent .game-item-interactive')) return;
            if (e.target.closest('.online-player-row')) return;
            e.preventDefault();
        }, true);

        inventoryContent.addEventListener('dblclick', (e) => {
            const itemEl = e.target.closest('.game-item-interactive');
            if (!itemEl) return;

            const fullItem = readInventoryItemFromElement(itemEl);
            const slotEl = itemEl.closest('.storage-slot[data-slot-uuid]');
            const fromSlotUuid = slotEl ? slotEl.dataset.slotUuid : (fullItem.slot_uuid || '');

            if (window.ItemDispatcher) {
                ItemDispatcher.handleInventoryDblclick(fullItem, fromSlotUuid);
            }
        });
    });
</script>
@stack('scripts')
