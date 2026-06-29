<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TradeOffer extends Model
{
    protected $fillable = ['uuid', 'initiator_uuid', 'partner_uuid', 'status', 'initiator_accepted', 'partner_accepted'];
    protected $casts = ['initiator_accepted' => 'boolean', 'partner_accepted' => 'boolean'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? Str::uuid()->toString());
    }

    public function initiator(): BelongsTo { return $this->belongsTo(Character::class, 'initiator_uuid', 'uuid'); }
    public function partner(): BelongsTo { return $this->belongsTo(Character::class, 'partner_uuid', 'uuid'); }
    public function items(): HasMany { return $this->hasMany(TradeItem::class, 'trade_uuid', 'uuid'); }
}
