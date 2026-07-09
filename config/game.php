<?php

declare(strict_types=1);

return [
    /*
    | Длительность одной строки боевого лога в UI (мс). Сервер и клиент используют одно значение.
    */
    'combat_log_line_ms' => (int) env('GAME_COMBAT_LOG_LINE_MS', 500),

    /*
    | Дополнительное время после проигрывания лога, в течение которого можно забрать добычу (мс).
    */
    'combat_claim_grace_ms' => (int) env('GAME_COMBAT_CLAIM_GRACE_MS', 60_000),

    'default_zone' => 'craft_city',

    'world_max_speed' => (float) env('GAME_WORLD_MAX_SPEED', 15.0),

    'world_max_step' => (float) env('GAME_WORLD_MAX_STEP', 12.0),

    'world_nearby_radius' => (float) env('GAME_WORLD_NEARBY_RADIUS', 30.0),

    'world_interact_radius' => (float) env('GAME_WORLD_INTERACT_RADIUS', 5.0),

    'world_portal_radius' => (float) env('GAME_WORLD_PORTAL_RADIUS', 4.0),

    'world_step_size' => (float) env('GAME_WORLD_STEP_SIZE', 0.75),

    /*
    | Редактор зон: /gamecraft/zone-editor — раскладка тайлов и проходимость.
    | Отключите на проде: GAME_ZONE_EDITOR_ENABLED=false
    */
    'zone_editor_enabled' => (bool) env('GAME_ZONE_EDITOR_ENABLED', true),

    /*
    | URL-префикс приложения (без завершающего слэша).
    | Страницы: /gamecraft, /gamecraft/play; API: /gamecraft/api/...
    */
    'base_path' => rtrim((string) env('GAME_BASE_PATH', '/gamecraft'), '/'),
    'default_window_positions' => [
        'bank' => ['top' => 121, 'left' => 821],
        'mail' => ['top' => 141, 'left' => 509],
        'craft' => ['top' => 142, 'left' => 509],
        'quest' => ['top' => 142, 'left' => 509],
        'trade' => ['top' => 142, 'left' => 509],
        'quests' => ['top' => 16, 'left' => 522],
        'auction' => ['top' => 142, 'left' => 509],
        'confirm' => ['top' => 142, 'left' => 509],
        'journal' => ['top' => 672, 'left' => 12],
        'players' => ['top' => 123, 'left' => 61],
        'settings' => ['top' => 169, 'left' => 769],
        'character' => ['top' => 128, 'left' => 0],
        'encounter' => ['top' => 142, 'left' => 509],
        'inventory' => ['top' => 589, 'left' => 1285],
        'disassemble' => ['top' => 142, 'left' => 509],
        'item-preview' => ['top' => 142, 'left' => 509],
    ],

    'avatars' => [
        'warrior' => ['icon' => '⚔️', 'label' => 'Воин'],
        'mage' => ['icon' => '🧙', 'label' => 'Маг'],
        'ranger' => ['icon' => '🏹', 'label' => 'Лучник'],
        'rogue' => ['icon' => '🗡️', 'label' => 'Разбойник'],
        'cleric' => ['icon' => '✨', 'label' => 'Жрец'],
        'dwarf' => ['icon' => '🧔', 'label' => 'Дварф'],
        'elf' => ['icon' => '🧝', 'label' => 'Эльф'],
        'knight' => ['icon' => '🛡️', 'label' => 'Рыцарь'],
    ],

    'guild_emblems' => [
        'shield' => ['icon' => '🛡️', 'label' => 'Щит'],
        'sword' => ['icon' => '⚔️', 'label' => 'Меч'],
        'dragon' => ['icon' => '🐉', 'label' => 'Дракон'],
        'wolf' => ['icon' => '🐺', 'label' => 'Волк'],
        'eagle' => ['icon' => '🦅', 'label' => 'Орёл'],
        'crown' => ['icon' => '👑', 'label' => 'Корона'],
        'fire' => ['icon' => '🔥', 'label' => 'Огонь'],
        'star' => ['icon' => '⭐', 'label' => 'Звезда'],
    ],
];
