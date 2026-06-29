<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\ItemTemplate;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\StorageType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SpecialSlotService
{
    /**
     * @return array<int, array{slot_type: ?string, count: int, hidden: bool, priority_fill: bool, auto_reclaim: bool}>
     */
    public function getSlotDefinitions(Storage $storage): array
    {
        $typeConfig = StorageType::where('type', $storage->storage_type)->first();
        if (!$typeConfig?->allowed_types) {
            return [];
        }

        $defs = [];
        foreach ($typeConfig->allowed_types['slots'] ?? [] as $def) {
            $defs[] = [
                'slot_type' => $def['slot_type'] ?? null,
                'count' => (int) ($def['count'] ?? 0),
                'hidden' => (bool) ($def['hidden'] ?? false),
                'priority_fill' => (bool) ($def['priority_fill'] ?? false),
                'auto_reclaim' => (bool) ($def['auto_reclaim'] ?? false),
            ];
        }

        return $defs;
    }

    public function getPolicyForSlotType(Storage $storage, ?string $slotType): ?array
    {
        foreach ($this->getSlotDefinitions($storage) as $def) {
            if ($def['slot_type'] === $slotType) {
                return $def;
            }
        }

        return null;
    }

    public function getGridSlots(Storage $storage): Collection
    {
        return $storage->slots()->whereNull('slot_type')->orderBy('id')->get();
    }

    public function getSpecialSlots(Storage $storage): Collection
    {
        return $storage->slots()->whereNotNull('slot_type')->orderBy('id')->get();
    }

    public function isGridSlot(Slot $slot): bool
    {
        return $slot->slot_type === null;
    }

    public function resourceMatchesSlotType(ItemTemplate $template, string $slotType): bool
    {
        if ($template->slug === $slotType) {
            return true;
        }

        return $template->slot_type === $slotType;
    }

    public function getGoldSlot(Storage $storage): ?Slot
    {
        return $storage->slots()->where('slot_type', 'gold')->first();
    }

    public function getGoldQuantity(Character $character, string $storageType = 'inventory'): int
    {
        $storage = $character->storages()->where('storage_type', $storageType)->first();
        if (!$storage) {
            return 0;
        }

        $goldSlot = $this->getGoldSlot($storage);
        if (!$goldSlot) {
            return $this->sumResourceInStorage($storage, 'gold');
        }

        return (int) Resources::where('slot_uuid', $goldSlot->uuid)
            ->where('template_slug', 'gold')
            ->whereNull('temporary_slot_uuid')
            ->sum('quantity');
    }

    public function relocateGoldToSpecialSlot(Character $character, string $storageType = 'inventory'): void
    {
        $storage = $character->storages()->where('storage_type', $storageType)->first();
        if (!$storage) {
            return;
        }

        $goldSlot = $this->getGoldSlot($storage);
        if (!$goldSlot) {
            return;
        }

        $gridUuids = $this->getGridSlots($storage)->pluck('uuid');
        $gridGold = Resources::whereIn('slot_uuid', $gridUuids)
            ->where('template_slug', 'gold')
            ->whereNull('temporary_slot_uuid')
            ->get();

        if ($gridGold->isEmpty()) {
            return;
        }

        $extra = (int) $gridGold->sum('quantity');
        foreach ($gridGold as $row) {
            $row->delete();
        }

        $keeper = Resources::where('slot_uuid', $goldSlot->uuid)
            ->where('template_slug', 'gold')
            ->whereNull('temporary_slot_uuid')
            ->first();

        if ($keeper) {
            $keeper->update(['quantity' => $keeper->quantity + $extra]);
        } elseif ($extra > 0) {
            Resources::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $goldSlot->uuid,
                'recipe_slug' => 'gold',
                'template_slug' => 'gold',
                'slot_type' => 'gold',
                'max_stack' => null,
                'quantity' => $extra,
            ]);
        }
    }

    public function depositResource(
        Character $character,
        string $templateSlug,
        int $quantity,
        string $storageType = 'inventory'
    ): Resources {
        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
        $storage = $character->storages()->where('storage_type', $storageType)->firstOrFail();
        $maxStack = $template->max_stack;

        $remaining = $quantity;
        $lastResource = null;

        $priorityDefs = collect($this->getSlotDefinitions($storage))
            ->filter(fn (array $d) => $d['priority_fill'] && $d['slot_type'] !== null);

        foreach ($priorityDefs as $def) {
            if ($remaining <= 0) {
                break;
            }
            if (!$this->resourceMatchesSlotType($template, $def['slot_type'])) {
                continue;
            }

            [$remaining, $lastResource] = $this->fillSlots(
                $storage,
                $storage->slots()->where('slot_type', $def['slot_type'])->orderBy('id')->get(),
                $template,
                $templateSlug,
                $maxStack,
                $remaining,
                $character,
                $lastResource
            );
        }

        while ($remaining > 0) {
            $existingResource = $this->findPartialStack($storage, $templateSlug, $maxStack, $this->getGridSlots($storage));

            if ($existingResource) {
                $space = $maxStack === null ? $remaining : $maxStack - $existingResource->quantity;
                $toAdd = min($remaining, $space);
                $existingResource->quantity += $toAdd;
                $existingResource->save();
                $remaining -= $toAdd;
                $lastResource = $existingResource;
                app(EventStore::class)->recordResourceEvent(
                    'resources.received',
                    $existingResource->uuid,
                    ['quantity' => $toAdd, 'new_quantity' => $existingResource->quantity, 'template_slug' => $templateSlug],
                    $character->uuid
                );
                continue;
            }

            $slot = app(InventoryService::class)->findFreeSlot($storage);
            if (!$slot) {
                throw new \RuntimeException("Нет свободных слотов в хранилище {$storageType}");
            }

            $toAdd = $maxStack === null ? $remaining : min($remaining, $maxStack);
            $lastResource = Resources::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $slot->uuid,
                'recipe_slug' => $templateSlug,
                'template_slug' => $templateSlug,
                'slot_type' => $template->slot_type,
                'max_stack' => $maxStack,
                'quantity' => $toAdd,
            ]);
            $remaining -= $toAdd;

            app(EventStore::class)->recordResourceEvent(
                'resources.received',
                $lastResource->uuid,
                ['quantity' => $toAdd, 'new_quantity' => $toAdd, 'template_slug' => $templateSlug],
                $character->uuid
            );
        }

        if (!$lastResource) {
            throw new \RuntimeException("Не удалось добавить ресурс {$templateSlug}");
        }

        return $lastResource;
    }

  /**
     * @return array{0: int, 1: ?Resources}
     */
    private function fillSlots(
        Storage $storage,
        Collection $slots,
        ItemTemplate $template,
        string $templateSlug,
        ?int $maxStack,
        int $remaining,
        Character $character,
        ?Resources $lastResource
    ): array {
        while ($remaining > 0) {
            $existingResource = $this->findPartialStack($storage, $templateSlug, $maxStack, $slots);

            if ($existingResource) {
                $space = $maxStack === null ? $remaining : $maxStack - $existingResource->quantity;
                $toAdd = min($remaining, $space);
                $existingResource->quantity += $toAdd;
                $existingResource->save();
                $remaining -= $toAdd;
                $lastResource = $existingResource;
                app(EventStore::class)->recordResourceEvent(
                    'resources.received',
                    $existingResource->uuid,
                    ['quantity' => $toAdd, 'new_quantity' => $existingResource->quantity, 'template_slug' => $templateSlug],
                    $character->uuid
                );
                continue;
            }

            $freeSlot = $this->findEmptySlotIn($slots);
            if (!$freeSlot) {
                break;
            }

            $toAdd = $maxStack === null ? $remaining : min($remaining, $maxStack);
            $lastResource = Resources::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $freeSlot->uuid,
                'recipe_slug' => $templateSlug,
                'template_slug' => $templateSlug,
                'slot_type' => $template->slot_type,
                'max_stack' => $maxStack,
                'quantity' => $toAdd,
            ]);
            $remaining -= $toAdd;

            app(EventStore::class)->recordResourceEvent(
                'resources.received',
                $lastResource->uuid,
                ['quantity' => $toAdd, 'new_quantity' => $toAdd, 'template_slug' => $templateSlug],
                $character->uuid
            );
        }

        return [$remaining, $lastResource];
    }

    private function findPartialStack(
        Storage $storage,
        string $templateSlug,
        ?int $maxStack,
        Collection $slots
    ): ?Resources {
        if ($maxStack !== null && $maxStack < 1) {
            return null;
        }

        $slotUuids = $slots->pluck('uuid');
        $query = Resources::whereIn('slot_uuid', $slotUuids)
            ->where('template_slug', $templateSlug)
            ->whereNull('temporary_slot_uuid');

        if ($maxStack !== null) {
            $query->where('quantity', '<', $maxStack);
        }

        return $query->first();
    }

    private function findEmptySlotIn(Collection $slots): ?Slot
    {
        $slotUuids = $slots->pluck('uuid');
        $occupied = Resources::whereIn('slot_uuid', $slotUuids)->pluck('slot_uuid')
            ->merge(\App\Models\Item::whereIn('slot_uuid', $slotUuids)->pluck('slot_uuid'))
            ->unique();

        return $slots->first(fn (Slot $s) => !$occupied->contains($s->uuid));
    }

    private function sumResourceInStorage(Storage $storage, string $templateSlug): int
    {
        $slotUuids = $storage->slots()->pluck('uuid');

        return (int) Resources::whereIn('slot_uuid', $slotUuids)
            ->where('template_slug', $templateSlug)
            ->whereNull('temporary_slot_uuid')
            ->sum('quantity');
    }

    public function shouldAutoReclaim(Storage $storage, Slot $targetSlot, string $templateSlug): bool
    {
        if ($targetSlot->slot_type !== null) {
            return false;
        }

        $template = ItemTemplate::where('slug', $templateSlug)->first();
        if (!$template) {
            return false;
        }

        foreach ($this->getSlotDefinitions($storage) as $def) {
            if (!$def['auto_reclaim'] || $def['slot_type'] === null) {
                continue;
            }
            if ($this->resourceMatchesSlotType($template, $def['slot_type'])) {
                return true;
            }
        }

        return false;
    }

    public function resolveAutoReclaimTarget(Storage $storage, string $templateSlug): ?Slot
    {
        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();

        foreach ($this->getSlotDefinitions($storage) as $def) {
            if (!$def['auto_reclaim'] || $def['slot_type'] === null) {
                continue;
            }
            if ($this->resourceMatchesSlotType($template, $def['slot_type'])) {
                return $storage->slots()->where('slot_type', $def['slot_type'])->orderBy('id')->first();
            }
        }

        return null;
    }
}
