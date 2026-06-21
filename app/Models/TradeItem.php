<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeItem extends Model
{
    protected $table = 'trade_items';

    protected $fillable = [
        'trade_id',
        'side',
        'template_id',
        'item_instance_id',
        'quantity',
    ];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(TradeOffer::class, 'trade_id');
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
