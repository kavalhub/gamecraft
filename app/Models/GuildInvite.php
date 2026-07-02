<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GuildInvite extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'uuid',
        'guild_uuid',
        'inviter_uuid',
        'target_uuid',
        'status',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (GuildInvite $model) => $model->uuid = $model->uuid ?? Str::uuid()->toString());
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'guild_uuid', 'uuid');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'inviter_uuid', 'uuid');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'target_uuid', 'uuid');
    }
}
