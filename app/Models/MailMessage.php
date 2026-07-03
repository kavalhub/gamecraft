<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MailMessage extends Model
{
    protected $fillable = [
        'uuid',
        'storage_uuid',
        'recipient_uuid',
        'sender_uuid',
        'sender_name',
        'subject',
        'body',
        'attachment_count',
        'status',
        'read_at',
        'claimed_at',
        'expires_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $model) => $model->uuid = $model->uuid ?? Str::uuid()->toString());
    }

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class, 'storage_uuid', 'uuid');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'recipient_uuid', 'uuid');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'sender_uuid', 'uuid');
    }
}
