<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharacterType extends Model
{
    protected $table = 'characters_type';
    protected $fillable = ['type', 'name', 'parent_type'];

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class, 'character_type', 'type');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_type', 'type');
    }
}
