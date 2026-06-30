<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CharacterQuest extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_TURNED_IN = 'turned_in';

    protected $fillable = [
        'uuid',
        'character_uuid',
        'quest_slug',
        'status',
        'progress',
        'accepted_at',
        'completed_at',
        'turned_in_at',
        'storage_prepared_at',
    ];

    protected $casts = [
        'progress' => 'array',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'turned_in_at' => 'datetime',
        'storage_prepared_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $model) => $model->uuid = $model->uuid ?? Str::uuid()->toString());
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class, 'quest_slug', 'slug');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_uuid', 'uuid');
    }
}
