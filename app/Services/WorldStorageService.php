<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorldStorageService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore,
    ) {}

    public function ensureWorldStorage(): Storage
    {
        $system = Character::where('character_type', 'system')->firstOrFail();

        return Storage::firstOrCreate(
            ['characters_uuid' => $system->uuid, 'storage_type' => 'world'],
            ['name' => 'Мир', 'active' => true]
        );
    }

    public function depositItem(Item $item): void
    {
        DB::transaction(function () use ($item) {
            $item = Item::where('uuid', $item->uuid)->lockForUpdate()->firstOrFail();

            if ($item->buffer_slot_uuid !== null) {
                throw new \RuntimeException('Предмет занят и не может быть выброшен в мир');
            }

            $worldSlot = $this->allocateWorldSlot();
            $item->update([
                'slot_uuid' => $worldSlot->uuid,
                'buffer_slot_uuid' => null,
            ]);

            $this->eventStore->record(
                'item.dropped',
                'item',
                $item->uuid,
                [
                    'template_slug' => $item->template_slug,
                    'stage' => $item->stage,
                    'to_storage' => 'world',
                ],
                null
            );
        });
    }

    public function claimItem(Character $to, string $templateSlug, string $stage): ?Item
    {
        return DB::transaction(function () use ($to, $templateSlug, $stage) {
            $worldStorage = $this->ensureWorldStorage();
            $worldSlotUuids = $worldStorage->slots()->pluck('uuid');

            $item = Item::query()
                ->whereIn('slot_uuid', $worldSlotUuids)
                ->where('template_slug', $templateSlug)
                ->where('stage', $stage)
                ->whereNull('buffer_slot_uuid')
                ->lockForUpdate()
                ->first();

            if (!$item) {
                return null;
            }

            $inventory = $to->storages()->where('storage_type', 'inventory')->firstOrFail();
            $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
            $targetSlot = $this->inventoryService->findFreeSlot($inventory, $template->slot_type);

            if (!$targetSlot) {
                throw new \RuntimeException('Нет свободных слотов в инвентаре');
            }

            $item->update(['slot_uuid' => $targetSlot->uuid]);

            $this->eventStore->record(
                'item.claimed_from_world',
                'item',
                $item->uuid,
                [
                    'template_slug' => $templateSlug,
                    'stage' => $stage,
                    'to_character_uuid' => $to->uuid,
                ],
                $to->uuid
            );

            return $item->fresh();
        });
    }

    public function findAvailableItem(string $templateSlug, string $stage): ?Item
    {
        $worldStorage = $this->ensureWorldStorage();
        $worldSlotUuids = $worldStorage->slots()->pluck('uuid');

        return Item::query()
            ->whereIn('slot_uuid', $worldSlotUuids)
            ->where('template_slug', $templateSlug)
            ->where('stage', $stage)
            ->whereNull('buffer_slot_uuid')
            ->first();
    }

    public function dropFromInventory(Character $character, string $itemUuid): Item
    {
        return DB::transaction(function () use ($character, $itemUuid) {
            $item = Item::where('uuid', $itemUuid)->lockForUpdate()->firstOrFail();

            if ($item->buffer_slot_uuid !== null) {
                throw new \RuntimeException('Предмет занят и не может быть выброшен');
            }

            $slot = Slot::where('uuid', $item->slot_uuid)->firstOrFail();
            $storage = Storage::where('uuid', $slot->storage_uuid)->firstOrFail();

            if ($storage->characters_uuid !== $character->uuid || $storage->storage_type !== 'inventory') {
                throw new \RuntimeException('Предмет не в вашем инвентаре');
            }

            $this->depositItem($item);

            return $item->fresh();
        });
    }

    public function isQuestItem(Item $item): bool
    {
        if ($item->stage === 'quest_item') {
            return true;
        }

        $template = ItemTemplate::where('slug', $item->template_slug)->first();

        return $template?->type === 'quest_item';
    }

    public function isInstanceTemplate(ItemTemplate $template): bool
    {
        return $template->type !== 'material';
    }

    public function stageForTemplate(ItemTemplate $template): string
    {
        return match ($template->type) {
            'blueprint' => 'blueprint',
            'quest_item' => 'quest_item',
            default => 'item',
        };
    }

    private function allocateWorldSlot(): Slot
    {
        $worldStorage = $this->ensureWorldStorage();

        $occupiedUuids = Item::whereIn('slot_uuid', $worldStorage->slots()->pluck('uuid'))
            ->pluck('slot_uuid')
            ->merge(
                Resources::whereIn('slot_uuid', $worldStorage->slots()->pluck('uuid'))
                    ->pluck('slot_uuid')
            );

        $free = $worldStorage->slots()
            ->whereNotIn('uuid', $occupiedUuids)
            ->first();

        if ($free) {
            return $free;
        }

        return Slot::create([
            'uuid' => Str::uuid()->toString(),
            'storage_uuid' => $worldStorage->uuid,
            'slot_type' => null,
        ]);
    }
}
