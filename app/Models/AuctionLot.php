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
        'buyer_id',
        'template_id',
        'quantity',
        'item_instance_id',
        'item_stats',
        'price',
        'commission_percent',
        'status',
    ];

    protected $casts = [
        'seller_id' => 'integer',
        'buyer_id' => 'integer',
        'template_id' => 'integer',
        'quantity' => 'integer',
        'item_instance_id' => 'integer',
        'item_stats' => 'array',
        'price' => 'integer',
        'commission_percent' => 'integer',
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

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ItemInstance::class, 'item_instance_id');
    }
}
