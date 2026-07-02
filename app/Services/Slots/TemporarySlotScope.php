<?php

declare(strict_types=1);

namespace App\Services\Slots;

use App\Models\Character;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use App\Services\StorageProvisioningService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class TemporarySlotScope implements SlotScope
{
    /**
     * @param  Collection<int, TemporarySlot>  $temporarySlots
     */
    public function __construct(
        private Storage $storage,
        private Collection $temporarySlots,
        private string $backingSlotType,
    ) {}

    public static function forTemporarySlots(
        Storage $storage,
        Collection $temporarySlots,
        string $backingSlotType,
    ): self {
        return new self($storage, $temporarySlots, $backingSlotType);
    }

    public function findPartialStack(string $templateSlug, ?int $maxStack): ?Resources
    {
        if ($maxStack !== null && $maxStack < 1) {
            return null;
        }

        $tempUuids = $this->temporarySlots->pluck('uuid');
        $query = Resources::whereIn('temporary_slot_uuid', $tempUuids)
            ->where('template_slug', $templateSlug);

        if ($maxStack !== null) {
            $query->where('quantity', '<', $maxStack);
        }

        return $query->first();
    }

    public function findEmptyCell(): ?TemporarySlot
    {
        $provisioning = app(StorageProvisioningService::class);

        foreach ($this->temporarySlots as $tempSlot) {
            if (!$provisioning->getOccupantForTemporarySlot($tempSlot)) {
                return $tempSlot;
            }
        }

        return null;
    }

    public function createResource(
        Character $character,
        ItemTemplate $template,
        string $templateSlug,
        int $quantity,
        mixed $cell
    ): Resources {
        if (!$cell instanceof TemporarySlot) {
            throw new \InvalidArgumentException('TemporarySlotScope ожидает TemporarySlot');
        }

        $backingSlot = $this->allocateBackingSlot();

        return Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $backingSlot->uuid,
            'temporary_slot_uuid' => $cell->uuid,
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

    private function allocateBackingSlot(): Slot
    {
        $backingSlots = $this->storage->slots()
            ->where('slot_type', $this->backingSlotType)
            ->orderBy('id')
            ->get();

        foreach ($backingSlots as $slot) {
            $hasItem = Item::where('slot_uuid', $slot->uuid)->exists();
            $hasResource = Resources::where('slot_uuid', $slot->uuid)->exists();
            if (!$hasItem && !$hasResource) {
                return $slot;
            }
        }

        return Slot::create([
            'uuid' => Str::uuid()->toString(),
            'storage_uuid' => $this->storage->uuid,
            'slot_type' => $this->backingSlotType,
        ]);
    }
}
