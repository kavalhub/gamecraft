<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlugAction extends Model
{
    protected $table = 'slug_actions';

    protected $primaryKey = 'slug';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['slug', 'label'];

    public function formulas(): HasMany
    {
        return $this->hasMany(Formula::class, 'action_slug', 'slug');
    }
}
