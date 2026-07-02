<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Friendship extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';

    protected $fillable = [
        'uuid',
        'requester_uuid',
        'addressee_uuid',
        'status',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (Friendship $model) => $model->uuid = $model->uuid ?? Str::uuid()->toString());
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'requester_uuid', 'uuid');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'addressee_uuid', 'uuid');
    }
}
