# UI Design — Унифицированные хранилища

> Версия: 1.1 · 28 июня 2026

## Общие принципы

- Все хранилища рендерятся компонентом **StorageGrid** (IIFE в `game.bundle.js`, совместимость с Opera)
- Перетаскивание через **DragEngine** на pointer events (не HTML5 `draggable`)
- Данные загружает **StorageManager** с `GET /api/storage/{characterUuid}`
- Перемещения: `POST /api/storage/{characterUuid}/move`

## CSS-классы

```css
.storage-grid       /* display:grid; gap:6px; cols через inline style */
.storage-slot       /* ячейка 1:1, рамка, фон */
.storage-slot--empty
.storage-slot--draggable
.storage-slot--readonly   /* partner trade grid */
.storage-slot--drag-over
.storage-drag-ghost       /* ghost при перетаскивании */
```

Предмет внутри ячейки: `GameItemPresenter.renderIcon()` + класс `storage-slot-item`.

## StorageGrid

```javascript
StorageGrid.render(storageData, { cols, draggable, readonly, gridId })
StorageGrid.mount(container, storageData, options)
```

`storageData` из API:

```json
{
  "uuid": "...",
  "storage_type": "inventory",
  "cols": 4,
  "grid_slots": [ { "uuid": "...", "slot_type": null, "item": null, "resource": { ... } } ],
  "special_slots": [ { "uuid": "...", "slot_type": "gold", "hidden": true, "resource": { ... } } ],
  "slots": [ "... grid_slots alias ..." ]
}
```

Корневой ответ: `gold` — количество в gold-слоте (для шапки).

## Gold chip и SpecialSlotsBar

- **GoldChip** — золото в шапке инвентаря (`#playerGold`), draggable в trade/сетку
- **SpecialSlotsBar** — видимые спец-слоты над сеткой (будущее: wood×3)
- Сетка рендерит только `grid_slots` (без hidden спец-слотов)

## DragEngine

1. `pointerdown` на `.storage-slot--draggable` или `.gold-chip--draggable`
2. Ghost следует за курсором
3. `pointerup` на целевой ячейке → `StorageManager.move(from, to, qty?)`
4. **Обычный drag** ресурса = весь стак (`quantity` не передаётся)
5. **Shift+drag** ресурса с qty>1 → модалка `ResourceQuantityModal`
6. После move: `refreshStorageGrids()` перерисовывает инвентарь и trade

## Окна

| Окно | Сетка | Опции |
|------|-------|-------|
| Инвентарь | 4×9 (36) + gold chip | draggable |
| Обмен (мои) | 4×5 (20) | draggable |
| Обмен (партнёр) | 4×5 (20) | readonly |

Шапка обмена: **слева партнёр**, **справа я** — соответствует колонкам.

## Тултипы и модалки

- Hover → `GameItemTooltip`
- Click → `GameItemDetailModal`
- Dblclick в инвентаре → shortcut в верстак / аукцион / обмен (если окно открыто)

## Верстак

- Центральный слот `#centerSlot` сбрасывается в placeholder при `clearWorkbench()`
- `window.initWorkbench()` — биндинг кнопок при открытии окна
