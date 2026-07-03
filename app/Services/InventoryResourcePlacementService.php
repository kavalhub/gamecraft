<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Services\Slots\ResourcePlacementStep;
use Illuminate\Support\Collection;

class InventoryResourcePlacementService
{
    public function __construct(
        private SpecialSlotService $specialSlotService,
    ) {}

    /**
     * @param  list<string>  $prependEmptyGridSlotUuids
     * @param  list<string>  $reservedSlotUuids
     * @return list<ResourcePlacementStep>
     */
    public function plan(
        Storage $storage,
        string $templateSlug,
        int $quantity,
        array $prependEmptyGridSlotUuids = [],
        array $reservedSlotUuids = [],
    ): array {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Количество должно быть больше 0');
        }

        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
        $maxStack = $template->max_stack;
        $remaining = $quantity;
        $steps = [];

        $orderedSlots = $storage->slots()->orderBy('id')->get();
        $slotOrder = $orderedSlots->pluck('id', 'uuid');

        foreach ($this->findPartialStacks($storage, $templateSlug, $maxStack, $slotOrder, $reservedSlotUuids) as $existing) {
            if ($remaining < 1) {
                break;
            }

            $merged = $this->mergeAmount($existing, $maxStack, $remaining);
            if ($merged < 1) {
                continue;
            }

            $steps[] = new ResourcePlacementStep(
                targetSlotUuid: $existing->slot_uuid,
                quantity: $merged,
                mergeIntoResourceUuid: $existing->uuid,
            );
            $remaining -= $merged;
        }

        if ($remaining < 1) {
            return $steps;
        }

        foreach ($this->priorityFillSlotTypes($storage, $template) as $slotType) {
            if ($remaining < 1) {
                break;
            }

            $typedSlots = $storage->slots()
                ->where('slot_type', $slotType)
                ->orderBy('id')
                ->get();

            foreach ($typedSlots as $slot) {
                if ($remaining < 1) {
                    break;
                }

                if (!$this->isSlotEmpty($slot)) {
                    continue;
                }

                $chunk = $this->chunkSize($maxStack, $remaining);
                $steps[] = new ResourcePlacementStep(
                    targetSlotUuid: $slot->uuid,
                    quantity: $chunk,
                );
                $remaining -= $chunk;
            }
        }

        foreach ($this->emptyGridSlotUuids($storage, $prependEmptyGridSlotUuids, $reservedSlotUuids) as $slotUuid) {
            if ($remaining < 1) {
                break;
            }

            $chunk = $this->chunkSize($maxStack, $remaining);
            $steps[] = new ResourcePlacementStep(
                targetSlotUuid: $slotUuid,
                quantity: $chunk,
            );
            $remaining -= $chunk;
        }

        if ($remaining > 0) {
            throw new \RuntimeException('Недостаточно места в инвентаре для размещения ресурса');
        }

        return $steps;
    }

    public function canFit(
        Storage $storage,
        string $templateSlug,
        int $quantity,
        array $prependEmptyGridSlotUuids = [],
        array $reservedSlotUuids = [],
    ): bool {
        try {
            $this->plan($storage, $templateSlug, $quantity, $prependEmptyGridSlotUuids, $reservedSlotUuids);
        } catch (\RuntimeException) {
            return false;
        }

        return true;
    }

    public function calculateCapacity(Storage $storage, string $templateSlug): int
    {
        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
        $maxStack = $template->max_stack;

        if ($maxStack === null) {
            $slotUuids = $storage->slots()->pluck('uuid');
            $exists = Resources::whereIn('slot_uuid', $slotUuids)
                ->where('template_slug', $templateSlug)
                ->whereNull('buffer_slot_uuid')
                ->exists();

            if ($exists) {
                return PHP_INT_MAX;
            }

            return $this->hasAnyPlacementCell($storage, $template) ? PHP_INT_MAX : 0;
        }

        $capacity = 0;

        foreach ($this->findPartialStacks(
            $storage,
            $templateSlug,
            $maxStack,
            $storage->slots()->orderBy('id')->pluck('id', 'uuid'),
            [],
        ) as $stack) {
            $capacity += max(0, $maxStack - $stack->quantity);
        }

        foreach ($this->priorityFillSlotTypes($storage, $template) as $slotType) {
            $emptyTyped = $storage->slots()
                ->where('slot_type', $slotType)
                ->orderBy('id')
                ->get()
                ->filter(fn (Slot $slot) => $this->isSlotEmpty($slot))
                ->count();

            $capacity += $emptyTyped * $maxStack;
        }

        $emptyGrid = $this->specialSlotService->getGridSlots($storage)
            ->filter(fn (Slot $slot) => $this->isSlotEmpty($slot))
            ->count();

        return $capacity + ($emptyGrid * $maxStack);
    }

  /**
     * @return Collection<int, Resources>
     */
    private function findPartialStacks(
        Storage $storage,
        string $templateSlug,
        ?int $maxStack,
        Collection $slotOrder,
        array $excludeSlotUuids = [],
    ): Collection {
        if ($maxStack !== null && $maxStack < 1) {
            return collect();
        }

        $slotUuids = $storage->slots()->pluck('uuid');
        $exclude = collect($excludeSlotUuids);

        $query = Resources::whereIn('slot_uuid', $slotUuids)
            ->whereNull('buffer_slot_uuid')
            ->where('template_slug', $templateSlug);

        if ($maxStack !== null) {
            $query->where('quantity', '<', $maxStack);
        }

        return $query->get()
            ->filter(fn (Resources $resource) => !$exclude->contains($resource->slot_uuid))
            ->sortBy(fn (Resources $resource) => $slotOrder->get($resource->slot_uuid, PHP_INT_MAX));
    }

    /**
     * @return list<string>
     */
    private function priorityFillSlotTypes(Storage $storage, ItemTemplate $template): array
    {
        $types = [];

        foreach ($this->specialSlotService->getSlotDefinitions($storage) as $def) {
            if ($def['slot_type'] === null) {
                continue;
            }

            if (!$this->specialSlotService->resourceMatchesSlotType($template, $def['slot_type'])) {
                continue;
            }

            $types[] = $def['slot_type'];
        }

        return $types;
    }

    /**
     * @param  list<string>  $prependEmptyGridSlotUuids
     * @param  list<string>  $reservedSlotUuids
     * @return list<string>
     */
    private function emptyGridSlotUuids(
        Storage $storage,
        array $prependEmptyGridSlotUuids,
        array $reservedSlotUuids = [],
    ): array {
        $reserved = collect($reservedSlotUuids);
        $gridSlots = $this->specialSlotService->getGridSlots($storage);

        $prepended = collect($prependEmptyGridSlotUuids)
            ->filter(function (string $uuid) use ($storage, $gridSlots, $reserved) {
                if ($reserved->contains($uuid)) {
                    return false;
                }

                $slot = Slot::where('uuid', $uuid)->first();
                if (!$slot || $slot->storage_uuid !== $storage->uuid) {
                    return false;
                }

                return $gridSlots->contains('uuid', $uuid) && $this->isSlotEmpty($slot);
            });

        $regular = $gridSlots
            ->filter(fn (Slot $slot) => !$reserved->contains($slot->uuid) && $this->isSlotEmpty($slot))
            ->pluck('uuid');

        return $prepended->merge($regular)->unique()->values()->all();
    }

    private function hasAnyPlacementCell(Storage $storage, ItemTemplate $template): bool
    {
        foreach ($this->priorityFillSlotTypes($storage, $template) as $slotType) {
            $hasEmpty = $storage->slots()
                ->where('slot_type', $slotType)
                ->get()
                ->contains(fn (Slot $slot) => $this->isSlotEmpty($slot));

            if ($hasEmpty) {
                return true;
            }
        }

        return $this->specialSlotService->getGridSlots($storage)
            ->contains(fn (Slot $slot) => $this->isSlotEmpty($slot));
    }

    private function isSlotEmpty(Slot $slot): bool
    {
        if (Item::where('slot_uuid', $slot->uuid)->exists()) {
            return false;
        }

        return !Resources::where('slot_uuid', $slot->uuid)->exists();
    }

    private function mergeAmount(Resources $existing, ?int $maxStack, int $remaining): int
    {
        if ($maxStack === null) {
            return $remaining;
        }

        $space = max(0, $maxStack - $existing->quantity);

        return min($remaining, $space);
    }

    private function chunkSize(?int $maxStack, int $remaining): int
    {
        return $maxStack === null ? $remaining : min($remaining, $maxStack);
    }
}
