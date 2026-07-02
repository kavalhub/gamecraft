<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EncounterLootStationService
{
    public const LOOT_SLOT_COUNT = 8;

    public function __construct(
        private StorageProvisioningService $provisioningService,
        private SlotDepositService $slotDepositService,
        private CurrencyService $currencyService,
        private SlotCellResolver $slotCellResolver,
        private ExperienceService $experienceService,
    ) {}

    public function ensureEncounterLootStorage(Character $character): Storage
    {
        $storage = $this->provisioningService->grantStorage($character, 'encounter_loot');
        $this->provisionTemporarySlots($character, $storage);

        return $storage;
    }

    public function provisionTemporarySlots(Character $character, Storage $storage): void
    {
        if ($storage->storage_type !== 'encounter_loot') {
            return;
        }

        $existing = TemporarySlot::where('storage_uuid', $storage->uuid)
            ->where('character_uuid', $character->uuid)
            ->count();

        for ($i = $existing; $i < self::LOOT_SLOT_COUNT; $i++) {
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
        $storage = $this->ensureEncounterLootStorage($character);

        return TemporarySlot::where('storage_uuid', $storage->uuid)
            ->where('character_uuid', $character->uuid)
            ->where('active', true)
            ->orderBy('slot_index')
            ->get();
    }

    public function hasUnclaimedLoot(Character $character): bool
    {
        foreach ($this->getTemporarySlots($character) as $tempSlot) {
            if ($this->provisioningService->getOccupantForTemporarySlot($tempSlot)) {
                if ($tempSlot->timestamps_end && $tempSlot->timestamps_end->isFuture()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function clearExpiredLoot(Character $character): int
    {
        $cleared = 0;

        foreach ($this->getTemporarySlots($character) as $tempSlot) {
            if (!$tempSlot->timestamps_end || $tempSlot->timestamps_end->isFuture()) {
                continue;
            }

            $occupant = $this->slotCellResolver->getOccupantForTemporarySlot($tempSlot);
            if ($occupant instanceof Item) {
                $occupant->delete();
                $cleared++;
            } elseif ($occupant instanceof Resources) {
                $occupant->delete();
                $cleared++;
            }

            $tempSlot->update(['timestamps_end' => null]);
        }

        return $cleared;
    }

    public function clearAllLoot(Character $character): void
    {
        foreach ($this->getTemporarySlots($character) as $tempSlot) {
            $occupant = $this->slotCellResolver->getOccupantForTemporarySlot($tempSlot);
            if ($occupant) {
                $occupant->delete();
            }
            $tempSlot->update(['timestamps_end' => null]);
        }
    }

  /**
   * @param  array<string, int>  $outputs
   * @return array<string, int>
   */
    public function depositLoot(Character $character, array $outputs, CarbonInterface $expiresAt): array
    {
        $this->clearAllLoot($character);

        $scope = $this->slotDepositService->scopeForEncounterLoot($character, $this);
        $deposited = $this->slotDepositService->depositMany($character, $outputs, $scope, recordEvents: false);

        foreach ($this->getTemporarySlots($character) as $tempSlot) {
            if ($this->provisioningService->getOccupantForTemporarySlot($tempSlot)) {
                $tempSlot->update(['timestamps_end' => $expiresAt]);
            }
        }

        return $deposited;
    }

    public function claimAllToInventory(Character $character): int
    {
        $moveService = app(StorageMoveService::class);
        $inventoryService = app(InventoryService::class);
        $moved = 0;

        foreach ($this->getTemporarySlots($character) as $tempSlot) {
            if ($tempSlot->timestamps_end && $tempSlot->timestamps_end->isPast()) {
                continue;
            }

            $occupant = $this->slotCellResolver->getOccupantForTemporarySlot($tempSlot);
            if (!$occupant) {
                continue;
            }

            if ($occupant instanceof Item) {
                $inventory = $character->storages()->where('storage_type', 'inventory')->firstOrFail();
                $targetSlot = $inventory->slots()->whereNull('slot_type')->orderBy('id')->get()
                    ->first(function (Slot $slot) {
                        return !Item::where('slot_uuid', $slot->uuid)->exists()
                            && !Resources::where('slot_uuid', $slot->uuid)->exists();
                    });

                if ($targetSlot) {
                    $moveService->move($character, $tempSlot->uuid, $targetSlot->uuid);
                } else {
                    $inventoryService->addItem(
                        $character,
                        $occupant->template_slug,
                        $occupant->stage,
                        $occupant->custom_name,
                        $occupant->recipe_slug,
                        $occupant->materials_used,
                        $occupant->stats,
                        'inventory'
                    );
                    $occupant->delete();
                }
                $moved++;

                continue;
            }

            /** @var Resources $resource */
            $resource = $occupant;

            if (in_array($resource->template_slug, ['gold', 'experience'], true)) {
                $templateSlug = $resource->template_slug;
                $quantity = $resource->quantity;
                $resource->delete();
                if ($templateSlug === 'gold') {
                    $this->currencyService->credit($character, $quantity, 'encounter.loot');
                } else {
                    $this->experienceService->credit($character, $quantity, 'encounter.loot');
                }
                $moved++;

                continue;
            }

            $targetSlot = $this->slotDepositService->resolveInventoryTargetSlot($character, $resource);
            $moveService->move($character, $tempSlot->uuid, $targetSlot->uuid);
            $moved++;
        }

        foreach ($this->getTemporarySlots($character) as $tempSlot) {
            $tempSlot->update(['timestamps_end' => null]);
        }

        return $moved;
    }
}
