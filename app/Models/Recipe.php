<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'type', 'name', 'description', 'result_template_slug', 'result_quantity'];

    public function formulas(): HasMany
    {
        return $this->hasMany(Formula::class, 'recipe_slug', 'slug');
    }

    public function craftFormulas(): HasMany
    {
        return $this->formulas()->where('type', 'craft')->where('is_active', true)->orderBy('priority');
    }

    public function disassembleFormulas(): HasMany
    {
        return $this->formulas()->where('type', 'disassemble')->where('is_active', true)->orderBy('priority');
    }
}
