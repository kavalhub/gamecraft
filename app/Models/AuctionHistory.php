<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionHistory extends Model
{
    protected $table = 'auction_history';

    protected $fillable = [
        'lot_id',
        'seller_id',
        'buyer_id',
        'template_id',
        'quantity',
        'price',
        'commission',
        'seller_received',
        'action',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'price' => 'integer',
        'commission' => 'integer',
        'seller_received' => 'integer',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AuctionLot::class, 'lot_id');
    }

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
}
