<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'result_template_id',
        'result_quantity',
    ];

    protected $casts = [
        'result_template_id' => 'integer',
        'result_quantity' => 'integer',
    ];

    public function resultTemplate(): BelongsTo
    {
        return $this->belongsTo(ItemTemplate::class, 'result_template_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(RecipeComponent::class, 'recipe_id');
    }
}
