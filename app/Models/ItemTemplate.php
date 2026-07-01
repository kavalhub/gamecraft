<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'type', 'icon', 'is_stackable', 'max_stack', 'description', 'base_stats', 'slot_type', 'recipe_slug', 'quest_slug'];
    protected $casts = ['is_stackable' => 'boolean', 'max_stack' => 'integer', 'base_stats' => 'array'];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_slug', 'slug');
    }
}
