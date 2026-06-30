<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestObjective extends Model
{
    protected $fillable = [
        'quest_slug',
        'objective_key',
        'type',
        'config',
        'required_count',
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'required_count' => 'integer',
    ];

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class, 'quest_slug', 'slug');
    }
}
