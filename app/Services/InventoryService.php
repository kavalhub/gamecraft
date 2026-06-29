<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    public function __construct(
        private EventStore $eventStore
    ) {}

    public function addResource(
        Character $character,
        string $templateSlug,
        int $quantity,
        ?string $storageType = 'inventory'
    ): Resource {
        return DB::transaction(function () use ($character, $templateSlug, $quantity, $storageType) {
            $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
            $storage = $character->storages()->where('storage_type', $storageType)->firstOrFail();
            
            $storageSlotUuids = $storage->slots()->pluck('uuid');
            
            // Ищем существующий ресурс для стака
            $existingResource = Resource::whereIn('slot_uuid', $storageSlotUuids)
                ->where('template_slug', $templateSlug)
                ->first();

            if ($existingResource) {
                $existingResource->quantity += $quantity;
                $existingResource->save();
                
                $this->eventStore->recordResourceEvent(
                    'resource.received',
                    $existingResource->uuid,
                    [
                        'quantity' => $quantity,
                        'new_quantity' => $existingResource->quantity,
                        'template_slug' => $templateSlug,
                    ],
                    $character->uuid
                );

                return $existingResource;
            }

            // Находим свободный слот
            $slot = $this->findFreeSlot($storage);

            if (!$slot) {
                throw new \RuntimeException("Нет свободных слотов в хранилище {$storageType}");
            }

            $resource = Resource::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $slot->uuid,
                'recipe_slug' => $templateSlug,
                'template_slug' => $templateSlug,
                'slot_type' => $template->slot_type,
                'max_stack' => $template->max_stack,
                'quantity' => $quantity,
            ]);

            $this->eventStore->recordResourceEvent(
                'resource.received',
                $resource->uuid,
                [
                    'quantity' => $quantity,
                    'new_quantity' => $quantity,
                    'template_slug' => $templateSlug,
                ],
                $character->uuid
            );

            return $resource;
        });
    }

    public function removeResource(
        Character $character,
        string $templateSlug,
        int $quantity
    ): void {
        DB::transaction(function () use ($character, $templateSlug, $quantity) {
            $storageUuids = $character->storages()->pluck('uuid');
            $slotUuids = Slot::whereIn('storage_uuid', $storageUuids)->pluck('uuid');
            
            $resources = Resource::whereIn('slot_uuid', $slotUuids)
                ->where('template_slug', $templateSlug)
                ->get();

            $totalAvailable = $resources->sum('quantity');
            if ($totalAvailable < $quantity) {
                throw new \RuntimeException("Недостаточно ресурса {$templateSlug}: есть {$totalAvailable}, нужно {$quantity}");
            }

            $remaining = $quantity;
            foreach ($resources as $resource) {
                if ($remaining <= 0) break;

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
                    'resource.spent',
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
        $storageUuids = $character->storages()->pluck('uuid');
        $slotUuids = Slot::whereIn('storage_uuid', $storageUuids)->pluck('uuid');
        
        return (int) Resource::whereIn('slot_uuid', $slotUuids)
            ->where('template_slug', $templateSlug)
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
            
            // Находим свободный слот подходящего типа
            $slot = $this->findFreeSlot($storage, $template->slot_type);

            if (!$slot) {
                throw new \RuntimeException("Нет свободных слотов подходящего типа в хранилище {$storageType}");
            }

            $item = Item::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $slot->uuid,
                'recipe_slug' => $recipeSlug, // может быть null
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
                throw new \RuntimeException("Предмет не принадлежит персонажу");
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

        return Resource::whereIn('slot_uuid', $slotUuids)->with('template')->get();
    }

    public function findFreeSlot(Storage $storage, ?string $slotType = null): ?Slot
    {
        $slotUuids = $storage->slots()->pluck('uuid');

        $occupiedSlotUuids = Item::whereIn('slot_uuid', $slotUuids)
            ->pluck('slot_uuid')
            ->merge(Resource::whereIn('slot_uuid', $slotUuids)->pluck('slot_uuid'));

        $query = $storage->slots()->whereNotIn('uuid', $occupiedSlotUuids);

        if ($slotType !== null) {
            $query->where(function ($q) use ($slotType) {
                $q->whereNull('slot_type')->orWhere('slot_type', $slotType);
            });
        }

        return $query->first();
    }
}
