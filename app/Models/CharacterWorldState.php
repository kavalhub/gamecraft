<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterWorldState extends Model
{
    protected $fillable = [
        'character_uuid',
        'zone_slug',
        'x',
        'y',
        'z',
        'rotation_y',
        'moved_at',
    ];

    protected $casts = [
        'x' => 'float',
        'y' => 'float',
        'z' => 'float',
        'rotation_y' => 'float',
        'moved_at' => 'datetime',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_uuid', 'uuid');
    }
}
