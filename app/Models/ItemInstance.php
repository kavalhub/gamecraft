<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemInstance extends Model
{
    protected $table = 'item_instances';

    protected $fillable = [
        'template_id',
        'owner_id',
        'quantity',
        'durability',
        'stats',
    ];

    protected $casts = [
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
