<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Character extends Model
{
    protected $fillable = ['uuid', 'user_uuid', 'character_type', 'name', 'active'];
    protected $casts = ['active' => 'boolean'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? Str::uuid()->toString());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CharacterType::class, 'character_type', 'type');
    }

    public function storages(): HasMany
    {
        return $this->hasMany(Storage::class, 'characters_uuid', 'uuid');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(CharacterStat::class, 'character_uuid', 'uuid');
    }

    public function isPlayer(): bool { return $this->character_type === 'player'; }
    public function isNpc(): bool { return str_starts_with($this->character_type, 'npc'); }
    public function isAuction(): bool { return $this->character_type === 'auction'; }
}
