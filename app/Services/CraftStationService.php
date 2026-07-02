<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use Illuminate\Support\Collection;

class CraftStationService
{
    public const CENTER_SLOT_INDEX = 0;
    public const MATERIAL_SLOT_COUNT = 8;
    public const DISABLED_SLOT_TYPE = 'disabled';

    public function __construct(
        private StorageProvisioningService $provisioningService,
    ) {}

    public function ensureCraftStorage(Character $character): Storage
    {
        $storage = $this->provisioningService->grantStorage($character, 'craft');
        $this->provisionTemporarySlots($character, $storage);

        return $storage;
    }

    public function provisionTemporarySlots(Character $character, Storage $storage): void
    {
        if ($storage->storage_type !== 'craft') {
            return;
        }

        $existing = TemporarySlot::where('storage_uuid', $storage->uuid)
            ->where('character_uuid', $character->uuid)
            ->count();

        $needed = 1 + self::MATERIAL_SLOT_COUNT;
        for ($i = $existing; $i < $needed; $i++) {
            TemporarySlot::create([
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'storage_uuid' => $storage->uuid,
                'character_uuid' => $character->uuid,
                'slot_index' => $i,
                'slot_type' => $i === self::CENTER_SLOT_INDEX ? null : self::DISABLED_SLOT_TYPE,
                'active' => true,
            ]);
        }
    }

    public function getTemporarySlots(Character $character): Collection
    {
        $storage = $this->ensureCraftStorage($character);

        $slots = TemporarySlot::where('storage_uuid', $storage->uuid)
            ->where('character_uuid', $character->uuid)
            ->where('active', true)
            ->orderBy('slot_index')
            ->get();

        $this->normalizeMaterialSlotTypes($character, $slots);

        return $slots;
    }

    private function normalizeMaterialSlotTypes(Character $character, Collection $tempSlots): void
    {
        $center = $tempSlots->firstWhere('slot_index', self::CENTER_SLOT_INDEX);
        if (!$center) {
            return;
        }

        $hasCenterOccupant = Item::where('temporary_slot_uuid', $center->uuid)->exists()
            || Resources::where('temporary_slot_uuid', $center->uuid)->exists();

        if ($hasCenterOccupant) {
            return;
        }

        foreach ($tempSlots as $slot) {
            if ($slot->slot_index === self::CENTER_SLOT_INDEX) {
                if ($slot->slot_type !== null) {
                    $slot->update(['slot_type' => null]);
                    $slot->slot_type = null;
                }
                continue;
            }

            if ($slot->slot_type === null) {
                $slot->update(['slot_type' => self::DISABLED_SLOT_TYPE]);
                $slot->slot_type = self::DISABLED_SLOT_TYPE;
            }
        }
    }

    public function getCenterTemporarySlot(Character $character): TemporarySlot
    {
        $slot = $this->getTemporarySlots($character)
            ->firstWhere('slot_index', self::CENTER_SLOT_INDEX);

        if (!$slot) {
            throw new \RuntimeException('Центральный слот станции создания не найден');
        }

        return $slot;
    }

    public function getMaterialTemporarySlots(Character $character): Collection
    {
        return $this->getTemporarySlots($character)
            ->filter(fn (TemporarySlot $slot) => $slot->slot_index > self::CENTER_SLOT_INDEX);
    }

    public function isCraftTemporarySlot(TemporarySlot $slot): bool
    {
        $storage = Storage::where('uuid', $slot->storage_uuid)->first();

        return $storage?->storage_type === 'craft';
    }

    public function slotRole(TemporarySlot $slot): string
    {
        if ($slot->slot_index === self::CENTER_SLOT_INDEX) {
            return 'center';
        }

        return 'material';
    }

    public function syncMaterialSlotTypes(Character $character): void
    {
        $types = app(SlotFitService::class)->ingredientSlotTypesForCraftCenter($character);

        foreach ($this->getMaterialTemporarySlots($character)->values() as $index => $slot) {
            $slot->update(['slot_type' => $types[$index] ?? self::DISABLED_SLOT_TYPE]);
        }
    }

    public function clearMaterialSlotTypes(Character $character): void
    {
        foreach ($this->getMaterialTemporarySlots($character) as $slot) {
            $slot->update(['slot_type' => self::DISABLED_SLOT_TYPE]);
        }
    }

    public function syncAfterCenterChange(Character $character): void
    {
        $hasCenterOccupant = $this->getCenterItem($character) !== null
            || $this->getCenterResource($character) !== null;

        if ($hasCenterOccupant) {
            $this->syncMaterialSlotTypes($character);
        } else {
            $this->clearMaterialSlotTypes($character);
        }
    }

    public function finalizeAfterCraft(Character $character): void
    {
        $moveService = app(StorageMoveService::class);
        $inventoryService = app(InventoryService::class);

        foreach ($this->getTemporarySlots($character) as $tempSlot) {
            $this->returnTemporarySlotOccupantToInventory(
                $character,
                $tempSlot,
                $moveService,
                $inventoryService,
            );
        }

        $this->syncAfterCenterChange($character);
    }

    private function returnTemporarySlotOccupantToInventory(
        Character $character,
        TemporarySlot $tempSlot,
        StorageMoveService $moveService,
        InventoryService $inventoryService,
    ): void {
        $item = Item::where('temporary_slot_uuid', $tempSlot->uuid)->first();
        if ($item) {
            $backingSlot = Slot::where('uuid', $item->slot_uuid)->first();
            $backingStorage = $backingSlot
                ? Storage::where('uuid', $backingSlot->storage_uuid)->first()
                : null;
            if ($backingStorage?->storage_type === 'inventory') {
                $moveService->move($character, $tempSlot->uuid, $item->slot_uuid);
            } else {
                $inventoryService->addItem(
                    $character,
                    $item->template_slug,
                    $item->stage,
                    $item->custom_name,
                    $item->recipe_slug,
                    $item->materials_used,
                    $item->stats,
                    'inventory'
                );
                $item->delete();
            }

            return;
        }

        $resource = Resources::where('temporary_slot_uuid', $tempSlot->uuid)->first();
        if (!$resource) {
            return;
        }

        $backingSlot = Slot::where('uuid', $resource->slot_uuid)->first();
        $backingStorage = $backingSlot
            ? Storage::where('uuid', $backingSlot->storage_uuid)->first()
            : null;
        if ($backingStorage?->storage_type === 'inventory') {
            $moveService->move($character, $tempSlot->uuid, $resource->slot_uuid);
        } else {
            $templateSlug = $resource->template_slug;
            $quantity = $resource->quantity;
            $resource->delete();
            $inventoryService->addResource($character, $templateSlug, $quantity);
        }
    }

    public function clearOverlays(Character $character): int
    {
        $tempUuids = $this->getTemporarySlots($character)->pluck('uuid');

        $itemsCleared = Item::whereIn('temporary_slot_uuid', $tempUuids)
            ->update(['temporary_slot_uuid' => null]);

        $resourcesCleared = Resources::whereIn('temporary_slot_uuid', $tempUuids)
            ->update(['temporary_slot_uuid' => null]);

        return $itemsCleared + $resourcesCleared;
    }

    /**
     * @return array<string, int>
     */
    public function getMaterialQuantities(Character $character): array
    {
        $quantities = [];

        foreach ($this->getMaterialTemporarySlots($character) as $slot) {
            $resource = Resources::where('temporary_slot_uuid', $slot->uuid)->first();
            if (!$resource) {
                continue;
            }

            $slug = $resource->template_slug;
            $quantities[$slug] = ($quantities[$slug] ?? 0) + $resource->quantity;
        }

        return $quantities;
    }

    public function getCenterResource(Character $character): ?Resources
    {
        $center = $this->getCenterTemporarySlot($character);

        return Resources::where('temporary_slot_uuid', $center->uuid)->first();
    }

    public function getCenterItem(Character $character): ?Item
    {
        $center = $this->getCenterTemporarySlot($character);

        return Item::where('temporary_slot_uuid', $center->uuid)->first();
    }

    /**
     * @return array<string, int>
     */
    public function getCombinedIngredientQuantities(Character $character): array
    {
        $quantities = $this->getMaterialQuantities($character);
        $centerResource = $this->getCenterResource($character);

        if ($centerResource) {
            $slug = $centerResource->template_slug;
            $quantities[$slug] = ($quantities[$slug] ?? 0) + $centerResource->quantity;
        }

        return $quantities;
    }

    public function removeIngredients(Character $character, string $templateSlug, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        $remaining = $quantity;

        $center = $this->getCenterTemporarySlot($character);
        $centerResource = Resources::where('temporary_slot_uuid', $center->uuid)
            ->where('template_slug', $templateSlug)
            ->first();

        if ($centerResource) {
            if ($centerResource->quantity <= $remaining) {
                $remaining -= $centerResource->quantity;
                $centerResource->delete();
            } else {
                $centerResource->update(['quantity' => $centerResource->quantity - $remaining]);
                $remaining = 0;
            }
        }

        if ($remaining > 0) {
            $this->removeFromMaterialSlots($character, $templateSlug, $remaining);
        }
    }

    private function removeFromMaterialSlots(Character $character, string $templateSlug, int $quantity): void
    {
        $remaining = $quantity;

        foreach ($this->getMaterialTemporarySlots($character) as $slot) {
            $resource = Resources::where('temporary_slot_uuid', $slot->uuid)
                ->where('template_slug', $templateSlug)
                ->first();

            if (!$resource) {
                continue;
            }

            if ($resource->quantity <= $remaining) {
                $remaining -= $resource->quantity;
                $resource->delete();
            } else {
                $resource->update(['quantity' => $resource->quantity - $remaining]);
                $remaining = 0;
            }

            if ($remaining <= 0) {
                break;
            }
        }

        if ($remaining > 0) {
            throw new \RuntimeException("Недостаточно ресурса {$templateSlug} на станции создания");
        }
    }

    public function assertBlueprintOnStation(Item $item, Character $character): void
    {
        if (!$item->temporary_slot_uuid) {
            throw new \RuntimeException('Чертёж должен быть на станции создания');
        }

        $centerSlot = $this->getCenterTemporarySlot($character);
        if ($item->temporary_slot_uuid !== $centerSlot->uuid) {
            throw new \RuntimeException('Чертёж должен быть в центральном слоте станции создания');
        }
    }
}
