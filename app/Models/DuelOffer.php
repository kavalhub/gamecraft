<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DuelOffer extends Model
{
    protected $fillable = [
        'uuid',
        'challenger_uuid',
        'opponent_uuid',
        'status',
        'correlation_uuid',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $model) => $model->uuid = $model->uuid ?? Str::uuid()->toString());
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'challenger_uuid', 'uuid');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'opponent_uuid', 'uuid');
    }
}
