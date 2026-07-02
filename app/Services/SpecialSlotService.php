<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\ItemTemplate;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\StorageType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SpecialSlotService
{
    /**
     * @return array<int, array{slot_type: ?string, count: int, hidden: bool}>
     */
    public function getSlotDefinitions(Storage $storage): array
    {
        $typeConfig = StorageType::where('type', $storage->storage_type)->first();
        if (!$typeConfig?->allowed_types) {
            return [];
        }

        $defs = [];
        foreach ($typeConfig->allowed_types['slots'] ?? [] as $def) {
            $defs[] = [
                'slot_type' => $def['slot_type'] ?? null,
                'count' => (int) ($def['count'] ?? 0),
                'hidden' => (bool) ($def['hidden'] ?? false),
            ];
        }

        return $defs;
    }

    public function getPolicyForSlotType(Storage $storage, ?string $slotType): ?array
    {
        foreach ($this->getSlotDefinitions($storage) as $def) {
            if ($def['slot_type'] === $slotType) {
                return $def;
            }
        }

        return null;
    }

    public function getGridSlots(Storage $storage): Collection
    {
        return $storage->slots()->whereNull('slot_type')->orderBy('id')->get();
    }

    public function getSpecialSlots(Storage $storage): Collection
    {
        return $storage->slots()->whereNotNull('slot_type')->orderBy('id')->get();
    }

    public function isGridSlot(Slot $slot): bool
    {
        return $slot->slot_type === null;
    }

    public function resourceMatchesSlotType(ItemTemplate $template, string $slotType): bool
    {
        if ($template->slug === $slotType) {
            return true;
        }

        return $template->slot_type === $slotType;
    }

    public function getGoldSlot(Storage $storage): ?Slot
    {
        return $storage->slots()->where('slot_type', 'gold')->first();
    }

    public function getExperienceSlot(Storage $storage): ?Slot
    {
        return $storage->slots()->where('slot_type', 'experience')->first();
    }

    public function getExperienceQuantity(Character $character, string $storageType = 'inventory'): int
    {
        $storage = $character->storages()->where('storage_type', $storageType)->first();
        if (!$storage) {
            return 0;
        }

        $xpSlot = $this->getExperienceSlot($storage);
        if (!$xpSlot) {
            return $this->sumResourceInStorage($storage, 'experience');
        }

        return (int) Resources::where('slot_uuid', $xpSlot->uuid)
            ->where('template_slug', 'experience')
            ->whereNull('temporary_slot_uuid')
            ->sum('quantity');
    }

    public function relocateExperienceToSpecialSlot(Character $character, string $storageType = 'inventory'): void
    {
        $this->relocateResourceToSpecialSlot($character, 'experience', $storageType);
    }

    private function relocateResourceToSpecialSlot(Character $character, string $templateSlug, string $storageType = 'inventory'): void
    {
        $storage = $character->storages()->where('storage_type', $storageType)->first();
        if (!$storage) {
            return;
        }

        $specialSlot = $storage->slots()->where('slot_type', $templateSlug)->first();
        if (!$specialSlot) {
            return;
        }

        $gridUuids = $this->getGridSlots($storage)->pluck('uuid');
        $gridRows = Resources::whereIn('slot_uuid', $gridUuids)
            ->where('template_slug', $templateSlug)
            ->whereNull('temporary_slot_uuid')
            ->get();

        if ($gridRows->isEmpty()) {
            return;
        }

        $extra = (int) $gridRows->sum('quantity');
        foreach ($gridRows as $row) {
            $row->delete();
        }

        $keeper = Resources::where('slot_uuid', $specialSlot->uuid)
            ->where('template_slug', $templateSlug)
            ->whereNull('temporary_slot_uuid')
            ->first();

        if ($keeper) {
            $keeper->update(['quantity' => $keeper->quantity + $extra]);
        } elseif ($extra > 0) {
            Resources::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $specialSlot->uuid,
                'recipe_slug' => $templateSlug,
                'template_slug' => $templateSlug,
                'slot_type' => $templateSlug,
                'max_stack' => null,
                'quantity' => $extra,
            ]);
        }
    }

    public function getGoldQuantity(Character $character, string $storageType = 'inventory'): int
    {
        $storage = $character->storages()->where('storage_type', $storageType)->first();
        if (!$storage) {
            return 0;
        }

        $goldSlot = $this->getGoldSlot($storage);
        if (!$goldSlot) {
            return $this->sumResourceInStorage($storage, 'gold');
        }

        return (int) Resources::where('slot_uuid', $goldSlot->uuid)
            ->where('template_slug', 'gold')
            ->whereNull('temporary_slot_uuid')
            ->sum('quantity');
    }

    public function relocateGoldToSpecialSlot(Character $character, string $storageType = 'inventory'): void
    {
        $storage = $character->storages()->where('storage_type', $storageType)->first();
        if (!$storage) {
            return;
        }

        $goldSlot = $this->getGoldSlot($storage);
        if (!$goldSlot) {
            return;
        }

        $gridUuids = $this->getGridSlots($storage)->pluck('uuid');
        $gridGold = Resources::whereIn('slot_uuid', $gridUuids)
            ->where('template_slug', 'gold')
            ->whereNull('temporary_slot_uuid')
            ->get();

        if ($gridGold->isEmpty()) {
            return;
        }

        $extra = (int) $gridGold->sum('quantity');
        foreach ($gridGold as $row) {
            $row->delete();
        }

        $keeper = Resources::where('slot_uuid', $goldSlot->uuid)
            ->where('template_slug', 'gold')
            ->whereNull('temporary_slot_uuid')
            ->first();

        if ($keeper) {
            $keeper->update(['quantity' => $keeper->quantity + $extra]);
        } elseif ($extra > 0) {
            Resources::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $goldSlot->uuid,
                'recipe_slug' => 'gold',
                'template_slug' => 'gold',
                'slot_type' => 'gold',
                'max_stack' => null,
                'quantity' => $extra,
            ]);
        }
    }

    public function depositResource(
        Character $character,
        string $templateSlug,
        int $quantity,
        string $storageType = 'inventory'
    ): Resources {
        return app(SlotDepositService::class)->depositToInventory($character, $templateSlug, $quantity, $storageType);
    }

    private function sumResourceInStorage(Storage $storage, string $templateSlug): int
    {
        $slotUuids = $storage->slots()->pluck('uuid');

        return (int) Resources::whereIn('slot_uuid', $slotUuids)
            ->where('template_slug', $templateSlug)
            ->whereNull('temporary_slot_uuid')
            ->sum('quantity');
    }
}
