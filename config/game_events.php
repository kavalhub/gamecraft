<?php

declare(strict_types=1);

return [
    /*
    | Публичные типы событий — видны во вкладке «Журнал» окна «Чат».
    | Остальные типы — системные, только для внутренней логики и персонального polling.
    */
    'public_types' => [
        'user.registered',
        'auction.listed',
        'auction.purchased',
        'auction.sold',
        'trade.completed',
        'item.crafted',
        'item.disassembled',
        'presence.changed',
    ],
];
