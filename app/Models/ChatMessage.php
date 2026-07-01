<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'channel',
        'guild_uuid',
        'character_uuid',
        'character_name',
        'body',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (ChatMessage $message) {
            $message->uuid = $message->uuid ?? Str::uuid()->toString();
            $message->created_at = $message->created_at ?? now();
        });
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_uuid', 'uuid');
    }
}
