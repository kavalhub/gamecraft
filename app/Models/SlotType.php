<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotType extends Model
{
    protected $table = 'slot_types';
    protected $fillable = ['type', 'parent_type', 'name', 'description'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_type', 'type');
    }

    public function accepts(string $itemSlotType): bool
    {
        if ($this->type === $itemSlotType) return true;
        if ($this->parent_type === $itemSlotType) return true;
        return false;
    }
}
