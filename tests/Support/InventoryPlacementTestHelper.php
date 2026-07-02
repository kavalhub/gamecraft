<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Character;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\StorageType;
use App\Services\SpecialSlotService;
use App\Services\StorageProvisioningService;
use Illuminate\Support\Str;

final class InventoryPlacementTestHelper
{
    /**
     * Добавляет typed-слот в конфиг инвентаря и создаёт его в БД.
     *
     * @param  array{hidden?: bool}  $options
     */
    public static function addInventoryTypedSlot(
        Character $character,
        string $slotType,
        array $options = [],
    ): Slot {
        $inventory = $character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $storageType = StorageType::where('type', 'inventory')->firstOrFail();

        $allowed = $storageType->allowed_types ?? ['slots' => []];
        $slots = $allowed['slots'] ?? [];

        $alreadyConfigured = collect($slots)->contains(
            fn (array $def) => ($def['slot_type'] ?? null) === $slotType
        );

        if (!$alreadyConfigured) {
            $slots[] = [
                'slot_type' => $slotType,
                'count' => 1,
                'hidden' => (bool) ($options['hidden'] ?? false),
            ];
            $allowed['slots'] = $slots;
            $storageType->update(['allowed_types' => $allowed]);
        }

        app(StorageProvisioningService::class)->provisionStorageSlots($inventory);

        return $inventory->slots()->where('slot_type', $slotType)->orderBy('id')->firstOrFail();
    }

    public static function inventory(Character $character): Storage
    {
        return $character->storages()->where('storage_type', 'inventory')->firstOrFail();
    }

    public static function firstGridSlot(Character $character): Slot
    {
        $inventory = self::inventory($character);

        return app(SpecialSlotService::class)->getGridSlots($inventory)->firstOrFail();
    }

    public static function placeWood(Character $character, int $quantity, ?Slot $slot = null): Resources
    {
        $slot ??= self::firstGridSlot($character);

        return Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $slot->uuid,
            'recipe_slug' => 'wood',
            'template_slug' => 'wood',
            'slot_type' => 'wood',
            'max_stack' => 20,
            'quantity' => $quantity,
        ]);
    }
}
