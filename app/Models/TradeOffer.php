<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradeOffer extends Model
{
    protected $table = 'trade_offers';

    protected $fillable = [
        'initiator_id',
        'partner_id',
        'status',
        'initiator_accepted',
        'partner_accepted',
        'initiator_gold',
        'partner_gold',
    ];

    protected $casts = [
        'initiator_accepted' => 'boolean',
        'partner_accepted' => 'boolean',
        'initiator_gold' => 'integer',
        'partner_gold' => 'integer',
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TradeItem::class, 'trade_id');
    }

    public function initiatorItems(): HasMany
    {
        return $this->items()->where('side', 'initiator');
    }

    public function partnerItems(): HasMany
    {
        return $this->items()->where('side', 'partner');
    }

    public function isParticipant(int $userId): bool
    {
        return $this->initiator_id === $userId || $this->partner_id === $userId;
    }

    public function getSide(int $userId): ?string
    {
        if ($this->initiator_id === $userId) return 'initiator';
        if ($this->partner_id === $userId) return 'partner';
        return null;
    }

    public function getOpponentId(int $userId): ?int
    {
        if ($this->initiator_id === $userId) return $this->partner_id;
        if ($this->partner_id === $userId) return $this->initiator_id;
        return null;
    }
}
