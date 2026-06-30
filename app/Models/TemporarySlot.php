<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TemporarySlot extends Model
{
    protected $fillable = ['uuid', 'storage_uuid', 'character_uuid', 'slot_index', 'quest_slug', 'slot_role', 'active', 'timestamps_end'];
    protected $casts = ['active' => 'boolean', 'timestamps_end' => 'datetime'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? Str::uuid()->toString());
    }

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class, 'storage_uuid', 'uuid');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_uuid', 'uuid');
    }
}
