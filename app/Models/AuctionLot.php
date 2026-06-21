<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionLot extends Model
{
    protected $table = 'auction_lots';

    protected $fillable = [
        'seller_id',
        'template_id',
        'quantity',
        'item_instance_id',
        'item_stats',
        'price',
        'commission_percent',
        'status',
        'buyer_id',
        'sold_at',
    ];

    protected $casts = [
        'item_stats' => 'array',
        'price' => 'integer',
        'commission' => 'integer',
        'sold_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ItemTemplate::class, 'template_id');
    }

    // ===== Расчёты =====

    public function getCommissionAttribute(): int
    {
        return (int) floor($this->price * $this->commission_percent / 100);
    }

    public function getSellerReceivedAttribute(): int
    {
        return $this->price - $this->commission;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
