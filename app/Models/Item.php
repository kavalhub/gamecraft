<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Item extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'slot_uuid', 'buffer_slot_uuid', 'recipe_slug', 'template_slug', 'custom_name', 'stage', 'slot_type', 'durability', 'materials_used', 'stats'];
    protected $casts = ['materials_used' => 'array', 'stats' => 'array', 'durability' => 'integer'];

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

    public function isBlueprint(): bool { return $this->stage === 'blueprint'; }
    public function isItem(): bool { return $this->stage === 'item'; }
    public function getDisplayName(): string { return $this->custom_name ?? $this->template->name; }
    public function isBuffered(): bool { return $this->buffer_slot_uuid !== null; }
    /** @deprecated use isBuffered() */
    public function isOnTemporarySlot(): bool { return $this->isBuffered(); }
}
