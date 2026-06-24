<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'type',
        'icon',
        'is_stackable',
        'max_stack',
        'description',
        'disassemble_data',
        'stats',
    ];

    protected $casts = [
        'is_stackable' => 'boolean',
        'max_stack' => 'integer',
        'disassemble_data' => 'array',
        'stats' => 'array',
    ];

    public function isResource(): bool
    {
        return in_array($this->type, ['material', 'consumable']);
    }

    public function isItem(): bool
    {
        return in_array($this->type, ['equipment', 'blueprint']);
    }

    public function isBlueprint(): bool
    {
        return $this->type === 'blueprint';
    }

    public function isEquipment(): bool
    {
        return $this->type === 'equipment';
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'result_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function resourceBalances(): HasMany
    {
        return $this->hasMany(ResourceBalance::class);
    }
}
