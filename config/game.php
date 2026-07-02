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
];
