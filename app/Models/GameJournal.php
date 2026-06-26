<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GameJournal extends Model
{
    protected $fillable = ['uuid', 'type', 'item_uuid', 'resource_uuid', 'from_character_uuid', 'to_character_uuid', 'from_slot_uuid', 'to_slot_uuid', 'quantity', 'occurred_at', 'metadata'];
    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime', 'quantity' => 'integer'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? Str::uuid()->toString());
    }
}
