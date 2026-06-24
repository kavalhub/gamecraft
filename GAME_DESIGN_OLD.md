# 📜 Справочник по игре "Крафт-Мир"

> Версия документа: 2.0  
> Дата: 24 июня 2026  
> Статус: Архитектура зафиксирована, идёт миграция

---

## 🎯 Общая концепция

Крафт-Мир — это браузерная MMO-игра с упором на крафт, торговлю и социальные взаимодействия. Игрок создаёт персонажа, добывает ресурсы, крафтит предметы, торгует на аукционе и обменивается с другими игроками.

### Ключевые принципы

- **Event Sourcing как источник правды** — все изменения состояния фиксируются как события в таблице `game_events`. Любое состояние (инвентарь, золото, аукцион) — это проекция, которую можно пересчитать из событий.
- **Разделение ресурсов и предметов** — ресурсы (дерево, руда, золото) и предметы (мечи, доспехи) имеют разную природу и хранятся по-разному.
- **Хранилища как универсальные контейнеры** — предметы привязаны к хранилищам, а не к игрокам напрямую. Хранилище может принадлежать игроку, гильдии, аукциону или быть системным.
- **Чертежи как "душа" предметов** — чертёж не исчезает при крафте, а становится частью предмета и возвращается при разборке.
- **Вечные ID** — предметы никогда не удаляются из БД, только меняют хранилище.
- **NPC-торговцы** — бесконечные лоты на аукционе от NPC-персонажей.

---

## 🏗️ Архитектура базы данных

### ⚠️ ВАЖНО: Текущее vs Целевое состояние

**Текущее состояние** — это то, что сейчас в БД (с миграциями, которые уже применены).  
**Целевая архитектура** — это то, к чему мы стремимся.

Раздел описывает **ЦЕЛЕВУЮ АРХИТЕКТУРУ**. Если что-то ещё не реализовано — это будет отмечено.

---

## 📊 Целевая схема БД

### 1. users — Пользователи

```sql
users
├── id : BIGINT (PK)
├── name : VARCHAR(255)
├── email : VARCHAR(255) (UNIQUE)
├── password : VARCHAR(255)
├── last_seen_at : TIMESTAMP (nullable)
└── timestamps
```

**Принцип:** Золото НЕ хранится в `users.gold`. Золото — это ресурс, хранится в `resource_balances`.

**Статус:** ✅ Реализовано

---

### 2. storages — Хранилища (НОВОЕ)

```sql
storages
├── id : BIGINT (PK)
├── owner_type : VARCHAR(20)  -- player|guild|auction|system
├── owner_id : BIGINT (nullable)  -- user_id, guild_id, null
├── type : VARCHAR(20)  -- inventory|equipment|bank|auction_lot|special|world
├── name : VARCHAR(255)
├── capacity : INT (nullable)  -- null = безлимит
├── allowed_types : JSON (nullable)  -- null = все типы
├── allowed_slots : JSON (nullable)  -- для equipment: {"head": null, "chest": null, ...}
├── metadata : JSON (nullable)
└── timestamps

INDEXES:
  - (owner_type, owner_id)
  - (type, owner_type)
```

**Принцип:** Предметы привязаны к хранилищам, а не к игрокам напрямую. Хранилище — это универсальный контейнер.

**Типы хранилищ:**

| type | owner_type | Описание | Особенности |
|------|------------|----------|-------------|
| `inventory` | player | Основной инвентарь | Безлимитный |
| `equipment` | player | Экипировка | Слоты: head, chest, weapon, ring... |
| `bank` | player | Банк игрока | С лимитом слотов |
| `auction_lot` | auction | Лот на аукционе | Временное |
| `special` | player | Специальное | Ограничения по типу (сумка для руды) |
| `world` | system | Мировое | Выброшенные предметы |
| `guild_bank` | guild | Гильдейское | Общее для гильдии |

**Статус:** ⚠️ Таблица создана, но сервисы не обновлены

---

### 3. items — Предметы (ОБНОВЛЕНО)

```sql
items
├── id : BIGINT (PK)
├── template_id : BIGINT (FK → item_templates)
├── storage_id : BIGINT (FK → storages)  -- ВМЕСТО owner_id!
├── slot : VARCHAR(20) (nullable)  -- для equipment: 'head', 'chest', 'weapon'...
├── recipe_id : BIGINT (FK → recipes, nullable)  -- если создан по рецепту
├── materials_used : JSON (nullable)  -- какие материалы использованы
├── custom_name : VARCHAR(255) (nullable)  -- кастомное название
├── quantity : INT
├── durability : INT
├── stats : JSON
└── timestamps

INDEXES:
  - (storage_id)
  - (template_id)
  - (recipe_id)
```

**Принцип:**
- Предмет привязан к `storage_id`, а не к `owner_id`
- `slot` заполняется только для экипировки в хранилище типа `equipment`
- `recipe_id` и `materials_used` заполняются при крафте
- Предметы никогда не удаляются (принцип вечного ID)

**Статус:** ⚠️ Таблица есть, но содержит старые поля (`owner_id`). Нужна миграция.

---

### 4. resource_balances — Балансы ресурсов (НОВОЕ)

```sql
resource_balances
├── id : BIGINT (PK)
├── user_id : BIGINT (FK → users)
├── template_id : BIGINT (FK → item_templates)  -- включая gold!
├── quantity : INT
└── timestamps

UNIQUE: (user_id, template_id)
```

**Принцип:**
- Ресурсы (материалы, золото) хранятся как балансы, а не как экземпляры
- Золото — это ресурс с `template_id` = ID шаблона "gold"
- Одно дерево = другое дерево, нет индивидуальности

**Статус:** ✅ Реализовано

---

### 5. item_templates — Шаблоны предметов

```sql
item_templates
├── id : BIGINT (PK)
├── slug : VARCHAR(50) (UNIQUE)
├── name : VARCHAR(255)
├── type : VARCHAR(20)  -- material|equipment|blueprint
├── icon : VARCHAR(50)
├── is_stackable : BOOLEAN
├── max_stack : INT
├── description : TEXT (nullable)
├── stats : JSON (nullable)  -- для equipment: damage, defense...
└── timestamps
```

**Принцип:**
- `type: material` — ресурсы (дерево, руда, золото)
- `type: equipment` — экипировка (мечи, доспехи)
- `type: blueprint` — чертежи (особый тип предметов)

**Статус:** ✅ Реализовано

---

### 6. recipes — Рецепты

```sql
recipes
├── id : BIGINT (PK)
├── slug : VARCHAR(50) (UNIQUE)
├── name : VARCHAR(255)
├── description : TEXT (nullable)
├── result_template_id : BIGINT (FK → item_templates)
├── result_quantity : INT
└── timestamps
```

**Принцип:** Рецепт описывает только сборку. Формулы разбора — в отдельной таблице `disassemble_formulas`.

**Статус:** ✅ Реализовано

---

### 7. recipe_components — Компоненты рецептов

```sql
recipe_components
├── id : BIGINT (PK)
├── recipe_id : BIGINT (FK → recipes)
├── template_id : BIGINT (FK → item_templates)
├── quantity : INT
└── timestamps

INDEXES:
  - (recipe_id)
```

**Статус:** ✅ Реализовано

---

### 8. disassemble_formulas — Формулы разбора (НОВОЕ)

```sql
disassemble_formulas
├── id : BIGINT (PK)
├── recipe_id : BIGINT (FK → recipes)
├── name : VARCHAR(255)  -- "Обычный разбор", "Пасхальный разбор"
├── priority : INT  -- 1 = первый проверяется
├── chance : INT  -- 0-100, где 100 = всегда
├── conditions : JSON (nullable)  -- {"min_craftsmanship": 50}
├── formula : JSON  -- {"wooden_plank": 2, "iron_ingot": 1}
├── is_active : BOOLEAN
└── timestamps

INDEXES:
  - (recipe_id, is_active, priority)
```

**Принцип:**
- Один рецепт может иметь несколько формул разбора
- Формулы проверяются по `priority` (ASC)
- Каждая формула имеет `chance` (шанс срабатывания)
- `conditions` — дополнительные условия (мастерство, время суток и т.д.)
- Формула разбора может отличаться от сборки (потеря ресурсов или пасхалки)

**Логика выбора формулы:**
```
1. Берём все активные формулы для рецепта
2. Сортируем по priority (ASC)
3. Для каждой формулы:
   a. Проверяем conditions
   b. Проверяем chance (random 1-100 <= chance)
   c. Если обе проверки прошли — используем эту формулу
4. Если ни одна не сработала — используем формулу с chance=100 (fallback)
```

**Статус:** ✅ Таблица создана, данные импортированы

---

### 9. auction_lots — Лоты аукциона (ОБНОВЛЕНО)

```sql
auction_lots
├── id : BIGINT (PK)
├── storage_id : BIGINT (FK → storages)  -- ВМЕСТО seller_id!
├── template_id : BIGINT (FK → item_templates)
├── quantity : INT
├── price : INT
├── commission_percent : INT
├── status : VARCHAR(20)  -- active|sold|cancelled
├── is_infinite : BOOLEAN
└── timestamps

INDEXES:
  - (storage_id)
  - (status, is_infinite)
```

**Принцип:**
- Лот привязан к `storage_id` типа `auction_lot`, а не к `seller_id`
- Бесконечные лоты (`is_infinite = true`) — от NPC-торговцев
- Обычные лоты — от игроков

**Статус:** ⚠️ Таблица есть, но содержит старые поля (`seller_id`, `buyer_id`). Нужна миграция.

---

### 10. auction_history — История аукциона

```sql
auction_history
├── id : BIGINT (PK)
├── lot_id : BIGINT (FK → auction_lots)
├── seller_id : BIGINT (nullable)  -- может быть null для бесконечных лотов
├── buyer_id : BIGINT (nullable)
├── template_id : BIGINT (FK → item_templates)
├── quantity : INT
├── price : INT
├── commission : INT
├── seller_received : INT
├── action : VARCHAR(20)  -- sold|cancelled
├── occurred_at : TIMESTAMP
└── timestamps
```

**Статус:** ✅ Реализовано

---

### 11. trade_offers — Обмены

```sql
trade_offers
├── id : BIGINT (PK)
├── initiator_id : BIGINT (FK → users)
├── partner_id : BIGINT (FK → users)
├── status : VARCHAR(20)  -- pending|accepted|completed|cancelled
├── initiator_accepted : BOOLEAN
├── partner_accepted : BOOLEAN
└── timestamps
```

**Статус:** ✅ Реализовано

---

### 12. trade_items — Предметы в обмене

```sql
trade_items
├── id : BIGINT (PK)
├── trade_id : BIGINT (FK → trade_offers)
├── user_id : BIGINT (FK → users)
├── template_id : BIGINT (FK → item_templates)
├── quantity : INT
└── timestamps

INDEXES:
  - (trade_id)
```

**Статус:** ✅ Реализовано

---

### 13. game_events — События (SOURCE OF TRUTH)

```sql
game_events
├── id : BIGINT (PK)
├── uuid : VARCHAR(36) (UNIQUE)
├── event_type : VARCHAR(50)
├── aggregate_type : VARCHAR(50)
├── aggregate_id : BIGINT
├── actor_id : BIGINT (nullable)
├── occurred_at : TIMESTAMP
├── payload : JSON
├── metadata : JSON
├── correlation_id : VARCHAR(36)
├── causation_id : VARCHAR(36) (nullable)
├── version : INT
└── timestamps

INDEXES:
  - (event_type)
  - (aggregate_type, aggregate_id)
  - (actor_id)
  - (occurred_at)
```

**Принцип:** Это единственный источник правды. Все остальные таблицы — проекции.

**Статус:** ✅ Реализовано

---

## 🔄 План миграции к целевой архитектуре

### Приоритет 1: Миграция items к storage_id

**Что нужно сделать:**
1. Удалить старые поля из `items`: `owner_id`, `owner_type`, `auction_lot_id`
2. Убедиться, что все предметы имеют `storage_id`
3. Обновить все сервисы для работы с `storage_id` вместо `owner_id`

**Затронутые сервисы:**
- `InventoryService`
- `AuctionService`
- `TradeService`
- `CraftingService`

---

### Приоритет 2: Миграция auction_lots к storage_id

**Что нужно сделать:**
1. Удалить старые поля из `auction_lots`: `seller_id`, `buyer_id`, `sold_at`
2. Создать хранилища типа `auction_lot` для каждого лота
3. Обновить `AuctionService`

---

### Приоритет 3: Обновление сервисов

**Что нужно сделать:**
1. `InventoryService` — работа с хранилищами
2. `AuctionService` — создание хранилищ для лотов
3. `TradeService` — работа с хранилищами
4. `CraftingService` — поддержка blueprint-системы

---

### Приоритет 4: Обновление тестов

**Что нужно сделать:**
1. Обновить все тесты для работы с новой архитектурой
2. Добавить тесты для хранилищ
3. Добавить тесты для blueprint-системы

---

## 📦 Ключевые концепции

### 1. Event Sourcing

Таблица `game_events` — единственный источник правды. Все изменения состояния фиксируются как события.

**Типы событий:**
- `user.registered` — регистрация
- `user.gold_changed` — изменение золота (ресурса)
- `resource.received` — получение ресурса
- `resource.removed` — изъятие ресурса
- `item.received` — получение предмета
- `item.removed` — изъятие предмета
- `item.transferred` — передача предмета между хранилищами
- `item.crafted` — создание предмета
- `item.disassembled` — разборка предмета
- `item.equipped` — надевание экипировки
- `item.unequipped` — снятие экипировки
- `auction.listed` — выставление лота
- `auction.purchase` — покупка лота
- `auction.cancelled` — отмена лота
- `trade.created/updated/accepted/completed/cancelled` — обмен

---

### 2. Ресурсы vs Предметы

**Ресурсы** (`resource_balances`):
- Не имеют индивидуальности
- Хранятся как балансы: `user_id + template_id → quantity`
- Примеры: дерево, руда, золото
- При передаче меняется только количество

**Предметы** (`items`):
- Каждый экземпляр уникален
- Хранятся как отдельные записи
- Примеры: мечи, доспехи, чертежи
- При передаче меняется `storage_id`

---

### 3. Хранилища

Предметы привязаны к хранилищам, а не к игрокам напрямую.

**Примеры:**
- Инвентарь игрока — хранилище типа `inventory`
- Экипировка — хранилище типа `equipment` с слотами
- Лот на аукционе — хранилище типа `auction_lot`
- Выброшенный предмет — хранилище типа `world`

**Преимущества:**
- Множественные хранилища (несколько банков)
- Специализация (сумка только для руды)
- Единая модель для всех контейнеров

---

### 4. Чертежи (Blueprints)

Чертеж — это особый тип предмета (`type: blueprint`), который:
- Хранится в инвентаре как обычный предмет
- При крафте "встраивается" в создаваемый предмет (`recipe_id`, `materials_used`)
- При разборке возвращается обратно
- Может быть передан, продан, найден

**Поток:**
```
Чертёж (type: blueprint)
  ↓ крафт с материалами
Предмет (type: equipment, recipe_id: ..., materials_used: [...])
  ↓ разборка
Чертёж (type: blueprint) + материалы
```

---

### 5. Вечные ID

Предметы никогда не удаляются из БД. Они только меняют хранилище.

**Пример:**
```
Item #123: "Осиновый железный могильный посох"
├── 2026-06-24 10:00 — Создан (storage: inventory игрока #5)
├── 2026-06-24 12:30 — Выставлен на аукцион (storage: auction_lot #42)
├── 2026-06-24 15:45 — Куплен (storage: inventory игрока #10)
├── 2026-06-24 18:00 — Выброшен (storage: world)
├── 2026-06-24 20:15 — Найден NPC (storage: inventory игрока #15)
└── 2026-06-24 22:30 — Текущее хранилище: inventory игрока #15
```

---

## 🎮 Игровые механики

### Крафт

**Тип 1: Ресурсные превращения (без чертежа)**
- Дерево → 5 брусков
- 10 руды → 2 слитка
- Событие: `resource.transformed`

**Тип 2: Крафт предметов (с чертежом)**
- Чертёж + материалы → предмет
- Чертеж "встраивается" в предмет
- Событие: `item.crafted`

### Разборка

- Предмет → материалы + чертёж
- Формула разбора выбирается из `disassemble_formulas`
- Может быть пасхальной (больше ресурсов)
- Событие: `item.disassembled`

### Аукцион

**Обычные лоты:**
- Игрок выставляет предмет
- Предмет перемещается в хранилище `auction_lot`
- При покупке: предмет переходит в инвентарь покупателя

**Бесконечные лоты (NPC):**
- NPC-торговец (Wulfric Goldsmyth)
- Предметы создаются заново при каждой покупке
- Золото сжигается

### Обмен

- Игроки обмениваются ресурсами и предметами
- Все изменения фиксируются событиями

---

## 🔮 Будущие фичи

1. **Квесты от NPC** — награды из выброшенных предметов
2. **Монстры** — забирают экипировку у убитых игроков
3. **Гильдии** — общие хранилища
4. **Специальные хранилища** — сумки с ограничениями
5. **Таблица названий** — автоматическая генерация названий из материалов

---

## 📝 История изменений

### 24 июня 2026 (v2.0)
- Зафиксирована целевая архитектура БД
- Добавлена концепция хранилищ
- Добавлена система формул разбора
- Описан план миграции

### 23 июня 2026 (v1.0)
- Базовая архитектура
- Аукцион, обмен, крафт
- NPC-торговцы

---

*Документ поддерживается в актуальном состоянии. Все изменения обсуждаются и фиксируются.*
