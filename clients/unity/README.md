# Unity Client (Sprint 3)

3D-клиент **Крафт-Мир** поверх [World API](../../docs/WORLD_API.md).

## Требования

- Unity **2022.3 LTS** (или новее)
- Сервер: `http://local.game.local` (или ваш `baseUrl`)
- WebSocket на `/ws` (standalone build)

## Быстрый старт

1. Откройте папку `clients/unity` в Unity Hub → **Add project from disk**.
2. Дождитесь импорта пакетов (Newtonsoft JSON, TextMeshPro).
3. Меню **CraftWorld → Create API Config Asset** — задайте `baseUrl`.
4. Меню **CraftWorld → Setup Bootstrap Scene** — создаст сцену `Assets/_Game/Scenes/Bootstrap.unity`.
5. **Play** → логин (существующий аккаунт с персонажем).

> Если config-asset не создан, клиент использует `http://local.game.local` по умолчанию.

## Управление

| Клавиша | Действие |
|---------|----------|
| WASD | Движение (скорость ≤ `world.max_speed` с сервера) |
| E | Взаимодействие с ближайшим объектом |

Позиция синхронизируется с сервером каждые ~250 ms через `POST /move`. Порталы срабатывают на сервере при входе в радиус.

## Архитектура

```
GameSession          — login, meta, WS connect
  GameApiClient      — REST (Sanctum Bearer)
  GameEventSocket    — /ws events (world.moved, world.entered_zone)
  WorldCoordinator   — zone load, local player, remotes
  LocalPlayerMotor   — CharacterController + anti-cheat sync
  ZoneVisualBuilder  — ground + interactables + portals из GET /zones
```

## Координаты

Сервер и Unity используют одну систему: **X** — восток/запад, **Y** — высота, **Z** — север/юг.

## Следующие шаги (Sprint 3+)

- [ ] UGUI/TMP вместо IMGUI login
- [ ] Модели зон вместо примитивов
- [ ] Raycast interact + подсказка «E»
- [ ] Открытие in-game UI по `interact.window` (WebView или native panels)
- [ ] WebGL: замена `ClientWebSocket` на polling `/api/events`

Подробнее: [docs/UNITY_CLIENT.md](../../docs/UNITY_CLIENT.md)
