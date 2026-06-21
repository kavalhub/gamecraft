<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemTemplate extends Model
{
    protected $table = 'item_templates';

    protected $fillable = [
        'name',
        'type',
        'is_stackable',
        'icon',
        'disassemble_data',
    ];

    protected $casts = [
        'is_stackable' => 'boolean',
        'disassemble_data' => 'array',
    ];

    public function instances(): HasMany
    {
        return $this->hasMany(ItemInstance::class, 'template_id');
    }
}
