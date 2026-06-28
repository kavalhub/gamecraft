# Крафт-Мир

Браузерная крафт-игра на Laravel 12 + Octane (Swoole).

## Возможности

- Slot-based инвентарь (инвентарь, экипировка, банк)
- Крафт ресурсов, чертежей и предметов
- Аукцион и P2P-обмен
- Real-time события через WebSocket
- Контент через `content/base.json`

## Требования

- PHP 8.2+
- MySQL
- Composer, Node.js
- Swoole (для Octane и WebSocket)

## Установка

```bash
composer install
cp .env.example .env   # если ещё нет
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
```

## Запуск

### Режим разработки

```bash
composer dev
```

### Octane (production-like)

```bash
php artisan octane:start --server=swoole
```

### WebSocket-сервер событий

```bash
php artisan websocket:serve --port=8001
```

## API и авторизация

Все игровые эндпоинты (кроме `/api/register` и `/api/login`) требуют Bearer-токен Sanctum.

```bash
# Регистрация
curl -X POST /api/register -d '{"username":"hero","password":"secret"}'

# Вход
curl -X POST /api/login -d '{"username":"hero","password":"secret"}'

# Запрос с токеном
curl -H "Authorization: Bearer {token}" /api/inventory/{characterUuid}
```

Маршруты с `{characterUuid}` проверяют, что персонаж принадлежит авторизованному пользователю.

## Тесты

```bash
php artisan test
```

## Структура

```
app/Services/     — бизнес-логика (Inventory, Crafting, Auction, Trade)
app/Services/EventStore.php — журнал игровых событий
content/base.json — рецепты, шаблоны, NPC
resources/views/  — Blade UI + клиентский JS
```

## Импорт контента

Контент загружается при `php artisan db:seed` из `content/base.json`.
Для обновления баланса отредактируйте JSON и перезапустите сидер.
