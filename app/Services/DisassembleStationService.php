<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Storage;
use App\Models\TemporarySlot;
use Illuminate\Support\Collection;

class DisassembleStationService
{
    public const CENTER_SLOT_INDEX = 0;

    public function __construct(
        private StorageProvisioningService $provisioningService,
    ) {}

    public function ensureDisassembleStorage(Character $character): Storage
    {
        $storage = $this->provisioningService->grantStorage($character, 'disassemble');
        $this->provisionTemporarySlots($character, $storage);

        return $storage;
    }

    public function provisionTemporarySlots(Character $character, Storage $storage): void
    {
        if ($storage->storage_type !== 'disassemble') {
            return;
        }

        $existing = TemporarySlot::where('storage_uuid', $storage->uuid)
            ->where('character_uuid', $character->uuid)
            ->count();

        if ($existing < 1) {
            TemporarySlot::create([
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'storage_uuid' => $storage->uuid,
                'character_uuid' => $character->uuid,
                'slot_index' => self::CENTER_SLOT_INDEX,
                'active' => true,
            ]);
        }
    }

    public function getTemporarySlots(Character $character): Collection
    {
        $storage = $this->ensureDisassembleStorage($character);

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
            throw new \RuntimeException('Центральный слот станции разбора не найден');
        }

        return $slot;
    }

    public function isDisassembleTemporarySlot(TemporarySlot $slot): bool
    {
        $storage = Storage::where('uuid', $slot->storage_uuid)->first();

        return $storage?->storage_type === 'disassemble';
    }

    public function slotRole(TemporarySlot $slot): string
    {
        return 'disassemble_center';
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

    public function assertItemOnStation(Item $item, Character $character): void
    {
        if (!$item->temporary_slot_uuid) {
            throw new \RuntimeException('Предмет для разборки должен быть на станции разбора');
        }

        $centerSlot = $this->getCenterTemporarySlot($character);
        if ($item->temporary_slot_uuid !== $centerSlot->uuid) {
            throw new \RuntimeException('Предмет для разборки должен быть в центральном слоте');
        }
    }

    public function assertResourceOnStation(Resources $resource, Character $character): void
    {
        if (!$resource->temporary_slot_uuid) {
            throw new \RuntimeException('Ресурс для разборки должен быть на станции разбора');
        }

        $centerSlot = $this->getCenterTemporarySlot($character);
        if ($resource->temporary_slot_uuid !== $centerSlot->uuid) {
            throw new \RuntimeException('Ресурс для разборки должен быть в центральном слоте');
        }
    }
}
