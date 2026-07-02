<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\TemporarySlot;
use Illuminate\Database\Eloquent\Builder;

/**
 * slot_uuid — каноническая ячейка (slots.uuid или temporary_slots.uuid).
 * buffer_slot_uuid — overlay-ячейка на время операции (только temporary_slots).
 */
final class SlotCellResolver
{
    public function resolve(string $uuid, bool $lock = false): array
    {
        $tempQuery = TemporarySlot::where('uuid', $uuid);
        if ($lock) {
            $tempQuery->lockForUpdate();
        }
        $tempSlot = $tempQuery->first();
        if ($tempSlot) {
            return ['kind' => 'temporary', 'cell' => $tempSlot];
        }

        $slotQuery = Slot::where('uuid', $uuid);
        if ($lock) {
            $slotQuery->lockForUpdate();
        }
        $slot = $slotQuery->first();
        if ($slot) {
            return ['kind' => 'regular', 'cell' => $slot];
        }

        throw new \RuntimeException('Слот не найден');
    }

    public function isTemporaryCell(string $uuid): bool
    {
        return TemporarySlot::where('uuid', $uuid)->exists();
    }

    public function isRegularCell(string $uuid): bool
    {
        return Slot::where('uuid', $uuid)->exists();
    }

    public function getOccupantForCell(string $cellUuid): Item|Resources|null
    {
        if ($this->isTemporaryCell($cellUuid)) {
            return $this->getOccupantForTemporarySlot(
                TemporarySlot::where('uuid', $cellUuid)->firstOrFail()
            );
        }

        return $this->getOccupantForRegularSlot(
            Slot::where('uuid', $cellUuid)->firstOrFail()
        );
    }

    public function getOccupantForRegularSlot(Slot $slot): Item|Resources|null
    {
        $item = Item::where('slot_uuid', $slot->uuid)
            ->whereNull('buffer_slot_uuid')
            ->first();
        if ($item) {
            return $item;
        }

        return Resources::where('slot_uuid', $slot->uuid)
            ->whereNull('buffer_slot_uuid')
            ->first();
    }

    public function getOccupantForTemporarySlot(TemporarySlot $temporarySlot): Item|Resources|null
    {
        $item = Item::where('buffer_slot_uuid', $temporarySlot->uuid)->first();
        if ($item) {
            return $item;
        }

        $resource = Resources::where('buffer_slot_uuid', $temporarySlot->uuid)->first();
        if ($resource) {
            return $resource;
        }

        $item = Item::where('slot_uuid', $temporarySlot->uuid)
            ->whereNull('buffer_slot_uuid')
            ->first();
        if ($item) {
            return $item;
        }

        return Resources::where('slot_uuid', $temporarySlot->uuid)
            ->whereNull('buffer_slot_uuid')
            ->first();
    }

    public function isRegularSlotEmpty(Slot $slot): bool
    {
        return $this->getOccupantForRegularSlot($slot) === null;
    }

    public function isTemporarySlotEmpty(TemporarySlot $temporarySlot): bool
    {
        return $this->getOccupantForTemporarySlot($temporarySlot) === null;
    }

    /**
     * Occupants visually tied to a fixed slot via buffer overlay.
     */
    public function hasBufferedOccupantOnRegularSlot(Slot $slot): bool
    {
        return Item::where('slot_uuid', $slot->uuid)->whereNotNull('buffer_slot_uuid')->exists()
            || Resources::where('slot_uuid', $slot->uuid)->whereNotNull('buffer_slot_uuid')->exists();
    }

    public function itemsInTemporarySlots(array $temporarySlotUuids): Builder
    {
        return Item::whereIn('buffer_slot_uuid', $temporarySlotUuids)
            ->orWhere(function (Builder $q) use ($temporarySlotUuids) {
                $q->whereIn('slot_uuid', $temporarySlotUuids)->whereNull('buffer_slot_uuid');
            });
    }

    public function resourcesInTemporarySlots(array $temporarySlotUuids): Builder
    {
        return Resources::whereIn('buffer_slot_uuid', $temporarySlotUuids)
            ->orWhere(function (Builder $q) use ($temporarySlotUuids) {
                $q->whereIn('slot_uuid', $temporarySlotUuids)->whereNull('buffer_slot_uuid');
            });
    }
}
