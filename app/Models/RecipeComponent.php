<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'template_id',
        'quantity',
    ];

    protected $casts = [
        'recipe_id' => 'integer',
        'template_id' => 'integer',
        'quantity' => 'integer',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ItemTemplate::class, 'template_id');
    }
}
