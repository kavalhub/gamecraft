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
        'result_quantity' => 'integer',
    ];

    public function resultTemplate(): BelongsTo
    {
        return $this->belongsTo(ItemTemplate::class, 'result_template_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(RecipeComponent::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function disassembleFormulas(): HasMany
    {
        return $this->hasMany(DisassembleFormula::class);
    }

    public function getDisassembleFormula(array $context = []): ?DisassembleFormula
    {
        $formulas = $this->disassembleFormulas()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($formulas as $formula) {
            if ($formula->shouldApply($context)) {
                return $formula;
            }
        }

        return null;
    }
}
