<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Services\CraftStationService;
use App\Services\DisassembleStationService;
use App\Services\InventoryService;
use App\Services\StorageMoveService;

final class WorkbenchHelper
{
    public static function ensureWorkbench(Character $character)
    {
        return app(CraftStationService::class)->ensureCraftStorage($character);
    }

    public static function placeOnBlueprintSlot(Character $character, Item $item): void
    {
        CraftStationHelper::placeOnCenterSlot($character, $item, 'craft');
    }

    /**
     * @param  array<string, int>  $materials
     */
    public static function placeMaterials(Character $character, array $materials): void
    {
        CraftStationHelper::placeMaterials($character, $materials);
    }

    /**
     * @param  array<string, int>  $materials
     */
    public static function moveMaterialsToWorkbench(Character $character, array $materials): void
    {
        CraftStationHelper::moveMaterialsToCraftStation($character, $materials);
    }

    public static function prepareCraft(Character $character, Item $blueprint, array $materials): void
    {
        CraftStationHelper::prepareCraft($character, $blueprint, $materials);
    }

    public static function craftWoodenSword(Character $character, ?Item $blueprint = null): Item
    {
        $crafting = app(\App\Services\CraftingService::class);
        $blueprint = $blueprint ?? $crafting->createBlueprint($character, 'craft_wooden_sword');
        self::prepareCraft($character, $blueprint, ['wood' => 5]);

        return $crafting->craftItem($character, 'craft_wooden_sword', $blueprint->uuid);
    }

    public static function moveCraftedItemToInventory(Character $character, Item $item): Item
    {
        $inventory = $character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $emptySlot = $inventory->slots()->whereNull('slot_type')->orderBy('id')->get()
            ->first(function (Slot $slot) {
                return !\App\Models\Item::where('slot_uuid', $slot->uuid)->exists()
                    && !Resources::where('slot_uuid', $slot->uuid)->exists();
            });

        if (!$emptySlot) {
            throw new \RuntimeException('No free inventory slot');
        }

        if ($item->temporary_slot_uuid) {
            app(StorageMoveService::class)->move($character, $item->temporary_slot_uuid, $emptySlot->uuid);
        } else {
            app(StorageMoveService::class)->move($character, $item->slot_uuid, $emptySlot->uuid);
        }

        return $item->fresh();
    }

    public static function craftWoodenSwordFromInventory(Character $character, ?Item $blueprint = null): Item
    {
        return CraftStationHelper::craftWoodenSwordFromInventory($character, $blueprint);
    }
}
