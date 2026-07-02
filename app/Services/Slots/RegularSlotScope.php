<?php

declare(strict_types=1);

namespace App\Services\Slots;

use App\Models\Character;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class RegularSlotScope implements SlotScope
{
    /**
     * @param  Collection<int, Slot>  $slots
     */
    public function __construct(
        private Storage $storage,
        private Collection $slots,
        private bool $onlyWithoutOverlay = true,
    ) {}

    public static function forStorageGrid(Storage $storage, Collection $slots): self
    {
        return new self($storage, $slots, onlyWithoutOverlay: true);
    }

    public function findPartialStack(string $templateSlug, ?int $maxStack): ?Resources
    {
        if ($maxStack !== null && $maxStack < 1) {
            return null;
        }

        $slotUuids = $this->slots->pluck('uuid');
        $query = Resources::whereIn('slot_uuid', $slotUuids)
            ->where('template_slug', $templateSlug);

        if ($this->onlyWithoutOverlay) {
            $query->whereNull('buffer_slot_uuid');
        }

        if ($maxStack !== null) {
            $query->where('quantity', '<', $maxStack);
        }

        return $query->first();
    }

    public function findEmptyCell(): ?Slot
    {
        $slotUuids = $this->slots->pluck('uuid');
        $occupied = Resources::whereIn('slot_uuid', $slotUuids)->pluck('slot_uuid')
            ->merge(Item::whereIn('slot_uuid', $slotUuids)->pluck('slot_uuid'))
            ->unique();

        return $this->slots->first(fn (Slot $slot) => !$occupied->contains($slot->uuid));
    }

    public function createResource(
        Character $character,
        ItemTemplate $template,
        string $templateSlug,
        int $quantity,
        mixed $cell
    ): Resources {
        if (!$cell instanceof Slot) {
            throw new \InvalidArgumentException('RegularSlotScope ожидает Slot');
        }

        return Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $cell->uuid,
            'recipe_slug' => $templateSlug,
            'template_slug' => $templateSlug,
            'slot_type' => $template->slot_type,
            'max_stack' => $template->max_stack,
            'quantity' => $quantity,
        ]);
    }

    public function exhaustedMessage(): string
    {
        return "Нет свободных слотов в хранилище {$this->storage->storage_type}";
    }

    public function storage(): Storage
    {
        return $this->storage;
    }
}
