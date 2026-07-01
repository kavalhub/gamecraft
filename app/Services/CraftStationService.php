<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Storage;
use App\Models\TemporarySlot;
use Illuminate\Support\Collection;

class CraftStationService
{
    public const CENTER_SLOT_INDEX = 0;
    public const MATERIAL_SLOT_COUNT = 8;

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
                'active' => true,
            ]);
        }
    }

    public function getTemporarySlots(Character $character): Collection
    {
        $storage = $this->ensureCraftStorage($character);

        return TemporarySlot::where('storage_uuid', $storage->uuid)
            ->where('character_uuid', $character->uuid)
            ->where('active', true)
            ->orderBy('slot_index')
            ->get();
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
        return $slot->slot_index === self::CENTER_SLOT_INDEX
            ? 'craft_center'
            : 'craft_material';
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
