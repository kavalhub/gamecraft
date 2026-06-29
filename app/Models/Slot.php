<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Slot extends Model
{
    protected $fillable = ['uuid', 'storage_uuid', 'slot_type'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? Str::uuid()->toString());
    }

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class, 'storage_uuid', 'uuid');
    }

    public function item(): HasOne
    {
        return $this->hasOne(Item::class, 'slot_uuid', 'uuid');
    }

    public function resource(): HasOne
    {
        return $this->hasOne(Resources::class, 'slot_uuid', 'uuid');
    }

    public function isEmpty(): bool
    {
        return !$this->item && !$this->resource;
    }
}
