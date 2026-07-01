<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Formula extends Model
{
    protected $fillable = ['recipe_slug', 'type', 'priority', 'chance', 'conditions', 'formula', 'is_active', 'description', 'action_slug'];

    protected $casts = [
        'priority' => 'integer',
        'chance' => 'integer',
        'conditions' => 'array',
        'formula' => 'array',
        'is_active' => 'boolean',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_slug', 'slug');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(SlugAction::class, 'action_slug', 'slug');
    }

    public function shouldApply(array $context = []): bool
    {
        if ($this->conditions) {
            foreach ($this->conditions as $key => $value) {
                if (!isset($context[$key]) || $context[$key] < $value) {
                    return false;
                }
            }
        }

        if ($this->chance < 100) {
            return random_int(1, 100) <= $this->chance;
        }

        return true;
    }
}
