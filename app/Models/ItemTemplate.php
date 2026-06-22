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

    public function instances(): HasMany
    {
        return $this->hasMany(ItemInstance::class, 'template_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'result_template_id');
    }

    public function recipeComponents(): HasMany
    {
        return $this->hasMany(RecipeComponent::class, 'template_id');
    }
}
