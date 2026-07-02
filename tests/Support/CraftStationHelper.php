<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Services\CraftStationService;
use App\Services\InventoryService;
use App\Services\StorageMoveService;

final class CraftStationHelper
{
    public static function ensureCraftStation(Character $character)
    {
        return app(CraftStationService::class)->ensureCraftStorage($character);
    }

    public static function placeOnCenterSlot(Character $character, Item $item, string $station = 'craft'): void
    {
        if ($station === 'disassemble') {
            app(\App\Services\DisassembleStationService::class)->ensureDisassembleStorage($character);
            $tempSlot = app(\App\Services\DisassembleStationService::class)->getCenterTemporarySlot($character);
        } else {
            self::ensureCraftStation($character);
            $tempSlot = app(CraftStationService::class)->getCenterTemporarySlot($character);
        }

        $item->refresh();
        if ($item->buffer_slot_uuid === $tempSlot->uuid) {
            return;
        }

        app(StorageMoveService::class)->move($character, $item->slot_uuid, $tempSlot->uuid);
        $item->refresh();
    }

    /**
     * @param  array<string, int>  $materials
     */
    public static function placeMaterials(Character $character, array $materials): void
    {
        $inventoryService = app(InventoryService::class);
        foreach ($materials as $templateSlug => $quantity) {
            $inventoryService->addResource($character, $templateSlug, $quantity);
        }

        self::moveMaterialsToCraftStation($character, $materials);
    }

    /**
     * @param  array<string, int>  $materials
     */
    public static function moveMaterialsToCraftStation(Character $character, array $materials): void
    {
        $moveService = app(StorageMoveService::class);
        self::ensureCraftStation($character);
        $materialSlots = app(CraftStationService::class)->getMaterialTemporarySlots($character);

        foreach ($materials as $templateSlug => $quantity) {
            $inventory = $character->storages()->where('storage_type', 'inventory')->firstOrFail();
            $resource = Resources::query()
                ->whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
                ->where('template_slug', $templateSlug)
                ->whereNull('buffer_slot_uuid')
                ->orderByDesc('quantity')
                ->first();

            if (!$resource) {
                throw new \RuntimeException("Resource {$templateSlug} not found in inventory");
            }

            $template = \App\Models\ItemTemplate::where('slug', $templateSlug)->firstOrFail();
            $specialSlots = app(\App\Services\SpecialSlotService::class);

            $targetSlot = $materialSlots->first(function ($tempSlot) use ($specialSlots, $template, $templateSlug) {
                if (!$tempSlot->slot_type || $tempSlot->slot_type === \App\Services\CraftStationService::DISABLED_SLOT_TYPE) {
                    return false;
                }

                if (!$specialSlots->resourceMatchesSlotType($template, $tempSlot->slot_type)) {
                    return false;
                }

                $occupant = Resources::where('buffer_slot_uuid', $tempSlot->uuid)->first();

                return !$occupant
                    || ($occupant->template_slug === $templateSlug
                        && ($occupant->max_stack === null || $occupant->quantity < $occupant->max_stack));
            });

            if (!$targetSlot) {
                throw new \RuntimeException('No free craft material slot');
            }

            $moveService->move($character, $resource->slot_uuid, $targetSlot->uuid, $quantity);
        }
    }

    public static function prepareCraft(Character $character, Item $blueprint, array $materials): void
    {
        self::placeOnCenterSlot($character, $blueprint, 'craft');
        self::placeMaterials($character, $materials);
    }

    public static function craftWoodenSwordFromInventory(Character $character, ?Item $blueprint = null): Item
    {
        $crafting = app(\App\Services\CraftingService::class);
        $blueprint = $blueprint ?? $crafting->createBlueprint($character, 'craft_wooden_sword');
        self::placeOnCenterSlot($character, $blueprint, 'craft');
        self::moveMaterialsToCraftStation($character, ['wood' => 5]);

        $item = $crafting->craftItem($character, 'craft_wooden_sword', $blueprint->uuid);

        return $item->fresh();
    }
}
