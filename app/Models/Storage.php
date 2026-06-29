<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Storage extends Model
{
    protected $fillable = ['uuid', 'characters_uuid', 'storage_type', 'name', 'active'];
    protected $casts = ['active' => 'boolean'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? Str::uuid()->toString());
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'characters_uuid', 'uuid');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(StorageType::class, 'storage_type', 'type');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class, 'storage_uuid', 'uuid');
    }

    public function temporarySlots(): HasMany
    {
        return $this->hasMany(TemporarySlot::class, 'storage_uuid', 'uuid');
    }

    public function freeSlots()
    {
        $occupiedSlotUuids = Item::where('slot_uuid', $this->slots()->pluck('uuid'))->pluck('slot_uuid');
        return $this->slots()->whereNotIn('uuid', $occupiedSlotUuids);
    }
}
