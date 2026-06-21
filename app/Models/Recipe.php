<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $table = 'recipes';

    protected $fillable = [
        'result_template_id',
        'result_quantity',
        'name',
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
