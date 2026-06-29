<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\StorageType;
use App\Models\TemporarySlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorageProvisioningService
{
    public const INVENTORY_SLOT_COUNT = 36;
    public const TRADE_TEMPORARY_SLOT_COUNT = 20;

    public function __construct(
        private EventStore $eventStore,
        private SpecialSlotService $specialSlotService
    ) {}

    public function provisionDefaults(Character $character): void
    {
        foreach (['inventory', 'equipment', 'bank', 'play_panel'] as $storageType) {
            $this->grantStorage($character, $storageType);
        }

        $this->ensureStartingGold($character);
    }

    public function ensureStartingGold(Character $character, int $amount = 1000): void
    {
        $this->consolidateInventoryResources($character);

        if ($this->getInventoryGoldQuantity($character) > 0) {
            return;
        }

        $inventory = $character->storages()->where('storage_type', 'inventory')->first();
        if (!$inventory) {
            $inventory = $this->grantStorage($character, 'inventory');
        }

        $goldSlot = $this->specialSlotService->getGoldSlot($inventory);
        if (!$goldSlot) {
            return;
        }

        \App\Models\Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $goldSlot->uuid,
            'recipe_slug' => 'gold',
            'template_slug' => 'gold',
            'slot_type' => 'gold',
            'max_stack' => null,
            'quantity' => $amount,
        ]);
    }

    public function getInventoryGoldQuantity(Character $character): int
    {
        return $this->specialSlotService->getGoldQuantity($character);
    }

    /**
     * Сливает дубликаты в одном слоте и возвращает золото в спец-слот.
     */
    public function consolidateInventoryResources(Character $character): void
    {
        $inventory = $character->storages()->where('storage_type', 'inventory')->first();
        if (!$inventory) {
            return;
        }

        foreach ($inventory->slots as $slot) {
            $resources = \App\Models\Resources::where('slot_uuid', $slot->uuid)
                ->whereNull('temporary_slot_uuid')
                ->orderBy('id')
                ->get();

            if ($resources->count() <= 1) {
                continue;
            }

            foreach ($resources->groupBy('template_slug') as $group) {
                if ($group->count() <= 1) {
                    continue;
                }

                /** @var \App\Models\Resources $keeper */
                $keeper = $group->first();
                $keeper->update(['quantity' => $group->sum('quantity')]);

                foreach ($group->slice(1) as $duplicate) {
                    $duplicate->delete();
                }
            }
        }

        $this->specialSlotService->relocateGoldToSpecialSlot($character);

        $inventory = $character->storages()->where('storage_type', 'inventory')->first();
        if ($inventory) {
            $this->trimExcessSlots($inventory);
        }
    }

    public function grantStorage(Character $character, string $storageType): Storage
    {
        $typeConfig = StorageType::where('type', $storageType)->firstOrFail();

        $storage = Storage::firstOrCreate(
            [
                'characters_uuid' => $character->uuid,
                'storage_type' => $storageType,
            ],
            [
                'name' => $typeConfig->name,
                'active' => true,
            ]
        );

        if ($storageType !== 'trade') {
            $this->provisionStorageSlots($storage);
        }

        return $storage;
    }

    public function provisionStorageSlots(Storage $storage): void
    {
        $typeConfig = StorageType::where('type', $storage->storage_type)->first();
        if (!$typeConfig || !$typeConfig->allowed_types) {
            return;
        }

        $slotsConfig = $typeConfig->allowed_types['slots'] ?? [];
        $existingSlots = $storage->slots()->get();
        $existingByType = $existingSlots->groupBy(fn ($s) => $s->slot_type ?? '');

        foreach ($slotsConfig as $slotDef) {
            $slotType = $slotDef['slot_type'] ?? null;
            $count = (int) ($slotDef['count'] ?? 0);
            $key = $slotType ?? '';
            $current = $existingByType->get($key, collect())->count();

            for ($i = $current; $i < $count; $i++) {
                Slot::create([
                    'uuid' => Str::uuid()->toString(),
                    'storage_uuid' => $storage->uuid,
                    'slot_type' => $slotType,
                ]);
            }
        }

        $this->trimExcessSlots($storage);
    }

    private function trimExcessSlots(Storage $storage): void
    {
        $typeConfig = StorageType::where('type', $storage->storage_type)->first();
        if (!$typeConfig?->allowed_types) {
            return;
        }

        foreach ($typeConfig->allowed_types['slots'] ?? [] as $slotDef) {
            $slotType = $slotDef['slot_type'] ?? null;
            $count = (int) ($slotDef['count'] ?? 0);

            $query = $storage->slots()->orderBy('id');
            if ($slotType === null) {
                $query->whereNull('slot_type');
            } else {
                $query->where('slot_type', $slotType);
            }

            $slots = $query->get();
            if ($slots->count() <= $count) {
                continue;
            }

            foreach ($slots->slice($count) as $slot) {
                $hasItem = \App\Models\Item::where('slot_uuid', $slot->uuid)->exists();
                $hasResource = \App\Models\Resources::where('slot_uuid', $slot->uuid)->exists();

                if (!$hasItem && !$hasResource) {
                    $slot->delete();
                }
            }
        }
    }

    public function ensureTradeStorage(Character $character): Storage
    {
        return DB::transaction(function () use ($character) {
            $storage = $this->grantStorage($character, 'trade');

            $existingCount = TemporarySlot::where('character_uuid', $character->uuid)
                ->where('storage_uuid', $storage->uuid)
                ->count();

            if ($existingCount >= self::TRADE_TEMPORARY_SLOT_COUNT) {
                return $storage;
            }

            for ($i = $existingCount; $i < self::TRADE_TEMPORARY_SLOT_COUNT; $i++) {
                TemporarySlot::create([
                    'uuid' => Str::uuid()->toString(),
                    'storage_uuid' => $storage->uuid,
                    'character_uuid' => $character->uuid,
                    'slot_index' => $i,
                    'active' => true,
                ]);
            }

            if ($existingCount === 0) {
                $this->eventStore->record(
                    'storage.trade_granted',
                    'storage',
                    $storage->uuid,
                    [
                        'character_uuid' => $character->uuid,
                        'temporary_slot_count' => self::TRADE_TEMPORARY_SLOT_COUNT,
                    ],
                    $character->uuid
                );
            }

            return $storage;
        });
    }

    public function findFreeTradeTemporarySlot(Character $character): ?TemporarySlot
    {
        $storage = Storage::where('characters_uuid', $character->uuid)
            ->where('storage_type', 'trade')
            ->first();

        if (!$storage) {
            return null;
        }

        $tempSlots = TemporarySlot::where('storage_uuid', $storage->uuid)
            ->where('character_uuid', $character->uuid)
            ->where('active', true)
            ->orderBy('slot_index')
            ->get();

        foreach ($tempSlots as $tempSlot) {
            if ($this->isTemporarySlotEmpty($tempSlot)) {
                return $tempSlot;
            }
        }

        return null;
    }

    public function isTemporarySlotEmpty(TemporarySlot $temporarySlot): bool
    {
        return !$this->getOccupantForTemporarySlot($temporarySlot);
    }

    public function getOccupantForTemporarySlot(TemporarySlot $temporarySlot): ?object
    {
        $item = \App\Models\Item::where('temporary_slot_uuid', $temporarySlot->uuid)->first();
        if ($item) {
            return $item;
        }

        return \App\Models\Resources::where('temporary_slot_uuid', $temporarySlot->uuid)->first();
    }

    public function getGridCols(string $storageType): int
    {
        $typeConfig = StorageType::where('type', $storageType)->first();
        $metadata = $typeConfig?->metadata ?? [];

        if (isset($metadata['grid_cols'])) {
            return (int) $metadata['grid_cols'];
        }

        return match ($storageType) {
            'inventory' => 4,
            'trade' => 4,
            'play_panel' => 12,
            default => 4,
        };
    }
}
