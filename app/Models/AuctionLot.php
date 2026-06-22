<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'template_id',
        'quantity',
        'item_instance_id',
        'item_stats',
        'price',
        'commission_percent',
        'status',
        'is_infinite',
        'buyer_id',
        'sold_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'integer',
        'commission_percent' => 'integer',
        'item_stats' => 'array',
        'is_infinite' => 'boolean',
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

    public function itemInstance(): BelongsTo
    {
        return $this->belongsTo(ItemInstance::class, 'item_instance_id');
    }
}
