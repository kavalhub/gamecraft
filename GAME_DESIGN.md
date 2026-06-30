# 📜 Крафт-Мир — Финальная архитектура БД

> **Версия:** 3.0 (финальная)  
> **Дата:** 27 июня 2026  
> **Статус:** Готово к реализации

---

## 🎯 Общая концепция

Крафт-Мир — браузерная MMO с упором на крафт, торговлю и социальные взаимодействия.

### Ключевые принципы

1. **Event Sourcing как источник правды** — все изменения фиксируются в `game_events`. Любое состояние — проекция, которую можно пересчитать.
2. **Разделение ресурсов и предметов** — ресурсы (дерево, руда, золото) хранятся как балансы, предметы (мечи, чертежи) — как уникальные экземпляры.
3. **Хранилища как универсальные контейнеры** — предметы привязаны к слотам хранилищ. Хранилище может принадлежать игроку, гильдии, аукциону или быть системным.
4. **Чертежи как "душа" предметов** — чертёж не исчезает при крафте, а становится частью предмета и возвращается при разборке.
5. **Вечные UUID** — предметы и ресурсы никогда не удаляются из БД, только меняют хранилище.
6. **UUIDv4** — все сущности используют случайный UUIDv4 для внешних связей.
7. **NPC-торговцы** — бесконечные лоты на аукционе от NPC-персонажей.

---

## 🏗️ Принципы архитектуры БД

- Главная таблица `game_events` хранит всю историю игрового мира
- Все записи создаются **только после** регистрации события в `game_events`
- События бывают системные (не видны в UI) и публичные
- Все связи между таблицами — по **UUID** (или type/slug), а не по `id`
- Это позволяет:
    - Чистить таблицы от ненужных данных
    - Оптимизировать структуру
    - Менять архитектуру без потери связей
    - Восстановить БД из `game_events` через новые команды

---

## 📊 Схема БД

```
users
  ↓ (1:N через user_uuid)
characters (player, npc, guild, auction, location, chest, post)
  ↓ (1:N через characters_uuid)
storages (inventory, equipment, bank, auction, special, world)
  ↓ (1:N через storage_uuid)
slots (конкретные слоты хранилищ)
  ↓ (1:N через slot_uuid)
items (чертежи/предметы) + resource (ресурсы)

item_templates (шаблоны предметов)
  ↓ (через template_slug)
recipes (рецепты)
  ↓ (1:N через recipe_slug)
formulas (формулы крафта/разбора)

slot_types (иерархия типов слотов)
storages_type (типы хранилищ)
characters_type (типы сущностей)

game_events (история всех событий)
game_journal (история перемещений)
```

---

## 📋 Описание таблиц

### 1. users — Пользователи (аккаунты)

```sql
users
├── id : BIGINT (PK, автоинкремент)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── name : VARCHAR(255)
├── email : VARCHAR(255) (UNIQUE)
├── password : VARCHAR(255)
└── timestamps
```

**Принципы:**
- Один `users` может иметь несколько `characters`
- При удалении аккаунта все связанные данные можно удалить (сборщик мусора по крону)
- При восстановлении аккаунта `id` может быть новым, но `uuid` остаётся прежним
- Все связи с другими таблицами — через `users.uuid`

**Связи:**
- `characters.user_uuid` → `users.uuid` (1:N)

**Примеры:**
| id | uuid | name | email |
|----|------|------|-------|
| 1 | 550e8400-e29b-41d4-a716-446655440000 | Вася | vasya@mail.com |
| 2 | 550e8400-e29b-41d4-a716-446655440001 | Петя | petya@mail.com |

---

### 2. characters_type — Типы игровых сущностей

```sql
characters_type
├── id : BIGINT (PK)
├── type : VARCHAR(255) (UNIQUE)
├── name : VARCHAR(255)
├── parent_type : VARCHAR(255) (nullable, FK → characters_type.type)
└── timestamps
```

**Принципы:**
- Таблица для валидации и задела на будущее
- Поддерживает иерархию через `parent_type`
- В будущем можно добавить таблицу `character_type_rules` для прав/ограничений

**Базовые типы:**

| type | name | parent_type |
|------|------|-------------|
| `player` | Персонаж | null |
| `npc` | Неигровой персонаж | null |
| `npc_merchant` | Торговец | npc |
| `npc_quest_giver` | Квестодатель | npc |
| `auction` | Аукцион | null |
| `guild` | Гильдия | null |
| `alliance` | Альянс | null |
| `location` | Локация | null |
| `chest` | Сундук | null |
| `post` | Почта | null |

---

### 3. characters — Игровые сущности

```sql
characters
├── id : BIGINT (PK)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── user_uuid : VARCHAR(36) (nullable, FK → users.uuid)
├── character_type : VARCHAR(255) (FK → characters_type.type)
├── name : VARCHAR(255)
├── active : BOOLEAN (default: true)
└── timestamps
```

**Принципы:**
- Универсальная таблица для всех игровых сущностей
- `user_uuid` заполнен только для `type = player`
- Для NPC, гильдий, локаций `user_uuid = null`
- `active = false` означает деактивацию (не удаление)
- Дополнительные данные (статы, квесты) — в отдельных таблицах

**Примеры:**
| uuid | user_uuid | character_type | name | active |
|------|-----------|----------------|------|--------|
| abc-111 | user-vasya-uuid | player | Вася Пупкин | true |
| abc-222 | null | npc | Агент Смит | true |
| abc-333 | null | auction | Городской аукцион | true |
| abc-444 | null | guild | Гильдия хороших людей | true |
| abc-555 | null | location | Мост над рекой | true |
| abc-666 | null | chest | Сундук в пещере | true |

**Связи:**
- `storages.characters_uuid` → `characters.uuid` (1:N)
- `guilds_members.head_uuid` → `characters.uuid`
- `guilds_members.member_uuid` → `characters.uuid`

---

### 4. guilds_members — Состав гильдий и альянсов

```sql
guilds_members
├── id : BIGINT (PK)
├── head_uuid : VARCHAR(36) (FK → characters.uuid)
├── member_uuid : VARCHAR(36) (FK → characters.uuid)
├── role_type : VARCHAR(255)
├── active : BOOLEAN (default: true)
└── timestamps
```

**Принципы:**
- `head_uuid` — UUID гильдии или альянса (не лидера!)
- `member_uuid` — UUID участника (игрок, NPC или другая гильдия для альянса)
- Лидер определяется через `role_type = master`
- История вступления/исключения — в `game_events`
- При роспуске гильдии `characters.active = false`, записи можно деактивировать

**Роли:**
- `master` — лидер
- `officer` — офицер
- `member` — участник
- `recruit` — новобранец
- (любые другие)

**Примеры:**
| head_uuid | member_uuid | role_type | active |
|-----------|-------------|-----------|--------|
| guild-uuid-1 | player-vasya-uuid | master | true |
| guild-uuid-1 | player-petya-uuid | officer | true |
| alliance-uuid-1 | guild-uuid-1 | member | true |

---

### 5. storages_type — Типы хранилищ

```sql
storages_type
├── id : BIGINT (PK)
├── type : VARCHAR(255) (UNIQUE)
├── name : VARCHAR(255)
├── allowed_types : JSON (nullable)
├── metadata : JSON (nullable)
└── timestamps
```

**Принципы:**
- Описывает структуру хранилища (сколько слотов, каких типов)
- `allowed_types` — JSON со списком слотов для создания
- Если `allowed_types = null`, то безлимитное количество обычных слотов

**Структура `allowed_types`:**
```json
{
  "slots": [
    {"slot_type": "material", "count": 20},
    {"slot_type": "equipment_head", "count": 1},
    {"slot_type": null, "count": 36}
  ]
}
```

**Базовые типы:**

| type | name | allowed_types |
|------|------|---------------|
| `inventory` | Инвентарь | `{"slots": [{"slot_type": null, "count": 36}]}` |
| `equipment` | Экипировка | `{"slots": [{"slot_type": "equipment_head", "count": 1}, ...]}` |
| `bank` | Банк | `{"slots": [{"slot_type": null, "count": 100}]}` |
| `auction` | Аукцион | `null` (безлимит) |
| `special` | Специальное | зависит от конкретного хранилища |
| `world` | Мир | `null` (безлимит) |
| `mail` | Почта | `{"slots": [{"slot_type": null, "count": 100}]}` |

---

### 6. storages — Хранилища

```sql
storages
├── id : BIGINT (PK)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── characters_uuid : VARCHAR(36) (FK → characters.uuid)
├── storage_type : VARCHAR(255) (FK → storages_type.type)
├── name : VARCHAR(255)
├── active : BOOLEAN (default: true)
└── timestamps
```

**Принципы:**
- Каждое хранилище принадлежит конкретному `characters`
- Тип хранилища определяет его структуру (через `storages_type`)
- Иерархия: хранилище может содержать другие хранилища (сумка в инвентаре)
- `active = false` — хранилище деактивировано (например, потерянная сумка)

**Примеры:**
| uuid | characters_uuid | storage_type | name |
|------|-----------------|--------------|------|
| stor-111 | player-vasya | inventory | Основной инвентарь |
| stor-222 | player-vasya | equipment | Экипировка |
| stor-333 | player-vasya | bank | Банк |
| stor-444 | player-vasya | special | Сумка руды |
| stor-555 | auction-city | auction | Лот #42 |
| stor-666 | system | world | Мир |

**Связи:**
- `slots.storage_uuid` → `storages.uuid` (1:N)
- `temporary_slots.storage_uuid` → `storages.uuid` (1:N)

---

### 7. slot_types — Типы слотов (иерархия)

```sql
slot_types
├── id : BIGINT (PK)
├── type : VARCHAR(50) (UNIQUE)
├── parent_type : VARCHAR(50) (nullable, FK → slot_types.type)
├── name : VARCHAR(255)
├── description : TEXT (nullable)
└── timestamps
```

**Принципы:**
- Иерархическая структура типов слотов
- Предмет с `slot_type = equipment_head` может быть помещён в слот с `slot_type = equipment_head` или `slot_type = null`
- Наследование: если слот принимает `equipment`, то принимает и все подтипы

**Иерархия:**
```
null (любой тип)
├── material
│   ├── ore
│   ├── wood
│   └── ingot
├── equipment
│   ├── equipment_head
│   ├── equipment_chest
│   ├── equipment_legs
│   ├── equipment_weapon
│   ├── equipment_offhand
│   ├── equipment_ring
│   └── equipment_amulet
├── blueprint
└── bag
```

**Примеры:**
| type | parent_type | name |
|------|-------------|------|
| `material` | null | Материал |
| `ore` | material | Руда |
| `wood` | material | Дерево |
| `equipment` | null | Экипировка |
| `equipment_head` | equipment | Головной убор |
| `equipment_weapon` | equipment | Оружие |
| `blueprint` | null | Чертёж |
| `bag` | null | Сумка |

---

### 8. slots — Слоты хранилищ

```sql
slots
├── id : BIGINT (PK)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── storage_uuid : VARCHAR(36) (FK → storages.uuid)
├── slot_type : VARCHAR(50) (nullable, FK → slot_types.type)
└── timestamps
```

**Принципы:**
- Слот привязан к предмету, а не наоборот
- `slot_type = null` — принимает любой предмет
- `slot_type = equipment_head` — принимает только головные уборы
- Занятость слота определяется по `items.slot_uuid` (нет отдельного поля)
- Количество слотов фиксировано при создании хранилища

**Примеры:**
| uuid | storage_uuid | slot_type |
|------|--------------|-----------|
| slot-001 | stor-inventory | null |
| slot-002 | stor-inventory | null |
| slot-100 | stor-equipment | equipment_head |
| slot-101 | stor-equipment | equipment_chest |
| slot-200 | stor-ore-bag | ore |

---

### 9. temporary_slots — Временные слоты

```sql
temporary_slots
├── id : BIGINT (PK)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── storage_uuid : VARCHAR(36) (FK → storages.uuid)
├── character_uuid : VARCHAR(36) (FK → characters.uuid)
├── slot_index : TINYINT (nullable, 0–19 для trade)
├── active : BOOLEAN (default: true)
├── timestamps
├── timestamps_end : TIMESTAMP (nullable)
```

**Принципы:**
- Создаются динамически для аукционов, обменов, дропа
- `character_uuid` — от кого предмет (для обмена)
- `timestamps_end` — автоматическое истечение (возврат предмета)
- Предмет физически в `slot_uuid`, но отображается в `temporary_slot_uuid`
- При "Применить" — `slot_uuid` меняется на слот нового владельца, `temporary_slot_uuid` очищается

**Сценарий: Аукцион**
```
Шаг 1: Игрок A выставляет предмет
  items.slot_uuid = slot_inventory_A_1 (физически в инвентаре)
  items.temporary_slot_uuid = temporary_slot_auction_1
  UI: предмет виден на аукционе, НЕ виден в инвентаре

Шаг 2: Игрок A нажимает "Применить"
  items.slot_uuid = slot_auction_1 (физически на аукционе)
  items.temporary_slot_uuid = null
  auction_lots: создаётся запись

Шаг 3: Игрок B покупает
  items.slot_uuid = slot_inventory_B_1 (физически у покупателя)
  items.temporary_slot_uuid = null
  auction_lots.status = sold
```

**Если игрок закрыл игру до "Применить":**
- Предмет остаётся в инвентаре
- `temporary_slot_uuid` обнуляется по таймауту

**Если инвентарь покупателя полон:**
- Покупка блокируется
- В будущем — отправка на почту

---

### 10. item_templates — Шаблоны предметов

```sql
item_templates
├── id : BIGINT (PK)
├── slug : VARCHAR(50) (UNIQUE)
├── name : VARCHAR(255)
├── type : VARCHAR(20)  -- material|equipment|blueprint
├── icon : VARCHAR(50)
├── is_stackable : BOOLEAN (default: false)
├── max_stack : INT (nullable)
├── description : TEXT (nullable)
├── base_stats : JSON (nullable)
├── slot_type : VARCHAR(50) (nullable, FK → slot_types.type)
└── timestamps
```

**Принципы:**
- Шаблон описывает базовые характеристики предмета
- Конкретный экземпляр — в `items` с индивидуальными статами
- `base_stats` — диапазоны статов (например, damage: {min: 5, max: 8})
- `slot_type` — в какой тип слота можно положить

**Примеры:**

| slug | name | type | icon | base_stats | slot_type |
|------|------|------|------|------------|-----------|
| `wood` | Дерево | material | 🪵 | null | wood |
| `iron_ore` | Железная руда | material | ⛏️ | null | ore |
| `wooden_sword` | Деревянный меч | equipment | 🗡️ | `{"damage": {"min": 5, "max": 8}}` | equipment_weapon |
| `iron_sword` | Железный меч | equipment | ⚔️ | `{"damage": {"min": 15, "max": 20}}` | equipment_weapon |
| `recipe_wooden_sword` | Чертёж: Деревянный меч | blueprint | 📜 | null | blueprint |

---

### 11. recipes — Рецепты

```sql
recipes
├── id : BIGINT (PK)
├── slug : VARCHAR(50) (UNIQUE)
├── type : VARCHAR(50)  -- blueprint|resource
├── name : VARCHAR(255)
├── description : TEXT (nullable)
└── timestamps
```

**Принципы:**
- `type: blueprint` — рецепт создаёт запись в `items` (чертёж или предмет)
- `type: resource` — рецепт создаёт запись в `resource`
- Рецепт — это шаблон, конкретные экземпляры — в `items` и `resource`
- Формулы крафта/разбора — в отдельной таблице `formulas`

**Примеры:**

| slug | type | name |
|------|------|------|
| `craft_wooden_sword` | blueprint | Изготовление деревянного меча |
| `craft_wooden_plank` | resource | Распил дерева |
| `craft_iron_ingot` | resource | Плавка железного слитка |
| `starting_sword` | blueprint | Стартовый меч |

---

### 12. formulas — Формулы крафта и разбора

```sql
formulas
├── id : BIGINT (PK)
├── recipe_slug : VARCHAR(50) (FK → recipes.slug)
├── type : VARCHAR(50)  -- craft|disassemble
├── priority : INT (default: 100)
├── chance : INT (default: 100)  -- 0-100
├── conditions : JSON (nullable)
├── formula : JSON
├── is_active : BOOLEAN (default: true)
├── description : VARCHAR(255)
└── timestamps
```

**Принципы:**
- Один рецепт может иметь несколько формул (обычная + пасхальная)
- `type: craft` — формула для создания
- `type: disassemble` — формула для разбора
- `priority` — порядок проверки (меньше = раньше)
- `chance` — шанс срабатывания (100 = всегда)
- `conditions` — дополнительные условия (мастерство, время суток)
- `formula` — JSON с типами ресурсов и количеством

**Структура `formula`:**

**Для ресурса (craft):**
```json
{"iron_ore": 10}  -- нужно 10 железной руды
```

**Для ресурса (disassemble):**
```json
{"iron_ingot": 2}  -- получаем 2 железных слитка
```

**Для предмета (craft):**
```json
{"wood": 5}  -- нужно 5 дерева (любого типа: oak, aspen, etc.)
```

**Для предмета (disassemble):**
```json
{"wood": 2}  -- получаем 2 дерева (конкретный тип из materials_used)
```

**Примеры:**

| recipe_slug | type | priority | chance | formula | description |
|-------------|------|----------|--------|---------|-------------|
| `craft_iron_sword` | craft | 100 | 100 | `{"wood": 1, "iron_ingot": 2}` | Обычная ковка |
| `craft_iron_sword` | disassemble | 1 | 10 | `{"wood": 3, "iron_ingot": 4}` | Пасхальный разбор |
| `craft_iron_sword` | disassemble | 100 | 100 | `{"wood": 1, "iron_ingot": 1}` | Обычный разбор |

---

### 13. items — Чертежи и предметы

```sql
items
├── id : BIGINT (PK, автоинкремент)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── slot_uuid : VARCHAR(36) (FK → slots.uuid)
├── temporary_slot_uuid : VARCHAR(36) (nullable, FK → temporary_slots.uuid)
├── recipe_slug : VARCHAR(50) (FK → recipes.slug)
├── template_slug : VARCHAR(50) (FK → item_templates.slug)
├── custom_name : VARCHAR(255) (nullable)
├── stage : VARCHAR(50)  -- blueprint|item
├── slot_type : VARCHAR(50) (FK → slot_types.type)
├── durability : INT (default: 100)
├── materials_used : JSON (nullable)
├── stats : JSON (nullable)
└── timestamps
```

**Принципы:**
- **Вечный ID** — предметы никогда не удаляются, только меняют хранилище
- `uuid` — для внешних ссылок (Event Sourcing, API)
- `slot_uuid` — текущий слот предмета
- `temporary_slot_uuid` — временный слот (аукцион, обмен)
- `stage: blueprint` — чертёж (можно крафтить)
- `stage: item` — готовый предмет (можно использовать/разобрать)
- `recipe_slug` — по какому рецепту создан
- `template_slug` — шаблон предмета (для базовых характеристик)
- `materials_used` — конкретные материалы, использованные при крафте
- `stats` — индивидуальные статы этого экземпляра

**Примеры:**

| uuid | stage | template_slug | custom_name | materials_used |
|------|-------|---------------|-------------|----------------|
| item-111 | blueprint | recipe_wooden_sword | null | null |
| item-222 | item | wooden_sword | "Осиное жало" | `{"wood": 5, "wood_type": "aspen"}` |
| item-333 | item | iron_sword | null | `{"wood": 1, "iron_ingot": 2, "wood_type": "oak", "iron_type": "steel"}` |

**Трансформация blueprint → item:**
```
1. items.id = 1234, stage = blueprint
2. Игрок крафтит: чертёж + ресурсы
3. items.id = 1234 (не меняется!), stage = item
4. items.materials_used = {"wood": 5}
5. items.stats = {"damage": 7}  -- конкретные статы
```

---

### 14. resource — Ресурсы

```sql
resource
├── id : BIGINT (PK, автоинкремент)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── slot_uuid : VARCHAR(36) (FK → slots.uuid)
├── temporary_slot_uuid : VARCHAR(36) (nullable, FK → temporary_slots.uuid)
├── recipe_slug : VARCHAR(50) (FK → recipes.slug)
├── template_slug : VARCHAR(50) (FK → item_templates.slug)
├── slot_type : VARCHAR(50) (FK → slot_types.type)
├── max_stack : INT (nullable)
├── quantity : INT
└── timestamps
```

**Принципы:**
- Ресурсы — это стакуемые сущности (дерево, руда, золото)
- `uuid` — для Event Sourcing (ссылки в событиях)
- `id` не вечный — ресурсы могут создаваться и исчезать при стакании
- `recipe_slug` — по какому рецепту создан
- `template_slug` — шаблон ресурса
- `slot_type` и `max_stack` — денормализация для удобства (есть в `recipes`)

**Примеры:**

| uuid | template_slug | slot_type | max_stack | quantity |
|------|---------------|-----------|-----------|----------|
| res-111 | wood | wood | 200 | 50 |
| res-222 | iron_ore | ore | 100 | 30 |
| res-333 | gold | gold | null | 1500 |

---

### 15. game_events — История событий (SOURCE OF TRUTH)

```sql
game_events
├── id : BIGINT (PK)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── event_type : VARCHAR(50)
├── aggregate_type : VARCHAR(50)
├── aggregate_uuid : VARCHAR(36)
├── actor_uuid : VARCHAR(36) (nullable, FK → characters.uuid)
├── occurred_at : TIMESTAMP
├── payload : JSON
├── metadata : JSON
├── correlation_uuid : VARCHAR(36)
├── causation_uuid : VARCHAR(36) (nullable)
├── version : INT
└── timestamps
```

**Принципы:**
- Главная таблица — источник правды
- Все изменения состояния фиксируются здесь
- События бывают системные (не видны в UI) и публичные
- Можно пересчитать любое состояние из событий

**Типы событий:**
- `user.registered` — регистрация
- `character.created` — создание персонажа
- `resource.received` — получение ресурса
- `resource.spent` — трата ресурса
- `resource.transferred` — передача ресурса
- `item.crafted` — создание предмета
- `item.disassembled` — разборка предмета
- `item.transferred` — передача предмета
- `item.equipped` — надевание экипировки
- `item.dropped` — выбрасывание предмета
- `auction.listed` — выставление лота
- `auction.purchased` — покупка лота
- `auction.cancelled` — отмена лота
- `trade.created` — создание обмена
- `trade.completed` — завершение обмена
- `trade.cancelled` — отмена обмена
- `presence.changed` — игрок вошёл в игру (после офлайн-паузы)

**Публичные vs системные (UI «Чат» → вкладка «Журнал»):**

Публичные типы задаются в `config/game_events.php` (`public_types`). Во вкладке «Журнал» показываются только публичные события **текущего персонажа** (до 20 последних): свои крафт, аукцион (только своя сторона сделки), вход в игру, завершённые обмены. События других игроков в журнал не попадают. Остальные типы — системные: используются для персонального polling, обновления инвентаря и внутренней логики.

| Публичные (в персональном журнале) | Системные (примеры) |
|-----------|---------------------|
| `user.registered`, `auction.listed`, `auction.purchased`, `auction.sold`, `trade.completed`, `item.crafted`, `item.disassembled`, `presence.changed` | `trade.created`, `item.transferred`, `resource.received`, `auction.cancelled` |

API: `GET /api/events/{uuid}/latest?visibility=public&limit=20` — персональный журнал персонажа `{uuid}`.

**Фаза 2 — верстак:** целевой UI с типовыми слотами материалов по `craft_formula`, центральным слотом и preview stats из `materials_used` (см. §12–13).

**Пример события:**
```json
{
  "uuid": "evt-111",
  "event_type": "item.transferred",
  "aggregate_type": "item",
  "aggregate_uuid": "item-222",
  "actor_uuid": "player-vasya",
  "payload": {
    "from_slot_uuid": "slot-inventory-A",
    "to_slot_uuid": "slot-inventory-B",
    "reason": "trade"
  },
  "metadata": {"ip": "192.168.1.1"},
  "correlation_uuid": "corr-111"
}
```

---

### 16. game_journal — История перемещений

```sql
game_journal
├── id : BIGINT (PK)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── type : VARCHAR(50)  -- trade|auction|drop|quest_reward|mail|craft|disassemble
├── item_uuid : VARCHAR(36) (nullable, FK → items.uuid)
├── resource_uuid : VARCHAR(36) (nullable, FK → resource.uuid)
├── from_character_uuid : VARCHAR(36) (nullable, FK → characters.uuid)
├── to_character_uuid : VARCHAR(36) (nullable, FK → characters.uuid)
├── from_slot_uuid : VARCHAR(36) (nullable, FK → slots.uuid)
├── to_slot_uuid : VARCHAR(36) (nullable, FK → slots.uuid)
├── quantity : INT
├── occurred_at : TIMESTAMP
├── metadata : JSON
└── timestamps
```

**Принципы:**
- Упрощённая история перемещений предметов и ресурсов
- Используется для UI (история обменов, покупок и т.д.)
- Полная история — в `game_events`

**Примеры:**

| type | item_uuid | from_character | to_character | quantity |
|------|-----------|----------------|--------------|----------|
| trade | item-222 | player-vasya | player-petya | 1 |
| auction | item-333 | player-vasya | player-petya | 1 |
| craft | item-444 | null | player-vasya | 1 |
| drop | item-555 | player-vasya | null | 1 |

---

### 17. auction_lots — Лоты аукциона

```sql
auction_lots
├── id : BIGINT (PK)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── storage_uuid : VARCHAR(36) (FK → storages.uuid)
├── seller_uuid : VARCHAR(36) (FK → characters.uuid)
├── template_slug : VARCHAR(50) (FK → item_templates.slug)
├── quantity : INT
├── price : INT
├── commission_percent : INT (default: 5)
├── status : VARCHAR(20)  -- active|sold|cancelled|expired
├── is_infinite : BOOLEAN (default: false)
├── buyer_uuid : VARCHAR(36) (nullable, FK → characters.uuid)
├── sold_at : TIMESTAMP (nullable)
└── timestamps
```

**Принципы:**
- `storage_uuid` — хранилище аукциона (слот лота)
- `seller_uuid` — продавец (кому пойдут деньги)
- `is_infinite = true` — лот от NPC (бесконечный)
- При покупке создаётся запись в `game_journal`

---

### 18. trade_offers — Обмены

```sql
trade_offers
├── id : BIGINT (PK)
├── uuid : VARCHAR(36) (UNIQUE, UUIDv4)
├── initiator_uuid : VARCHAR(36) (FK → characters.uuid)
├── partner_uuid : VARCHAR(36) (FK → characters.uuid)
├── status : VARCHAR(20)  -- pending|accepted|completed|cancelled
├── initiator_accepted : BOOLEAN (default: false)
├── partner_accepted : BOOLEAN (default: false)
└── timestamps
```

---

### 19. trade_items — Предметы в обмене

```sql
trade_items
├── id : BIGINT (PK)
├── trade_uuid : VARCHAR(36) (FK → trade_offers.uuid)
├── character_uuid : VARCHAR(36) (FK → characters.uuid)
├── item_uuid : VARCHAR(36) (nullable, FK → items.uuid)
├── resource_uuid : VARCHAR(36) (nullable, FK → resource.uuid)
├── quantity : INT
└── timestamps
```

**Принципы:**
- Временное состояние обмена
- Предметы физически во временных слотах (`temporary_slot_uuid`)
- При завершении обмена — перемещение в постоянные слоты

---

## 🎬 Ключевые сценарии

### Сценарий 1: Регистрация и создание персонажа

```
1. Регистрация → создание users (uuid: user-111)
2. Создание персонажа → создание characters (uuid: char-vasya, user_uuid: user-111)
3. Создание хранилищ:
   - storages (uuid: stor-inv, type: inventory)
   - storages (uuid: stor-equip, type: equipment)
   - storages (uuid: stor-bank, type: bank)
4. Создание слотов согласно storages_type.allowed_types
5. Начальное золото → создание resource (template: gold, quantity: 100)
6. game_events: user.registered, character.created, resource.received
```

### Сценарий 2: Крафт предмета

```
1. Игрок имеет чертёж (items.stage: blueprint, recipe_slug: craft_wooden_sword)
2. Игрок имеет ресурсы (wood x5)
3. Игрок нажимает "Создать"
4. Проверка рецепта и ресурсов
5. Вычитание ресурсов (resource.quantity -= 5)
6. Трансформация чертежа:
   - items.stage: blueprint → item
   - items.materials_used: {"wood": 5}
   - items.stats: {"damage": 7}
7. game_events: resource.spent, item.crafted
8. game_journal: craft
```

### Сценарий 3: Выставление на аукцион

```
1. Игрок имеет предмет (items.slot_uuid: slot-inv-1)
2. Игрок нажимает "Выставить"
3. Создание temporary_slot (uuid: temp-auct-1)
4. items.temporary_slot_uuid: temp-auct-1
5. UI: предмет виден на аукционе, НЕ виден в инвентаре
6. Игрок нажимает "Применить"
7. items.slot_uuid: slot-auct-1 (слот аукциона)
8. items.temporary_slot_uuid: null
9. Создание auction_lots
10. game_events: item.transferred, auction.listed
```

### Сценарий 4: Покупка с аукциона

```
1. Покупатель нажимает "Купить"
2. Проверка золота и свободных слотов
3. Вычитание золота у покупателя
4. Прибавление золота продавцу (с комиссией)
5. items.slot_uuid: slot-inv-buyer (слот покупателя)
6. auction_lots.status: sold
7. game_events: resource.spent, resource.received, item.transferred, auction.purchased
8. game_journal: auction
```

### Сценарий 5: Обмен между игроками

```
1. Игрок A создаёт обмен с игроком B
2. trade_offers: status = pending
3. Игрок A кладёт предмет → items.temporary_slot_uuid: temp-trade-1
4. Игрок B кладёт ресурс → resource.temporary_slot_uuid: temp-trade-2
5. Оба подтверждают → trade_offers.initiator_accepted = true, partner_accepted = true
6. Завершение обмена:
   - items.slot_uuid: slot-inv-B
   - items.temporary_slot_uuid: null
   - resource.slot_uuid: slot-inv-A
   - resource.temporary_slot_uuid: null
7. trade_offers.status: completed
8. game_events: item.transferred, resource.transferred, trade.completed
9. game_journal: trade
```

### Сценарий 6: Разборка предмета

```
1. Игрок имеет предмет (items.stage: item)
2. Игрок нажимает "Разобрать"
3. Выбор формулы разбора (по priority и chance)
4. Трансформация предмета:
   - items.stage: item → blueprint
   - items.materials_used: null
   - items.stats: null
5. Создание ресурсов по формуле
6. game_events: item.disassembled, resource.received
7. game_journal: disassemble
```

---

## 🔗 Связи между таблицами

```
users (1) → (N) characters (через user_uuid)
characters (1) → (N) storages (через characters_uuid)
storages (1) → (N) slots (через storage_uuid)
storages (1) → (N) temporary_slots (через storage_uuid)
slots (1) → (N) items (через slot_uuid)
slots (1) → (N) resource (через slot_uuid)
temporary_slots (1) → (N) items (через temporary_slot_uuid)
temporary_slots (1) → (N) resource (через temporary_slot_uuid)

item_templates (1) → (N) items (через template_slug)
item_templates (1) → (N) resource (через template_slug)
recipes (1) → (N) items (через recipe_slug)
recipes (1) → (N) resource (через recipe_slug)
recipes (1) → (N) formulas (через recipe_slug)

slot_types (1) → (N) slots (через slot_type)
slot_types (1) → (N) items (через slot_type)
slot_types (1) → (N) resource (через slot_type)
slot_types (1) → (N) storages_type.allowed_types

storages_type (1) → (N) storages (через storage_type)
characters_type (1) → (N) characters (через character_type)

characters (1) → (N) game_events (через actor_uuid)
items (1) → (N) game_events (через aggregate_uuid)
resource (1) → (N) game_events (через aggregate_uuid)
```

---

## 🗄️ Унифицированная система хранилищ (v3.1)

### StorageProvisioningService

Централизованное создание хранилищ и слотов по шаблону `storages_type.allowed_types`:

| Тип | Слотов | Сетка UI |
|-----|--------|----------|
| `inventory` | **1 gold** (hidden) + **36** grid (4×9) | cols=4 |
| `equipment` | 8 typed | — |
| `bank` | 100 | — |
| `trade` | **20 temporary_slots** на персонажа | cols=5 (5×4) |

- `provisionDefaults(Character)` — при регистрации: inventory, equipment, bank
- `grantStorage(Character, type)` — идемпотентная выдача хранилища
- `ensureTradeStorage(Character)` — **lazy**: при первом обмене создаёт `storage_type=trade` и 20 `temporary_slots` с `slot_index` 0–19; событие `storage.trade_granted`

### Спец-слоты (SpecialSlotService)

Шаблон `storages_type.allowed_types.slots[]` поддерживает флаги:

| Флаг | Назначение |
|------|------------|
| `hidden` | Не показывать в сетке (золото — chip в шапке) |
| `priority_fill` | `addResource` сначала заполняет спец-слоты |
| `auto_reclaim` | Возврат из trade → merge в спец-слот |

Текущий инвентарь: `gold×1` (hidden, priority_fill, auto_reclaim) + `null×36` (сетка).

- `GET /api/storage` → `grid_slots[]`, `special_slots[]`, `gold` (сумма в gold-слоте)
- `relocateGoldToSpecialSlot` при загрузке layout сливает золото из сетки в спец-слот

### StorageMoveService

Единая точка перемещения между ячейками `Slot` (regular) и `TemporarySlot` (temporary):

```
POST /api/storage/{characterUuid}/move
{ from_slot_uuid, to_slot_uuid, quantity? }
```

| Направление | Поведение |
|-------------|-----------|
| regular → regular | move / merge стаков / swap |
| regular → temporary | overlay: `temporary_slot_uuid` ( `slot_uuid` не меняется ) |
| temporary → regular | снятие overlay + опционально смена `slot_uuid` |
| temporary → temporary | swap overlay внутри пула персонажа |

### Обмен: overlay-модель

- Пул из 20 `temporary_slots` закреплён за персонажем навсегда (lazy при первом обмене)
- Предмет/ресурс физически остаётся в `slot_uuid` инвентаря
- UI обмена читает occupant по `temporary_slot_uuid`
- При завершении: `slot_uuid` → инвентарь партнёра, `temporary_slot_uuid = null`
- При отмене: только `temporary_slot_uuid = null`

### StorageLayoutService + API

- `GET /api/storage/{uuid}?include=inventory,trade,workbench` — сетки слотов с occupants
- `formatTradeSlotGrids()` — `my_trade_slots[20]` + `partner_trade_slots[20]` для TradeController
- `formatWorkbenchSlotGrid()` — 9 overlay-слотов верстака (1 центр + 8 материалов)
- **`locked` в layout**: в инвентаре `true` при overlay; на overlay-назначении (верстак/обмен) — `false` (см. [`docs/STORAGE_UI.md`](docs/STORAGE_UI.md))

### Верстак: overlay-модель

- Аналогично обмену: `slot_uuid` остаётся в инвентаре, `temporary_slot_uuid` → пул верстака
- 9 `temporary_slots`: index 0 = чертёж/предмет, 1–8 = материалы
- Закрытие окна → `POST /api/storage/{uuid}/clear-workbench`
- Крафт читает материалы с overlay (`WorkbenchService`)

### UI: ItemDispatcher (клиент)

Подробно: [`docs/STORAGE_UI.md`](docs/STORAGE_UI.md)

- **Dblclick** из инвентаря: экипировка → открыть `character` + equip; иначе — только если открыто ровно одно sink-окно (`workbench` / `trade` / `auction`)
- **ПКМ** «Создать/Разобрать/Преобразовать» → открыть верстак + placement
- **Drag** — всегда `POST /storage/.../move`, без dispatch

---

## 📝 История изменений

### v3.3 (30 июня 2026) — Верстак overlay + ItemDispatcher
- Верстак на `temporary_slot_uuid` (как обмен), `WorkbenchService`, `clear-workbench` при закрытии окна
- `materials_used` с блоком `crafter`; тултип «Создал: …»
- Backend квестов: `quests`, `QuestService`, API accept/turn-in, `content/quests.json`
- **`locked` по контексту**: заблокирован в инвентаре, активен на overlay верстака/обмена
- **`ItemDispatcher`**: dblclick → equip ИЛИ единственное sink-окно; ПКМ → верстак
- Документация: [`docs/STORAGE_UI.md`](docs/STORAGE_UI.md)

### v3.2 (28 июня 2026) — Спец-слоты
- `SpecialSlotService`: priority_fill, auto_reclaim, hidden
- Золото вынесено из сетки в gold-слот; шапка «💰 N» из API `gold`
- Drag: обычный = весь стак; Shift+drag = модалка разделения

### v3.1 (29 июня 2026) — Унифицированные хранилища
- Инвентарь: 50 → **36 слотов** (4×9)
- `StorageProvisioningService`, `StorageMoveService`, `StorageLayoutService`
- Обмен переведён на persistent `temporary_slots` (overlay), убран system trade storage
- Глобальный drag: `POST /api/storage/{uuid}/move`
- `temporary_slots.slot_index` для стабильного порядка в UI

### v3.0 (27 июня 2026) — Финальная версия
- Добавлены UUIDv4 для всех сущностей
- Добавлена таблица `item_templates`
- Добавлена таблица `slot_types` с иерархией
- Добавлена таблица `game_journal` для истории перемещений
- Уточнена структура `formulas.formula`
- Уточнена структура `storages_type.allowed_types`
- Описан полный цикл `temporary_slots`
- Добавлены ключевые сценарии

### v2.0 (24 июня 2026)
- Зафиксирована целевая архитектура
- Добавлена концепция хранилищ
- Добавлена система формул разбора

### v1.0 (23 июня 2026)
- Базовая архитектура
- Аукцион, обмен, крафт

---

*Документ готов к реализации. Все изменения обсуждаются и фиксируются.* 🚀
