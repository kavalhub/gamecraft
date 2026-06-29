<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterSetting extends Model
{
    protected $fillable = ['character_uuid', 'key', 'value'];
    protected $casts = ['value' => 'array'];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_uuid', 'uuid');
    }

    public static function get(string $characterUuid, string $key, $default = null)
    {
        $setting = self::where('character_uuid', $characterUuid)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $characterUuid, string $key, $value): void
    {
        self::updateOrCreate(
            ['character_uuid' => $characterUuid, 'key' => $key],
            ['value' => $value]
        );
    }
}
