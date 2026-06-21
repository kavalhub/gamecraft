<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'owner_id',
        'quantity',
        'durability',
        'stats',
    ];

    protected $casts = [
        'template_id' => 'integer',
        'owner_id' => 'integer',
        'quantity' => 'integer',
        'durability' => 'integer',
        'stats' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ItemTemplate::class, 'template_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
