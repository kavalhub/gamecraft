<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameEvent extends Model
{
    protected $table = 'game_events';

    protected $fillable = [
        'uuid',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'actor_id',
        'occurred_at',
        'payload',
        'metadata',
        'correlation_id',
        'causation_id',
        'version',
        'is_snapshot',
    ];

    protected $casts = [
        'payload' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'is_snapshot' => 'boolean',
        'version' => 'integer',
    ];

    // ===== Типы событий (константы для удобства) =====

    // Пользователь
    public const USER_REGISTERED = 'user.registered';
    public const USER_GOLD_CHANGED = 'user.gold_changed';

    // Инвентарь
    public const ITEM_RECEIVED = 'item.received';
    public const ITEM_REMOVED = 'item.removed';
    public const ITEM_CRAFTED = 'item.crafted';
    public const ITEM_DISASSEMBLED = 'item.disassembled';

    // Аукцион
    public const AUCTION_LISTED = 'auction.listed';
    public const AUCTION_PURCHASE = 'auction.purchase';
    public const AUCTION_SALE = 'auction.sale';
    public const AUCTION_CANCELLED = 'auction.cancelled';
    public const AUCTION_EXPIRED = 'auction.expired';

    // Обмен
    public const TRADE_CREATED = 'trade.created';
    public const TRADE_UPDATED = 'trade.updated';
    public const TRADE_ACCEPTED = 'trade.accepted';
    public const TRADE_COMPLETED = 'trade.completed';
    public const TRADE_CANCELLED = 'trade.cancelled';
}
