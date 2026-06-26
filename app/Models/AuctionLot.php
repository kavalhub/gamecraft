<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuctionLot extends Model
{
    protected $fillable = ['uuid', 'storage_uuid', 'seller_uuid', 'template_slug', 'quantity', 'price', 'commission_percent', 'status', 'is_infinite', 'buyer_uuid', 'sold_at'];
    protected $casts = ['quantity' => 'integer', 'price' => 'integer', 'commission_percent' => 'integer', 'is_infinite' => 'boolean', 'sold_at' => 'datetime'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? Str::uuid()->toString());
    }

    public function storage(): BelongsTo { return $this->belongsTo(Storage::class, 'storage_uuid', 'uuid'); }
    public function seller(): BelongsTo { return $this->belongsTo(Character::class, 'seller_uuid', 'uuid'); }
    public function buyer(): BelongsTo { return $this->belongsTo(Character::class, 'buyer_uuid', 'uuid'); }
    public function template(): BelongsTo { return $this->belongsTo(ItemTemplate::class, 'template_slug', 'slug'); }
}
