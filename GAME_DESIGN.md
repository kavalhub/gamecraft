Крафт-Мир — это браузерная MMO-игра с упором на крафт, торговлю и социальные взаимодействия. 
Игрок создаёт персонажа, добывает ресурсы, крафтит предметы, торгует на аукционе и обменивается с другими игроками.

### Ключевые принципы

- **Event Sourcing как источник правды** — все изменения состояния фиксируются как события в таблице `game_events`. Любое состояние (инвентарь, золото, аукцион) — это проекция, которую можно пересчитать из событий.
- **Разделение ресурсов и предметов** — ресурсы (дерево, руда, золото) и предметы (мечи, доспехи) имеют разную природу и хранятся по-разному.
- **Хранилища как универсальные контейнеры** — предметы привязаны к слотам хранилища, а не к игрокам напрямую. Хранилище может принадлежать игроку, гильдии, аукциону или быть системным.
- **Чертежи как "душа" предметов** — чертёж не исчезает при крафте, а становится частью предмета и возвращается при разборке.
- **Вечные UUID** — предметы никогда не удаляются из БД, только меняют хранилище.
- **NPC-торговцы** — бесконечные лоты на аукционе от NPC-персонажей (при покупке создаётся копия предмета или ресурса)
---

## 🏗️ Архитектура базы данных

### 📊 Схема БД

* главная таблица `game_events` в которой хранится вся история игрового мира
* все записи в базе данных создаются только после регистрации события в `game_events` через команды
* события могут быть системными и публичными, системные не отображаются в UI
* любая связь таблиц должна быть не по id, а по uuid (или type/slug) созданием которых управляет сама игра
* это позволит чистить таблицы в любое время от ненужных данных и оптимизировать таблицы

### users — Пользователи

```sql
users
├── id : BIGINT (PK)
├── uuid : VARCHAR (36)
├── name : VARCHAR(255)
├── email : VARCHAR(255) (UNIQUE)
├── password : VARCHAR(255)
└── timestamps
```
**Статус:**

### characters_type — тип игровой сущности

```sql
characters_type
├── id : BIGINT (PK)
├── type : VARCHAR(255) (UNIQUE)
├── name : VARCHAR(255)
```
**Типы игровой сущности:**

| type      | name               |
|-----------|--------------------|
| `player`  | Персонаж           |
| `npc`     | Неигровой персонаж |
| `auction` | Аукцион            |
| `guild`   | Гильдия            |
| `post`    | Почта              |

### characters — игровая сущность

```sql
characters
├── id : BIGINT (PK)
├── uuid : VARCHAR(36)
├── character_type : VARCHAR(255)
├── name : VARCHAR(255)
├── timestamps # дата создания например персонажа или гильдии
```

**Игровая сущность:**

| character_type | name                    |
|----------------|-------------------------|
| `player`       | Вася Пупкин             |
| `npc`          | Агент Смитт             |
| `auction`      | Городской аукцион       |
| `guild`        | Гильдия хороших людей   |
| `location`     | Локация: мост над рекой |
| `chest`        | Сундук в пещере пауков  |



### guild_chars — состав гильдии

```sql
guilds_members
├── id : BIGINT (PK)
├── head_uuid : VARCHAR(36) -- конкретная гильдия или альянс (characters_uuid)
├── member_uuid : VARCHAR(36) -- участник гильдии персонаж или npc / участник альянса гильдия (characters_uuid)
├── role_type : VARCHAR(255) -- master, officer, member (возможно нужна нормализация в отдельную таблицу
├── active : BOOLEAN
├── timestamps # дата вступления в гильдию|альянс
```

### storages — Хранилища

```sql
storages
├── id : BIGINT (PK)
├── uuid : VARCHAR(36)
├── characters_uuid : VARCHAR(36)
├── type : VARCHAR(255)  -- inventory|equipment|bank|auction_lot|special|world
├── name : VARCHAR(255)  -- название например Большая сумка путешественника
├── active : BOOLEAN
├── capacity : INT (nullable)  -- null = безлимит
├── allowed_types : JSON (nullable)  -- null = все типы
├── metadata : JSON (nullable)
└── timestamps
```

### slot - слоты хранилища
```sql
slots
├── id : BIGINT (PK)
├── uuid : VARCHAR(36)
├── storage_uuid : VARCHAR(36) (FK → storages)
├── slot_type : VARCHAR(20) (nullable) -- тип сущности для слота material|blueprint|equipment|equipment_head|bag
└── timestamps
```
* Создаются при создании персонажа (сумка, сундук, банк) гильдии (банк гильдии) и тд.

### temporary_storage_slot - временные слоты хранилища
```sql
temporary_slots
├── id : BIGINT (PK)
├── uuid : VARCHAR(36)
├── storage_uuid : VARCHAR(36) (FK → storages) -- временное хранилище например аукцион или обмен
├── chars_uuid : VARCHAR(36) -- от кого предмет помещён во временное хранилище
└── active : BOOLEAN
└── timestamps -- время когда предмет помещён в хранилище
└── timestamps_end -- время когда хранилище перестанет быть активным
```
* Создаются при обмене предметами, выставлении лота на аукцион, возможно дроп с монстров

### recipes — Рецепты
```sql
recipes
├── id : BIGINT (PK)
├── slug : VARCHAR(50) (UNIQUE)
├── name : VARCHAR(255)
├── description : TEXT (nullable)
├── craft_formula : JSON  -- {"wooden_plank": 2, "iron_ingot": 1}
└── timestamps
```

### disassemble_formulas — Формулы разбора предмета
```sql
disassemble_formulas
├── id : BIGINT (PK)
├── recipe_slug : VARCHAR(50) (FK → recipes)
├── priority : INT  -- 1 = первый проверяется
├── chance : INT  -- 0-100, где 100 = всегда
├── conditions : JSON (nullable)  -- {"min_craftsmanship": 50}
├── formula : JSON  -- {"wooden_plank": 2, "iron_ingot": 1}
├── is_active : BOOLEAN
├── description : VARCHAR(255)  -- "Обычный разбор", "Пасхальный разбор"
└── timestamps
```

### items — Чертежи/Предметы
```sql
items
├── id : BIGINT (PK)
├── slot_uuid : VARCHAR(36) (FK → slots)
├── temporary_slot_uuid : VARCHAR(36) (nullable FK → temporary_slots)
├── recipe_slug : VARCHAR(50) (FK → recipes)
├── custom_name : VARCHAR(255) (nullable)  -- кастомное название
├── stage : VARCHAR(255)  -- blueprint|item
├── type : VARCHAR(20)  -- сущность предмета material|equipment_chest|bag (в будущем можно нормализовать в отдельную таблицу)
├── durability : INT
├── materials_used : JSON (nullable)  -- какие материалы использованы
├── stats : JSON
└── timestamps
```
Принцип:
* Предметы никогда не удаляются (принцип вечного ID (UUID))
* Чертёж может быть создан только по рецепту
* Предмет может быть создан только путём преобразования чертежа в предмет
* Предмет может быть привязан только к storage_uuid
* slot_type в какой тип хранилища может быть помещён предмет 
  (например equipment_head может быть помещён либо в слот хранилища equipment_head либо в слот с типом null)
* recipe_id и materials_used заполняются при крафте

### resources — Балансы ресурсов
resource_balances
├── id : BIGINT (PK)
├── characters_uuid : VARCHAR(36)
├── recipe_slug : VARCHAR(50) (FK → recipes)
├── quantity : INT
└── timestamps

