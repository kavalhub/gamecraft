# Квесты

## Поток игрока

1. Купить квестовый предмет (`quest_item`) на аукционе у NPC (цена ≥ 1 золота). Аукцион сначала ищет экземпляр в world-pool, иначе создаёт новый.
2. Dblclick по предмету → окно квеста (принять / завершить — по статусу квеста).
3. «Принять» → предметы выдачи (`accept_grants`) сразу в инвентарь (если нет места — ошибка).
4. Выполнить objectives → журнал квестов (📜) → открыть квест → «Завершить».
5. Атомарный обмен: сдаваемые предметы → world-pool, награды → инвентарь.

Квестовый предмет (`quest_item`): dblclick только открывает квест; drag только внутри инвентаря; можно выбросить в мир (`POST /api/storage/{uuid}/drop-to-world`).

## API

- `GET /api/quests/{characterUuid}` — списки available / active / finished
- `GET /api/quests/{characterUuid}/{questSlug}` — сессия + layout
- `POST /api/quests/{characterUuid}/accept` — принять + autoloot grants
- `POST /api/quests/{characterUuid}/turn-in` — сдать (exchange)
- `POST /api/storage/{characterUuid}/clear-quest` — сброс overlay
- `POST /api/storage/{characterUuid}/drop-to-world` — `{ item_uuid }` → world storage

## Контент

[`content/quests.json`](../content/quests.json) — квесты с `accept_grants`, `objectives`, `rewards`.

[`content/base.json`](../content/base.json) — шаблон `quest_item` с `quest_slug`, лот в `shop_lots`.

## Хранилище квеста

`temporary_slots` с полями `quest_slug`, `slot_role`:

| role | UI | Назначение |
|------|-----|------------|
| `grant` | «Предметы для квеста» (offer) | Preview выдачи |
| `requirement` | скрыто | Авто-перенос предметов для сдачи |
| `reward` | «Награда» (active) | Предзаполненные награды |

## World pool

`storages.storage_type = world` (system character). Instance-предметы (`quest_item`, `blueprint`, item) переиспользуются: выброс / сдача квеста → world → покупка на аукционе (`SELECT FOR UPDATE`).

Stackable resources (wood, gold) — отдельная таблица `resources`, world-pool не применяется.

Опыт — ресурс `experience` в спецслоте инвентаря (как gold).
