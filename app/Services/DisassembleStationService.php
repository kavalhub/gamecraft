<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DisassembleStationService
{
    public const CENTER_SLOT_INDEX = 0;

    public const OUTPUT_SLOT_COUNT = 8;

    public function __construct(
        private StorageProvisioningService $provisioningService,
        private SlotDepositService $slotDepositService,
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

        $needed = 1 + self::OUTPUT_SLOT_COUNT;
        for ($i = $existing; $i < $needed; $i++) {
            TemporarySlot::create([
                'uuid' => Str::uuid()->toString(),
                'storage_uuid' => $storage->uuid,
                'character_uuid' => $character->uuid,
                'slot_index' => $i,
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

    /**
     * @return Collection<int, TemporarySlot>
     */
    public function getOutputTemporarySlots(Character $character): Collection
    {
        return $this->getTemporarySlots($character)
            ->filter(fn (TemporarySlot $slot) => $slot->slot_index >= 1
                && $slot->slot_index <= self::OUTPUT_SLOT_COUNT)
            ->values();
    }

    public function isDisassembleTemporarySlot(TemporarySlot $slot): bool
    {
        $storage = Storage::where('uuid', $slot->storage_uuid)->first();

        return $storage?->storage_type === 'disassemble';
    }

    public function slotRole(TemporarySlot $slot): string
    {
        if ($slot->slot_index === self::CENTER_SLOT_INDEX) {
            return 'disassemble_center';
        }

        if ($slot->slot_index >= 1 && $slot->slot_index <= self::OUTPUT_SLOT_COUNT) {
            return 'disassemble_output';
        }

        return 'disassemble_output';
    }

    public function isOutputSlot(TemporarySlot $slot): bool
    {
        return $slot->slot_index >= 1 && $slot->slot_index <= self::OUTPUT_SLOT_COUNT;
    }

    /**
     * @param  array<string, int>  $outputs
     * @return array<string, int>
     */
    public function depositOutputs(Character $character, array $outputs): array
    {
        $scope = $this->slotDepositService->scopeForDisassembleOutputs($character, $this);

        return $this->slotDepositService->depositMany($character, $outputs, $scope, recordEvents: false);
    }

    public function returnAllToInventory(Character $character): int
    {
        $moveService = app(StorageMoveService::class);
        $inventoryService = app(InventoryService::class);
        $moved = 0;

        foreach ($this->getTemporarySlots($character) as $tempSlot) {
            $item = Item::where('temporary_slot_uuid', $tempSlot->uuid)->first();
            if ($item) {
                $backingSlot = Slot::where('uuid', $item->slot_uuid)->first();
                $backingStorage = $backingSlot
                    ? Storage::where('uuid', $backingSlot->storage_uuid)->first()
                    : null;
                if ($backingStorage?->storage_type === 'inventory') {
                    $moveService->move($character, $tempSlot->uuid, $item->slot_uuid);
                } else {
                    $inventoryService->addItem(
                        $character,
                        $item->template_slug,
                        $item->stage,
                        $item->custom_name,
                        $item->recipe_slug,
                        $item->materials_used,
                        $item->stats,
                        'inventory'
                    );
                    $item->delete();
                }
                $moved++;

                continue;
            }

            $resource = Resources::where('temporary_slot_uuid', $tempSlot->uuid)->first();
            if (!$resource) {
                continue;
            }

            $backingSlot = Slot::where('uuid', $resource->slot_uuid)->first();
            $backingStorage = $backingSlot
                ? Storage::where('uuid', $backingSlot->storage_uuid)->first()
                : null;
            if ($backingStorage?->storage_type === 'inventory') {
                $moveService->move($character, $tempSlot->uuid, $resource->slot_uuid);
            } else {
                $templateSlug = $resource->template_slug;
                $quantity = $resource->quantity;
                $resource->delete();
                $inventoryService->addResource($character, $templateSlug, $quantity);
            }
            $moved++;
        }

        return $moved;
    }

    public function clearOverlays(Character $character): int
    {
        return $this->returnAllToInventory($character);
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
