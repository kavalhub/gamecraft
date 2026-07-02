<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterStat extends Model
{
    protected $fillable = [
        'character_uuid',
        'level',
        'base_damage',
        'strength',
        'agility',
        'intellect',
        'stamina',
        'spirit',
    ];

    protected $casts = [
        'level' => 'integer',
        'base_damage' => 'integer',
        'strength' => 'integer',
        'agility' => 'integer',
        'intellect' => 'integer',
        'stamina' => 'integer',
        'spirit' => 'integer',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_uuid', 'uuid');
    }
}
