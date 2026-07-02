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
