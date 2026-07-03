# World API

Серверный spatial-слой для 3D-клиента (Unity) и web debug UI.

## Контент

Зоны описаны в [`content/zones.json`](../content/zones.json):

- `bounds` — AABB для anti-cheat
- `spawns` — точки появления (`default`, `south`, …)
- `portals` — переходы между зонами (auto при `move` в радиусе)
- `interactables` — NPC/станции (`open_window` → имя окна UI)

## Конфиг (`config/game.php`)

| Ключ | Default | Назначение |
|------|---------|------------|
| `world_max_speed` | 15 | макс. скорость (м/с) |
| `world_max_step` | 12 | макс. шаг за один запрос |
| `world_step_size` | 3 | шаг debug-кнопок N/S/E/W |
| `world_interact_radius` | 5 | радиус взаимодействия |
| `world_portal_radius` | 4 | радиус срабатывания портала |
| `world_nearby_radius` | 30 | радиус списка игроков |

## Endpoints (Bearer + `character.owner`)

| Method | Path | Описание |
|--------|------|----------|
| GET | `/api/world/zones` | каталог зон |
| GET | `/api/world/zones/{zoneSlug}` | метаданные зоны |
| GET | `/api/world/{characterUuid}` | текущая позиция |
| GET | `/api/world/{characterUuid}/context` | позиция + nearby players/interactables/portals |
| GET | `/api/world/{characterUuid}/nearby?radius=30` | игроки рядом |
| POST | `/api/world/{characterUuid}/move` | `{x,y,z,rotation_y?}` |
| POST | `/api/world/{characterUuid}/step` | `{direction: north\|south\|east\|west}` |
| POST | `/api/world/{characterUuid}/interact` | `{target_id}` → `{action, window?}` |
| POST | `/api/world/{characterUuid}/use-portal` | `{portal_id}` |
| POST | `/api/world/{characterUuid}/enter-zone` | `{zone_slug, spawn_id?}` (admin/debug) |

## Ответ move / step / use-portal

```json
{
  "success": true,
  "state": {
    "zone_slug": "craft_city",
    "zone_name": "Крафт-Сити",
    "x": 0, "y": 0, "z": 48,
    "rotation_y": 0
  },
  "portal_used": {
    "id": "gate_north",
    "from_zone": "craft_city",
    "to_zone": "forest_edge"
  }
}
```

`portal_used` — `null`, если портал не сработал.

## События (`game_events`)

| type | aggregate | Когда |
|------|-----------|-------|
| `world.moved` | `world/{zone_slug}` | после move/step |
| `world.entered_zone` | `world/{zone_slug}` | spawn, portal, enter-zone |
| `world.interacted` | `world/{zone_slug}` | interact |

Подписчики WebSocket в той же зоне получают `world.moved` / `world.entered_zone` соседей.

## Web debug UI

Панель **«Мир (debug)»** слева под unit frame:

- координаты и зона
- шаги ↑←↓→
- список игроков / объектов / порталов в радиусе
- **Use** → `interact` + `WindowManager.open(window)`

## Unity integration

См. [UNITY_CLIENT.md](./UNITY_CLIENT.md) и проект `clients/unity/`.

1. `GET /context` при входе в зону
2. `GET /game/meta` → лимиты движения
3. `POST /move` каждые N ms с позицией персонажа
4. `POST /interact` по raycast / клавиша E
5. WebSocket `/ws` → `world.moved` для других игроков
6. Порталы — auto на сервере при `move` в радиус

## Тесты

- PHPUnit: `WorldServiceTest`, `WorldApiTest`
- Smoke: `scripts/api_bot_smoke_test.py` (spawn, nearby, interact, portal roundtrip)
