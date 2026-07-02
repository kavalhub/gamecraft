<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Resources extends Model
{
    protected $table = 'resources';
    protected $fillable = ['uuid', 'slot_uuid', 'buffer_slot_uuid', 'recipe_slug', 'template_slug', 'slot_type', 'max_stack', 'quantity'];
    protected $casts = ['max_stack' => 'integer', 'quantity' => 'integer'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? Str::uuid()->toString());
    }

    public function slot(): BelongsTo { return $this->belongsTo(Slot::class, 'slot_uuid', 'uuid'); }
    public function bufferSlot(): BelongsTo { return $this->belongsTo(TemporarySlot::class, 'buffer_slot_uuid', 'uuid'); }
    /** @deprecated use bufferSlot() */
    public function temporarySlot(): BelongsTo { return $this->bufferSlot(); }
    public function recipe(): BelongsTo { return $this->belongsTo(Recipe::class, 'recipe_slug', 'slug'); }
    public function template(): BelongsTo { return $this->belongsTo(ItemTemplate::class, 'template_slug', 'slug'); }

    public function isBuffered(): bool { return $this->buffer_slot_uuid !== null; }
}
