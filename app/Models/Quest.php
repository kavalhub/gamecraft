<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quest extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'accept_grants',
        'starter_item_template_slug',
        'giver_character_uuid',
        'prerequisites',
        'rewards',
        'repeatable',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'accept_grants' => 'array',
        'prerequisites' => 'array',
        'rewards' => 'array',
        'repeatable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function objectives(): HasMany
    {
        return $this->hasMany(QuestObjective::class, 'quest_slug', 'slug')->orderBy('sort_order');
    }
}
