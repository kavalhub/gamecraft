<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeItem extends Model
{
    protected $fillable = ['trade_uuid', 'character_uuid', 'item_uuid', 'resource_uuid', 'quantity'];
    protected $casts = ['quantity' => 'integer'];

    public function trade(): BelongsTo { return $this->belongsTo(TradeOffer::class, 'trade_uuid', 'uuid'); }
    public function character(): BelongsTo { return $this->belongsTo(Character::class, 'character_uuid', 'uuid'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class, 'item_uuid', 'uuid'); }
    public function resource(): BelongsTo { return $this->belongsTo(Resource::class, 'resource_uuid', 'uuid'); }
}
