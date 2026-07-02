<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    public function __construct(
        private EventStore $eventStore,
        private SpecialSlotService $specialSlotService,
        private InventoryResourcePlacementService $placementService,
    ) {}

    public function addResource(
        Character $character,
        string $templateSlug,
        int $quantity,
        ?string $storageType = 'inventory'
    ): Resources {
        $this->assertCurrencyMutationAllowed($templateSlug);

        return DB::transaction(function () use ($character, $templateSlug, $quantity, $storageType) {
            return $this->specialSlotService->depositResource(
                $character,
                $templateSlug,
                $quantity,
                $storageType ?? 'inventory'
            );
        });
    }

    public function getMaxAddableQuantity(
        Character $character,
        string $templateSlug,
        ?string $storageType = 'inventory'
    ): int {
        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();

        if ($template->type === 'material') {
            return $this->getMaxAddableResourceQuantity($character, $templateSlug, $storageType);
        }

        return $this->countFreeSlots($character, $storageType, $template->slot_type);
    }

    public function getMaxAddableResourceQuantity(
        Character $character,
        string $templateSlug,
        ?string $storageType = 'inventory'
    ): int {
        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
        $storage = $character->storages()->where('storage_type', $storageType)->firstOrFail();

        return $this->placementService->calculateCapacity($storage, $templateSlug);
    }

    public function removeResource(
        Character $character,
        string $templateSlug,
        int $quantity
    ): void {
        $this->assertCurrencyMutationAllowed($templateSlug);

        DB::transaction(function () use ($character, $templateSlug, $quantity) {
            $storageUuids = $character->storages()->pluck('uuid');
            $slotUuids = Slot::whereIn('storage_uuid', $storageUuids)->pluck('uuid');

            $resources = Resources::whereIn('slot_uuid', $slotUuids)
                ->where('template_slug', $templateSlug)
                ->get();

            $totalAvailable = $resources->sum('quantity');
            if ($totalAvailable < $quantity) {
                throw new \RuntimeException("Недостаточно ресурса {$templateSlug}: есть {$totalAvailable}, нужно {$quantity}");
            }

            $remaining = $quantity;
            foreach ($resources as $resource) {
                if ($remaining <= 0) {
                    break;
                }

                $toRemove = min($remaining, $resource->quantity);
                $resource->quantity -= $toRemove;
                $remaining -= $toRemove;

                if ($resource->quantity <= 0) {
                    $resourceUuid = $resource->uuid;
                    $resource->delete();
                } else {
                    $resource->save();
                    $resourceUuid = $resource->uuid;
                }

                $this->eventStore->recordResourceEvent(
                    'resources.spent',
                    $resourceUuid,
                    [
                        'quantity' => $toRemove,
                        'new_quantity' => max(0, $resource->quantity),
                        'template_slug' => $templateSlug,
                    ],
                    $character->uuid
                );
            }
        });
    }

    public function getResourceQuantity(Character $character, string $templateSlug): int
    {
        $storageUuids = $character->storages()->where('storage_type', 'inventory')->pluck('uuid');
        $slotUuids = Slot::whereIn('storage_uuid', $storageUuids)->pluck('uuid');

        return (int) Resources::whereIn('slot_uuid', $slotUuids)
            ->where('template_slug', $templateSlug)
            ->whereNull('buffer_slot_uuid')
            ->sum('quantity');
    }

    public function addItem(
        Character $character,
        string $templateSlug,
        string $stage = 'item',
        ?string $customName = null,
        ?string $recipeSlug = null,
        ?array $materialsUsed = null,
        ?array $stats = null,
        ?string $storageType = 'inventory'
    ): Item {
        return DB::transaction(function () use ($character, $templateSlug, $stage, $customName, $recipeSlug, $materialsUsed, $stats, $storageType) {
            $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
            $storage = $character->storages()->where('storage_type', $storageType)->firstOrFail();

            $slot = $this->findFreeSlot($storage, $template->slot_type);

            if (!$slot) {
                throw new \RuntimeException("Нет свободных слотов подходящего типа в хранилище {$storageType}");
            }

            $item = Item::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $slot->uuid,
                'recipe_slug' => $recipeSlug,
                'template_slug' => $templateSlug,
                'custom_name' => $customName,
                'stage' => $stage,
                'slot_type' => $template->slot_type,
                'durability' => 100,
                'materials_used' => $materialsUsed,
                'stats' => $stats ?? $template->base_stats,
            ]);

            $this->eventStore->recordItemEvent(
                'item.created',
                $item->uuid,
                [
                    'template_slug' => $templateSlug,
                    'stage' => $stage,
                    'custom_name' => $customName,
                    'recipe_slug' => $recipeSlug,
                    'materials_used' => $materialsUsed,
                ],
                $character->uuid
            );

            return $item;
        });
    }

    public function removeItem(Character $character, string $itemUuid): void
    {
        DB::transaction(function () use ($character, $itemUuid) {
            $item = Item::where('uuid', $itemUuid)->firstOrFail();

            $slot = Slot::where('uuid', $item->slot_uuid)->firstOrFail();
            $storage = Storage::where('uuid', $slot->storage_uuid)->firstOrFail();

            if ($storage->characters_uuid !== $character->uuid) {
                throw new \RuntimeException('Предмет не принадлежит персонажу');
            }

            $item->delete();

            $this->eventStore->recordItemEvent(
                'item.destroyed',
                $itemUuid,
                [
                    'template_slug' => $item->template_slug,
                    'stage' => $item->stage,
                ],
                $character->uuid
            );
        });
    }

    public function getCharacterItems(Character $character, ?string $storageType = null): Collection
    {
        $storageQuery = Storage::where('characters_uuid', $character->uuid);
        if ($storageType) {
            $storageQuery->where('storage_type', $storageType);
        }
        $storageUuids = $storageQuery->pluck('uuid');
        $slotUuids = Slot::whereIn('storage_uuid', $storageUuids)->pluck('uuid');

        return Item::whereIn('slot_uuid', $slotUuids)->with('template')->get();
    }

    public function getCharacterResources(Character $character, ?string $storageType = null): Collection
    {
        $storageQuery = Storage::where('characters_uuid', $character->uuid);
        if ($storageType) {
            $storageQuery->where('storage_type', $storageType);
        }
        $storageUuids = $storageQuery->pluck('uuid');
        $slotUuids = Slot::whereIn('storage_uuid', $storageUuids)->pluck('uuid');

        return Resources::whereIn('slot_uuid', $slotUuids)->with('template')->get();
    }

    public function countItemsInStorage(
        Character $character,
        string $templateSlug,
        string $stage = 'item',
        string $storageType = 'inventory'
    ): int {
        return $this->getCharacterItems($character, $storageType)
            ->filter(fn (Item $item) => $item->template_slug === $templateSlug
                && $item->stage === $stage
                && $item->buffer_slot_uuid === null)
            ->count();
    }

    public function findFreeSlot(Storage $storage, ?string $slotType = null): ?Slot
    {
        $slotUuids = $storage->slots()->pluck('uuid');

        $occupiedSlotUuids = Item::whereIn('slot_uuid', $slotUuids)
            ->pluck('slot_uuid')
            ->merge(Resources::whereIn('slot_uuid', $slotUuids)->pluck('slot_uuid'));

        $query = $storage->slots()->whereNotIn('uuid', $occupiedSlotUuids);

        if ($slotType !== null) {
            $query->where(function ($q) use ($slotType) {
                $q->whereNull('slot_type')->orWhere('slot_type', $slotType);
            });
        } else {
            $query->whereNull('slot_type');
        }

        return $query->first();
    }

    private function findPartialResourceStack(Storage $storage, string $templateSlug, ?int $maxStack): ?Resources
    {
        if ($maxStack !== null && $maxStack < 1) {
            return null;
        }

        $storageSlotUuids = $storage->slots()->pluck('uuid');

        $query = Resources::whereIn('slot_uuid', $storageSlotUuids)
            ->where('template_slug', $templateSlug);

        if ($maxStack !== null) {
            $query->where('quantity', '<', $maxStack);
        }

        return $query->first();
    }

    private function countFreeSlots(Character $character, string $storageType, ?string $slotType = null): int
    {
        $storage = $character->storages()->where('storage_type', $storageType)->firstOrFail();
        $slotUuids = $storage->slots()->pluck('uuid');

        $occupiedSlotUuids = Item::whereIn('slot_uuid', $slotUuids)
            ->pluck('slot_uuid')
            ->merge(Resources::whereIn('slot_uuid', $slotUuids)->pluck('slot_uuid'));

        $query = $storage->slots()->whereNotIn('uuid', $occupiedSlotUuids);

        if ($slotType !== null) {
            $query->where(function ($q) use ($slotType) {
                $q->whereNull('slot_type')->orWhere('slot_type', $slotType);
            });
        } else {
            $query->whereNull('slot_type');
        }

        return $query->count();
    }

    private function countFreeSlotsInStorage(Storage $storage): int
    {
        $slotUuids = $storage->slots()->pluck('uuid');

        $occupiedSlotUuids = Item::whereIn('slot_uuid', $slotUuids)
            ->pluck('slot_uuid')
            ->merge(Resources::whereIn('slot_uuid', $slotUuids)->pluck('slot_uuid'));

        return $storage->slots()->whereNotIn('uuid', $occupiedSlotUuids)->count();
    }

    private function assertCurrencyMutationAllowed(string $templateSlug): void
    {
        if ($templateSlug === 'gold' && !CurrencyService::isMutationAllowed()) {
            throw new \RuntimeException('Изменение валюты возможно только через игровые операции');
        }

        if ($templateSlug === 'experience' && !ExperienceService::isMutationAllowed()) {
            throw new \RuntimeException('Изменение опыта возможно только через игровые операции');
        }
    }
}
