<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterHeartbeat extends Model
{
    protected $fillable = ['character_uuid', 'last_seen_at'];
    protected $casts = ['last_seen_at' => 'datetime'];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_uuid', 'uuid');
    }

    public static function ping(string $characterUuid): void
    {
        self::updateOrCreate(
            ['character_uuid' => $characterUuid],
            ['last_seen_at' => now()]
        );
    }

    public static function getOnline(int $minutesThreshold = 5): \Illuminate\Support\Collection
    {
        return self::where('last_seen_at', '>=', now()->subMinutes($minutesThreshold))
            ->with('character')
            ->get()
            ->pluck('character');
    }
}
