<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use App\Models\TradeOffer;
class StorageLayoutService
{
    public function __construct(
        private StorageProvisioningService $provisioningService,
        private SpecialSlotService $specialSlotService,
        private CharacterStatsService $characterStatsService,
    ) {}

    /**
     * @param  string[]|null  $include
     */
    public function getCharacterLayout(Character $character, ?array $include = null): array
    {
        $include = $include ?? ['inventory'];
        $this->provisioningService->consolidateInventoryResources($character);
        $this->provisioningService->ensureStartingGold($character);

        $result = [
            'character_uuid' => $character->uuid,
            'character_name' => $character->name,
            'gold' => $this->specialSlotService->getGoldQuantity($character),
            'storages' => [],
        ];

        if (in_array('inventory', $include, true)) {
            $inventory = $character->storages()->where('storage_type', 'inventory')->first();
            if ($inventory) {
                $this->provisioningService->provisionStorageSlots($inventory);
                $result['storages'][] = $this->formatRegularStorage($inventory);
            }
        }

        if (in_array('trade', $include, true)) {
            $activeTrade = TradeOffer::where('status', 'pending')
                ->where(function ($q) use ($character) {
                    $q->where('initiator_uuid', $character->uuid)
                        ->orWhere('partner_uuid', $character->uuid);
                })
                ->first();

            if ($activeTrade) {
                $this->provisioningService->ensureTradeStorage($character);
                $partnerUuid = $activeTrade->initiator_uuid === $character->uuid
                    ? $activeTrade->partner_uuid
                    : $activeTrade->initiator_uuid;
                $partner = Character::where('uuid', $partnerUuid)->first();
                if ($partner) {
                    $this->provisioningService->ensureTradeStorage($partner);
                }
            }

            $tradeStorage = $character->storages()->where('storage_type', 'trade')->first();
            if ($tradeStorage) {
                $result['my_trade_slots'] = $this->formatTradeSlotGrid($character, $tradeStorage);
            } else {
                $result['my_trade_slots'] = $this->emptyTradeSlotGrid();
            }

            if ($activeTrade) {
                $partnerUuid = $activeTrade->initiator_uuid === $character->uuid
                    ? $activeTrade->partner_uuid
                    : $activeTrade->initiator_uuid;
                $partner = Character::where('uuid', $partnerUuid)->first();
                if ($partner) {
                    $partnerTradeStorage = $partner->storages()->where('storage_type', 'trade')->first();
                    $result['partner_trade_slots'] = $partnerTradeStorage
                        ? $this->formatTradeSlotGrid($partner, $partnerTradeStorage)
                        : $this->emptyTradeSlotGrid();
                }
            }
        }

        if (in_array('equipment', $include, true)) {
            $equipment = $character->storages()->where('storage_type', 'equipment')->first();
            if ($equipment) {
                $this->provisioningService->provisionStorageSlots($equipment);
                $result['storages'][] = $this->formatRegularStorage($equipment);
            }
        }

        if (in_array('stats', $include, true) || in_array('equipment', $include, true)) {
            $result['character_stats'] = $this->characterStatsService->ensureFor($character);
        }

        return $result;
    }

    public function formatTradeSlotGrids(Character $character, ?Character $partner = null): array
    {
        $myStorage = $this->provisioningService->ensureTradeStorage($character);
        $result = [
            'my_trade_slots' => $this->formatTradeSlotGrid($character, $myStorage),
        ];

        if ($partner) {
            $partnerStorage = $this->provisioningService->ensureTradeStorage($partner);
            $result['partner_trade_slots'] = $this->formatTradeSlotGrid($partner, $partnerStorage);
        }

        return $result;
    }

    private function formatRegularStorage(Storage $storage): array
    {
        $gridSlots = $this->specialSlotService->getGridSlots($storage);
        $specialSlots = $this->specialSlotService->getSpecialSlots($storage);
        $allSlots = $gridSlots->concat($specialSlots);

        $slotUuids = $allSlots->pluck('uuid');

        $items = Item::whereIn('slot_uuid', $slotUuids)
            ->with('template')
            ->get()
            ->keyBy('slot_uuid');

        $resources = Resources::whereIn('slot_uuid', $slotUuids)
            ->with('template')
            ->get()
            ->keyBy('slot_uuid');

        $defs = collect($this->specialSlotService->getSlotDefinitions($storage))
            ->keyBy('slot_type');

        $formatSlot = function (Slot $slot, int $index) use ($items, $resources, $defs) {
            $item = $items->get($slot->uuid);
            $resource = $resources->get($slot->uuid);
            if ($slot->slot_type === null && $resource && $resource->template_slug === 'gold') {
                $resource = null;
            }
            $policy = $slot->slot_type ? ($defs->get($slot->slot_type) ?? []) : [];

            return [
                'uuid' => $slot->uuid,
                'kind' => 'regular',
                'slot_type' => $slot->slot_type,
                'index' => $index,
                'hidden' => (bool) ($policy['hidden'] ?? false),
                'item' => $item ? $this->formatItem($item) : null,
                'resource' => $resource ? $this->formatResource($resource) : null,
            ];
        };

        return [
            'uuid' => $storage->uuid,
            'storage_type' => $storage->storage_type,
            'name' => $storage->name,
            'cols' => $this->provisioningService->getGridCols($storage->storage_type),
            'grid_slots' => $gridSlots->values()->map(fn (Slot $slot, int $i) => $formatSlot($slot, $i))->all(),
            'special_slots' => $specialSlots->values()->map(fn (Slot $slot, int $i) => $formatSlot($slot, $i))->all(),
            'slots' => $gridSlots->values()->map(fn (Slot $slot, int $i) => $formatSlot($slot, $i))->all(),
        ];
    }

    private function formatTradeSlotGrid(Character $character, Storage $tradeStorage): array
    {
        $tempSlots = TemporarySlot::where('storage_uuid', $tradeStorage->uuid)
            ->where('character_uuid', $character->uuid)
            ->orderBy('slot_index')
            ->get();

        if ($tempSlots->isEmpty()) {
            return $this->emptyTradeSlotGrid();
        }

        $tempUuids = $tempSlots->pluck('uuid');

        $items = Item::whereIn('temporary_slot_uuid', $tempUuids)
            ->with('template')
            ->get()
            ->keyBy('temporary_slot_uuid');

        $resources = Resources::whereIn('temporary_slot_uuid', $tempUuids)
            ->with('template')
            ->get()
            ->keyBy('temporary_slot_uuid');

        return [
            'uuid' => $tradeStorage->uuid,
            'storage_type' => 'trade',
            'name' => $tradeStorage->name,
            'cols' => $this->provisioningService->getGridCols('trade'),
            'slots' => $tempSlots->map(function (TemporarySlot $tempSlot) use ($items, $resources) {
                $item = $items->get($tempSlot->uuid);
                $resource = $resources->get($tempSlot->uuid);

                return [
                    'uuid' => $tempSlot->uuid,
                    'kind' => 'temporary',
                    'slot_index' => $tempSlot->slot_index,
                    'index' => $tempSlot->slot_index,
                    'item' => $item ? $this->formatItem($item) : null,
                    'resource' => $resource ? $this->formatResource($resource) : null,
                ];
            })->values()->all(),
        ];
    }

    private function emptyTradeSlotGrid(): array
    {
        return [
            'uuid' => null,
            'storage_type' => 'trade',
            'name' => 'Обмен',
            'cols' => $this->provisioningService->getGridCols('trade'),
            'slots' => [],
        ];
    }

    private function formatItem(Item $item): array
    {
        $baseStats = $item->template?->base_stats;
        $stats = $item->stats;
        if ((!is_array($stats) || $stats === []) && is_array($baseStats)) {
            $stats = $baseStats;
        }

        return [
            'uuid' => $item->uuid,
            'template_slug' => $item->template_slug,
            'name' => $item->custom_name ?? $item->template?->name,
            'icon' => $item->template?->icon,
            'description' => $item->template?->description,
            'stage' => $item->stage,
            'recipe_slug' => $item->recipe_slug,
            'custom_name' => $item->custom_name,
            'stats' => $stats,
            'base_stats' => $baseStats,
            'slot_type' => $item->slot_type ?? $item->template?->slot_type,
            'materials_used' => $item->materials_used,
            'slot_uuid' => $item->slot_uuid,
            'temporary_slot_uuid' => $item->temporary_slot_uuid,
            'locked' => $item->temporary_slot_uuid !== null,
        ];
    }

    private function formatResource(Resources $resource): array
    {
        return [
            'uuid' => $resource->uuid,
            'template_slug' => $resource->template_slug,
            'name' => $resource->template?->name,
            'icon' => $resource->template?->icon,
            'description' => $resource->template?->description,
            'quantity' => $resource->quantity,
            'max_stack' => $resource->max_stack ?? $resource->template?->max_stack,
            'slot_uuid' => $resource->slot_uuid,
            'temporary_slot_uuid' => $resource->temporary_slot_uuid,
            'is_resource' => true,
            'locked' => $resource->temporary_slot_uuid !== null,
        ];
    }
}
