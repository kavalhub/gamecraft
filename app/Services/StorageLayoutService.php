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
        private CraftStationService $craftStationService,
        private DisassembleStationService $disassembleStationService,
        private CorpseLootService $corpseLootService,
        private QuestStorageService $questStorageService,
        private SlotCellResolver $slotCellResolver,
    ) {}

    /**
     * @param  string[]|null  $include
     */
    public function getCharacterLayout(Character $character, ?array $include = null, ?string $corpseUuid = null): array
    {
        $include = $include ?? ['inventory'];
        $this->provisioningService->consolidateInventoryResources($character);

        $result = [
            'character_uuid' => $character->uuid,
            'character_name' => $character->name,
            'gold' => $this->specialSlotService->getGoldQuantity($character),
            'experience' => $this->specialSlotService->getExperienceQuantity($character),
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
                        ? $this->formatTradeSlotGrid($partner, $partnerTradeStorage, 'deny')
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

        if (in_array('craft', $include, true)) {
            $craft = $this->craftStationService->ensureCraftStorage($character);
            $result['storages'][] = $this->formatStationSlotGrid($character, $craft, $this->craftStationService);
        }

        if (in_array('disassemble', $include, true)) {
            $disassemble = $this->disassembleStationService->ensureDisassembleStorage($character);
            $result['storages'][] = $this->formatStationSlotGrid($character, $disassemble, $this->disassembleStationService);
        }

        if (in_array('corpse', $include, true) && $corpseUuid) {
            $corpse = Character::where('uuid', $corpseUuid)
                ->where('character_type', 'corpse')
                ->first();
            if ($corpse) {
                $corpseStorage = $this->corpseLootService->ensureCorpseStorage($corpse);
                $result['corpse_uuid'] = $corpseUuid;
                $result['storages'][] = $this->formatCorpseGrid($corpse, $corpseStorage);
            }
        }

        if (in_array('quest', $include, true)) {
            $questStorage = $this->questStorageService->ensureQuestStorage($character);
            $result['quest_storage'] = $this->formatQuestSlotGrid($character, $questStorage);
        }

        if (in_array('stats', $include, true) || in_array('equipment', $include, true)) {
            $this->characterStatsService->syncLevelFromExperience($character);
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
            $result['partner_trade_slots'] = $this->formatTradeSlotGrid($partner, $partnerStorage, 'deny');
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
            if ($slot->slot_type === null && $resource && in_array($resource->template_slug, ['gold', 'experience'], true)) {
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

    private function formatTradeSlotGrid(Character $character, Storage $tradeStorage, ?string $dropPolicy = null): array
    {
        $slots = $tradeStorage->slots()
            ->whereNull('slot_type')
            ->orderBy('id')
            ->get();

        if ($slots->isEmpty()) {
            return $this->emptyTradeSlotGrid();
        }

        $slotUuids = $slots->pluck('uuid');

        $items = Item::whereIn('slot_uuid', $slotUuids)
            ->whereNull('buffer_slot_uuid')
            ->with('template')
            ->get()
            ->keyBy('slot_uuid');

        $resources = Resources::whereIn('slot_uuid', $slotUuids)
            ->whereNull('buffer_slot_uuid')
            ->with('template')
            ->get()
            ->keyBy('slot_uuid');

        return [
            'uuid' => $tradeStorage->uuid,
            'storage_type' => 'trade',
            'name' => $tradeStorage->name,
            'cols' => $this->provisioningService->getGridCols('trade'),
            'slots' => $slots->map(function (Slot $slot, int $index) use ($items, $resources, $dropPolicy) {
                $item = $items->get($slot->uuid);
                $resource = $resources->get($slot->uuid);

                $formatted = [
                    'uuid' => $slot->uuid,
                    'kind' => 'regular',
                    'slot_index' => $index,
                    'index' => $index,
                    'item' => $item ? $this->formatItem($item) : null,
                    'resource' => $resource ? $this->formatResource($resource) : null,
                ];

                if ($dropPolicy !== null) {
                    $formatted['drop_policy'] = $dropPolicy;
                }

                return $formatted;
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

    private function formatStationSlotGrid(Character $character, Storage $storage, CraftStationService|DisassembleStationService $stationService): array
    {
        $tempSlots = $stationService->getTemporarySlots($character);
        $cols = 4;

        if ($tempSlots->isEmpty()) {
            return [
                'uuid' => $storage->uuid,
                'storage_type' => $storage->storage_type,
                'name' => $storage->name,
                'cols' => $cols,
                'slots' => [],
                'special_slots' => [],
            ];
        }

        $slots = $tempSlots->map(function (TemporarySlot $tempSlot) {
            $occ = $this->occupantPayloadForTemporarySlot($tempSlot);

            return [
                'uuid' => $tempSlot->uuid,
                'kind' => 'temporary',
                'slot_type' => $tempSlot->slot_type,
                'slot_index' => $tempSlot->slot_index,
                'index' => $tempSlot->slot_index,
                'item' => $occ['item'],
                'resource' => $occ['resource'],
            ];
        })->values()->all();

        return [
            'uuid' => $storage->uuid,
            'storage_type' => $storage->storage_type,
            'name' => $storage->name,
            'cols' => $cols,
            'slots' => $slots,
            'special_slots' => $slots,
        ];
    }

    /**
     * @return array{item: ?array, resource: ?array}
     */
    private function occupantPayloadForTemporarySlot(TemporarySlot $tempSlot): array
    {
        $occupant = $this->slotCellResolver->getOccupantForTemporarySlot($tempSlot);
        if ($occupant instanceof Item) {
            return [
                'item' => $this->formatItem($occupant, true),
                'resource' => null,
            ];
        }
        if ($occupant instanceof Resources) {
            return [
                'item' => null,
                'resource' => $this->formatResource($occupant, true),
            ];
        }

        return ['item' => null, 'resource' => null];
    }

    private function formatCorpseGrid(Character $corpse, Storage $storage): array
    {
        $tempSlots = $this->corpseLootService->getLootSlots($corpse);
        $cols = 4;

        if ($tempSlots->isEmpty()) {
            return [
                'uuid' => $storage->uuid,
                'storage_type' => $storage->storage_type,
                'name' => $storage->name,
                'cols' => $cols,
                'slots' => [],
                'special_slots' => [],
            ];
        }

        $claimExpiresAt = $tempSlots
            ->filter(fn (TemporarySlot $slot) => $slot->timestamps_end !== null)
            ->max('timestamps_end');

        $slots = $tempSlots->map(function (TemporarySlot $tempSlot) {
            $occ = $this->occupantPayloadForTemporarySlot($tempSlot);

            return [
                'uuid' => $tempSlot->uuid,
                'kind' => 'temporary',
                'slot_type' => $tempSlot->slot_type,
                'slot_index' => $tempSlot->slot_index,
                'index' => $tempSlot->slot_index,
                'character_uuid' => $tempSlot->character_uuid,
                'timestamps_end' => $tempSlot->timestamps_end?->toIso8601String(),
                'item' => $occ['item'],
                'resource' => $occ['resource'],
            ];
        })->values()->all();

        return [
            'uuid' => $storage->uuid,
            'storage_type' => $storage->storage_type,
            'name' => $storage->name,
            'cols' => $cols,
            'slots' => $slots,
            'special_slots' => $slots,
            'claim_expires_at' => $claimExpiresAt?->toIso8601String(),
        ];
    }

    private function formatQuestSlotGrid(Character $character, Storage $questStorage): array
    {
        $tempSlots = $this->questStorageService->getTemporarySlots($character);

        if ($tempSlots->isEmpty()) {
            return [
                'uuid' => $questStorage->uuid,
                'storage_type' => 'quest',
                'name' => $questStorage->name,
                'cols' => 6,
                'grant_slots' => [],
                'turnin_slots' => [],
                'slots' => [],
            ];
        }

        $grantSlots = [];
        $turninSlots = [];

        foreach ($tempSlots as $tempSlot) {
            $occ = $this->occupantPayloadForTemporarySlot($tempSlot);
            $slotType = $this->questStorageService->slotRole($tempSlot);
            $formatted = [
                'uuid' => $tempSlot->uuid,
                'kind' => 'temporary',
                'slot_type' => $slotType,
                'slot_index' => $tempSlot->slot_index,
                'index' => $tempSlot->slot_index,
                'item' => $occ['item'],
                'resource' => $occ['resource'],
            ];

            if ($slotType === 'quest_grant') {
                $grantSlots[] = $formatted;
            } elseif ($slotType === 'quest_turnin') {
                $turninSlots[] = $formatted;
            }
        }

        return [
            'uuid' => $questStorage->uuid,
            'storage_type' => 'quest',
            'name' => $questStorage->name,
            'cols' => 6,
            'grant_slots' => $grantSlots,
            'turnin_slots' => $turninSlots,
            'slots' => array_merge($grantSlots, $turninSlots),
        ];
    }

    private function formatItem(Item $item, bool $asOverlayDestination = false): array
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
            'quest_slug' => $item->template?->quest_slug,
            'materials_used' => $item->materials_used,
            'slot_uuid' => $item->slot_uuid,
            'buffer_slot_uuid' => $item->buffer_slot_uuid,
            'locked' => !$asOverlayDestination && $item->buffer_slot_uuid !== null,
        ];
    }

    private function formatResource(Resources $resource, bool $asOverlayDestination = false): array
    {
        return [
            'uuid' => $resource->uuid,
            'template_slug' => $resource->template_slug,
            'name' => $resource->template?->name,
            'icon' => $resource->template?->icon,
            'description' => $resource->template?->description,
            'quantity' => $resource->quantity,
            'max_stack' => $resource->max_stack ?? $resource->template?->max_stack,
            'slot_type' => $resource->slot_type ?? $resource->template?->slot_type,
            'slot_uuid' => $resource->slot_uuid,
            'buffer_slot_uuid' => $resource->buffer_slot_uuid,
            'is_resource' => true,
            'locked' => !$asOverlayDestination && $resource->buffer_slot_uuid !== null,
        ];
    }
}
