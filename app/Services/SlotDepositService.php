<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\ItemTemplate;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Services\Slots\RegularSlotScope;
use App\Services\Slots\ResourcePlacementStep;
use App\Services\Slots\SlotScope;
use App\Services\Slots\TemporarySlotScope;
use Illuminate\Support\Collection;

class SlotDepositService
{
    public function __construct(
        private EventStore $eventStore,
        private InventoryService $inventoryService,
        private SpecialSlotService $specialSlotService,
        private InventoryResourcePlacementService $placementService,
    ) {}

    public function depositToInventory(
        Character $character,
        string $templateSlug,
        int $quantity,
        string $storageType = 'inventory'
    ): Resources {
        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
        $storage = $character->storages()->where('storage_type', $storageType)->firstOrFail();

        $steps = $this->placementService->plan($storage, $templateSlug, $quantity);
        $lastResource = null;

        foreach ($steps as $step) {
            $lastResource = $this->applyPlacementStep($character, $templateSlug, $template, $step);
        }

        if (!$lastResource) {
            throw new \RuntimeException("Не удалось добавить ресурс {$templateSlug}");
        }

        return $lastResource;
    }

    /**
     * @param  array<string, int>  $outputs
     * @return array<string, int>
     */
    public function depositMany(Character $character, array $outputs, SlotScope $scope, bool $recordEvents = true): array
    {
        $deposited = [];

        foreach ($outputs as $templateSlug => $quantity) {
            if ($quantity < 1) {
                continue;
            }

            $this->deposit($character, (string) $templateSlug, (int) $quantity, $scope, $recordEvents);
            $deposited[$templateSlug] = ($deposited[$templateSlug] ?? 0) + (int) $quantity;
        }

        return $deposited;
    }

    public function deposit(
        Character $character,
        string $templateSlug,
        int $quantity,
        SlotScope $scope,
        bool $recordEvents = true
    ): Resources {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Количество должно быть больше 0');
        }

        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
        $maxStack = $template->max_stack;
        $remaining = $quantity;
        $lastResource = null;

        while ($remaining > 0) {
            $existingResource = $scope->findPartialStack($templateSlug, $maxStack);

            if ($existingResource) {
                [$remaining, $lastResource] = $this->addToPartialStack(
                    $character,
                    $templateSlug,
                    $remaining,
                    $existingResource,
                    $maxStack,
                    $lastResource,
                    $recordEvents
                );
                continue;
            }

            $cell = $scope->findEmptyCell();
            if (!$cell) {
                throw new \RuntimeException($scope->exhaustedMessage());
            }

            $toAdd = $maxStack === null ? $remaining : min($remaining, $maxStack);
            $lastResource = $scope->createResource($character, $template, $templateSlug, $toAdd, $cell);
            $remaining -= $toAdd;

            if ($recordEvents) {
                $this->recordReceived($character, $lastResource, $templateSlug, $toAdd);
            }
        }

        if (!$lastResource) {
            throw new \RuntimeException("Не удалось добавить ресурс {$templateSlug}");
        }

        return $lastResource;
    }

    public function resolveInventoryTargetSlot(Character $character, Resources $resource): Slot
    {
        $storage = $character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $template = ItemTemplate::where('slug', $resource->template_slug)->firstOrFail();
        $scope = RegularSlotScope::forStorageGrid($storage, $this->specialSlotService->getGridSlots($storage));

        $partial = $scope->findPartialStack($resource->template_slug, $template->max_stack);
        if ($partial) {
            return Slot::where('uuid', $partial->slot_uuid)->firstOrFail();
        }

        return $this->inventoryService->findFreeSlot($storage);
    }

    public function scopeForDisassembleOutputs(Character $character, DisassembleStationService $station): TemporarySlotScope
    {
        $storage = $station->ensureDisassembleStorage($character);

        return TemporarySlotScope::forTemporarySlots(
            $storage,
            $station->getOutputTemporarySlots($character),
            'disassemble_backing'
        );
    }

    public function scopeForQuestTemporarySlots(Storage $questStorage, Collection $temporarySlots): TemporarySlotScope
    {
        return TemporarySlotScope::forTemporarySlots(
            $questStorage,
            $temporarySlots,
            'quest_backing'
        );
    }

    private function applyPlacementStep(
        Character $character,
        string $templateSlug,
        ItemTemplate $template,
        ResourcePlacementStep $step,
    ): Resources {
        if ($step->mergeIntoResourceUuid !== null) {
            $existing = Resources::where('uuid', $step->mergeIntoResourceUuid)->firstOrFail();
            [$_, $lastResource] = $this->addToPartialStack(
                $character,
                $templateSlug,
                $step->quantity,
                $existing,
                $template->max_stack,
                $existing,
            );

            return $lastResource;
        }

        $slot = Slot::where('uuid', $step->targetSlotUuid)->firstOrFail();
        $scope = RegularSlotScope::forStorageGrid(
            $slot->storage,
            collect([$slot]),
        );
        $resource = $scope->createResource(
            $character,
            $template,
            $templateSlug,
            $step->quantity,
            $slot,
        );
        $this->recordReceived($character, $resource, $templateSlug, $step->quantity);

        return $resource;
    }

    /**
     * @return array{0: int, 1: ?Resources}
     */
    private function depositAmount(
        Character $character,
        string $templateSlug,
        int $remaining,
        SlotScope $scope,
        ?Resources $lastResource
    ): array {
        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
        $maxStack = $template->max_stack;

        while ($remaining > 0) {
            $existingResource = $scope->findPartialStack($templateSlug, $maxStack);

            if ($existingResource) {
                [$remaining, $lastResource] = $this->addToPartialStack(
                    $character,
                    $templateSlug,
                    $remaining,
                    $existingResource,
                    $maxStack,
                    $lastResource
                );
                continue;
            }

            $cell = $scope->findEmptyCell();
            if (!$cell) {
                break;
            }

            $toAdd = $maxStack === null ? $remaining : min($remaining, $maxStack);
            $lastResource = $scope->createResource($character, $template, $templateSlug, $toAdd, $cell);
            $remaining -= $toAdd;
            $this->recordReceived($character, $lastResource, $templateSlug, $toAdd);
        }

        return [$remaining, $lastResource];
    }

    /**
     * @return array{0: int, 1: Resources}
     */
    private function addToPartialStack(
        Character $character,
        string $templateSlug,
        int $remaining,
        Resources $existingResource,
        ?int $maxStack,
        ?Resources $lastResource,
        bool $recordEvents = true
    ): array {
        $space = $maxStack === null ? $remaining : $maxStack - $existingResource->quantity;
        $toAdd = min($remaining, $space);
        $existingResource->quantity += $toAdd;
        $existingResource->save();
        $remaining -= $toAdd;
        $lastResource = $existingResource;

        if ($recordEvents) {
            $this->recordReceived($character, $existingResource, $templateSlug, $toAdd);
        }

        return [$remaining, $lastResource];
    }

    private function recordReceived(
        Character $character,
        Resources $resource,
        string $templateSlug,
        int $quantity
    ): void {
        $this->eventStore->recordResourceEvent(
            'resources.received',
            $resource->uuid,
            ['quantity' => $quantity, 'new_quantity' => $resource->quantity, 'template_slug' => $templateSlug],
            $character->uuid
        );
    }
}
