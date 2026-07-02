<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\TemporarySlot;

class StorageQuickMoveService
{
    public function __construct(
        private StorageMoveService $moveService,
        private SlotFitService $slotFitService,
        private CraftStationService $craftStationService,
        private DisassembleStationService $disassembleStationService,
        private SpecialSlotService $specialSlotService,
        private StorageProvisioningService $provisioningService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function quickMove(
        Character $character,
        string $fromSlotUuid,
        string $intent,
        ?string $stationMode = null,
        ?int $quantity = null,
    ): array {
        $intent = strtolower(trim($intent));

        if ($intent === 'station_return') {
            return $this->returnFromStation($character, $fromSlotUuid);
        }

        $occupant = $this->resolveOccupantAt($fromSlotUuid);
        if (!$occupant) {
            return ['type' => 'quick_move', 'noop' => true];
        }

        $toSlotUuid = match ($intent) {
            'equip' => $this->resolveEquipTarget($character, $occupant),
            'inventory' => $this->resolveInventoryTarget($character),
            'craft' => $this->resolveCraftTarget($character, $occupant, $stationMode),
            'disassemble' => $this->resolveDisassembleTarget($character, $occupant),
            default => null,
        };

        if (!$toSlotUuid || $toSlotUuid === $fromSlotUuid) {
            return ['type' => 'quick_move', 'noop' => true];
        }

        return $this->moveService->move($character, $fromSlotUuid, $toSlotUuid, $quantity);
    }

    /**
     * @return array<string, mixed>
     */
    private function returnFromStation(Character $character, string $tempSlotUuid): array
    {
        $tempSlot = TemporarySlot::where('uuid', $tempSlotUuid)->first();
        if (!$tempSlot || $tempSlot->character_uuid !== $character->uuid) {
            return ['type' => 'quick_move', 'noop' => true];
        }

        $occupant = $this->provisioningService->getOccupantForTemporarySlot($tempSlot);
        if (!$occupant?->slot_uuid) {
            return ['type' => 'quick_move', 'noop' => true];
        }

        return $this->moveService->move($character, $tempSlotUuid, $occupant->slot_uuid);
    }

    private function resolveOccupantAt(string $slotUuid): Item|Resources|null
    {
        $tempSlot = TemporarySlot::where('uuid', $slotUuid)->first();
        if ($tempSlot) {
            return $this->provisioningService->getOccupantForTemporarySlot($tempSlot);
        }

        $item = Item::where('slot_uuid', $slotUuid)->whereNull('buffer_slot_uuid')->first();
        if ($item) {
            return $item;
        }

        return Resources::where('slot_uuid', $slotUuid)->whereNull('buffer_slot_uuid')->first();
    }

    private function resolveEquipTarget(Character $character, Item|Resources $occupant): ?string
    {
        if (!$occupant instanceof Item) {
            return null;
        }

        $equipment = $character->storages()->where('storage_type', 'equipment')->first();
        if (!$equipment) {
            return null;
        }

        $slotType = $occupant->slot_type ?? $occupant->template?->slot_type;
        if (!$slotType || !str_starts_with($slotType, 'equipment_')) {
            return null;
        }

        $slots = $equipment->slots()->where('slot_type', $slotType)->orderBy('id')->get();
        if ($slotType === 'equipment_ring') {
            $empty = $slots->first(fn ($slot) => !Item::where('slot_uuid', $slot->uuid)->exists());

            return ($empty ?? $slots->first())?->uuid;
        }

        $slot = $slots->first();
        if (!$slot) {
            return null;
        }

        $target = ['kind' => 'regular', 'cell' => $slot];

        return $this->slotFitService->occupantFitsTarget($character, $occupant, $target)
            ? $slot->uuid
            : null;
    }

    private function resolveInventoryTarget(Character $character): ?string
    {
        $inventory = $character->storages()->where('storage_type', 'inventory')->first();
        if (!$inventory) {
            return null;
        }

        foreach ($this->specialSlotService->getGridSlots($inventory) as $slot) {
            if (Item::where('slot_uuid', $slot->uuid)->exists()) {
                continue;
            }

            if (Resources::where('slot_uuid', $slot->uuid)->whereNull('buffer_slot_uuid')->exists()) {
                continue;
            }

            return $slot->uuid;
        }

        return null;
    }

    private function resolveCraftTarget(Character $character, Item|Resources $occupant, ?string $stationMode): ?string
    {
        $this->craftStationService->ensureCraftStorage($character);
        $slots = $this->craftStationService->getTemporarySlots($character);
        $ordered = [];

        $center = $slots->firstWhere('slot_index', CraftStationService::CENTER_SLOT_INDEX);
        if ($center && ($stationMode === null || $stationMode === 'center')) {
            $ordered[] = $center;
        }

        if ($stationMode === null || $stationMode === 'material') {
            foreach ($slots as $slot) {
                if ($slot->slot_index > CraftStationService::CENTER_SLOT_INDEX) {
                    $ordered[] = $slot;
                }
            }
        }

        return $this->firstFittingTemporarySlot($character, $occupant, $ordered);
    }

    private function resolveDisassembleTarget(Character $character, Item|Resources $occupant): ?string
    {
        $this->disassembleStationService->ensureDisassembleStorage($character);
        $center = $this->disassembleStationService->getCenterTemporarySlot($character);

        return $this->firstFittingTemporarySlot($character, $occupant, [$center]);
    }

    /**
     * @param  list<TemporarySlot>  $tempSlots
     */
    private function firstFittingTemporarySlot(Character $character, Item|Resources $occupant, array $tempSlots): ?string
    {
        foreach ($tempSlots as $tempSlot) {
            $target = ['kind' => 'temporary', 'cell' => $tempSlot];
            if (!$this->slotFitService->occupantFitsTarget($character, $occupant, $target)) {
                continue;
            }

            if (!$this->provisioningService->getOccupantForTemporarySlot($tempSlot)) {
                return $tempSlot->uuid;
            }
        }

        if (!$occupant instanceof Resources) {
            return null;
        }

        foreach ($tempSlots as $tempSlot) {
            $target = ['kind' => 'temporary', 'cell' => $tempSlot];
            if (!$this->slotFitService->occupantFitsTarget($character, $occupant, $target)) {
                continue;
            }

            $existing = Resources::where('buffer_slot_uuid', $tempSlot->uuid)
                ->where('template_slug', $occupant->template_slug)
                ->first();

            if (!$existing) {
                continue;
            }

            $maxStack = $existing->max_stack;
            if ($maxStack === null || $existing->quantity < $maxStack) {
                return $tempSlot->uuid;
            }
        }

        return null;
    }
}
