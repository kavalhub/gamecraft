<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TradeOffer;
use App\Models\TradeItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TradeService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore,
        private ResourceStackingService $stackingService,
        private StorageProvisioningService $provisioningService
    ) {}

    public function createTrade(Character $initiator, Character $partner): TradeOffer
    {
        if ($initiator->uuid === $partner->uuid) {
            throw new \RuntimeException('Нельзя обмениваться с самим собой');
        }

        $initiatorHasActiveTrade = TradeOffer::where('status', 'pending')
            ->where(function ($q) use ($initiator) {
                $q->where('initiator_uuid', $initiator->uuid)
                    ->orWhere('partner_uuid', $initiator->uuid);
            })
            ->exists();

        if ($initiatorHasActiveTrade) {
            throw new \RuntimeException('Вы уже участвуете в другом обмене');
        }

        $partnerHasActiveTrade = TradeOffer::where('status', 'pending')
            ->where(function ($q) use ($partner) {
                $q->where('initiator_uuid', $partner->uuid)
                    ->orWhere('partner_uuid', $partner->uuid);
            })
            ->exists();

        if ($partnerHasActiveTrade) {
            throw new \RuntimeException('Этот игрок уже участвует в другом обмене');
        }

        return DB::transaction(function () use ($initiator, $partner) {
            $this->provisioningService->ensureTradeStorage($initiator);
            $this->provisioningService->ensureTradeStorage($partner);

            $trade = TradeOffer::create([
                'initiator_uuid' => $initiator->uuid,
                'partner_uuid' => $partner->uuid,
                'status' => 'pending',
                'initiator_accepted' => false,
                'partner_accepted' => false,
            ]);

            $this->eventStore->record(
                'trade.created',
                'trade',
                $trade->uuid,
                [
                    'initiator_uuid' => $initiator->uuid,
                    'partner_uuid' => $partner->uuid,
                ],
                $initiator->uuid
            );

            return $trade;
        });
    }

    public function addItemToTrade(Character $character, TradeOffer $trade, string $itemUuid): TradeItem
    {
        return DB::transaction(function () use ($character, $trade, $itemUuid) {
            if (!$this->isParticipant($character, $trade)) {
                throw new \RuntimeException('Вы не участвуете в этом обмене');
            }

            if ($trade->status !== 'pending') {
                throw new \RuntimeException('Обмен не в статусе ожидания');
            }

            $item = Item::where('uuid', $itemUuid)->first();

            if (!$item) {
                throw new \RuntimeException('Предмет не найден');
            }

            if (!in_array($item->stage, ['item', 'blueprint'])) {
                throw new \RuntimeException('Этот предмет нельзя обменять');
            }

            if ($item->temporary_slot_uuid) {
                throw new \RuntimeException('Предмет уже участвует в другом обмене или аукционе');
            }

            $slot = Slot::where('uuid', $item->slot_uuid)->first();
            if (!$slot) {
                throw new \RuntimeException('Слот предмета не найден');
            }

            $storage = Storage::where('uuid', $slot->storage_uuid)->first();
            if (!$storage || $storage->characters_uuid !== $character->uuid) {
                throw new \RuntimeException('Предмет не принадлежит вам');
            }

            $existingTradeItem = TradeItem::where('trade_uuid', $trade->uuid)
                ->where('item_uuid', $itemUuid)
                ->first();

            if ($existingTradeItem) {
                throw new \RuntimeException('Предмет уже в обмене');
            }

            $this->provisioningService->ensureTradeStorage($character);
            $this->provisioningService->ensureTradeStorage($this->getPartner($character, $trade));

            $tradeTempSlot = $this->provisioningService->findFreeTradeTemporarySlot($character);
            if (!$tradeTempSlot) {
                throw new \RuntimeException('Нет свободных слотов в обмене');
            }

            $item->update(['temporary_slot_uuid' => $tradeTempSlot->uuid]);

            $tradeItem = TradeItem::create([
                'trade_uuid' => $trade->uuid,
                'character_uuid' => $character->uuid,
                'item_uuid' => $itemUuid,
                'resource_uuid' => null,
                'quantity' => 1,
            ]);

            $trade->update([
                'initiator_accepted' => false,
                'partner_accepted' => false,
            ]);

            $this->eventStore->record(
                'trade.item_added',
                'trade',
                $trade->uuid,
                [
                    'character_uuid' => $character->uuid,
                    'item_uuid' => $itemUuid,
                    'temporary_slot_uuid' => $tradeTempSlot->uuid,
                ],
                $character->uuid
            );

            $this->recordTradeUpdated($trade, $character);

            return $tradeItem;
        });
    }

    public function addResourceToTrade(Character $character, TradeOffer $trade, string $templateSlug, int $quantity): TradeItem
    {
        return DB::transaction(function () use ($character, $trade, $templateSlug, $quantity) {
            if (!$this->isParticipant($character, $trade)) {
                throw new \RuntimeException('Вы не участвуете в этом обмене');
            }

            if ($trade->status !== 'pending') {
                throw new \RuntimeException('Обмен не в статусе ожидания');
            }

            if ($quantity < 1) {
                throw new \RuntimeException('Количество должно быть больше 0');
            }

            $available = $this->inventoryService->getResourceQuantity($character, $templateSlug);
            if ($available < $quantity) {
                throw new \RuntimeException("Недостаточно ресурса {$templateSlug}: доступно {$available}, нужно {$quantity}");
            }

            $this->provisioningService->ensureTradeStorage($character);
            $this->provisioningService->ensureTradeStorage($this->getPartner($character, $trade));

            $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
            $chunks = $this->stackingService->split($quantity, $template->max_stack);

            $lastTradeItem = null;

            foreach ($chunks as $chunkQty) {
                $tradeTempSlot = $this->provisioningService->findFreeTradeTemporarySlot($character);
                if (!$tradeTempSlot) {
                    throw new \RuntimeException('Нет свободных слотов в обмене');
                }

                $tradeResource = $this->reserveResourceForTrade($character, $templateSlug, $chunkQty);
                $tradeResource->update(['temporary_slot_uuid' => $tradeTempSlot->uuid]);

                $lastTradeItem = TradeItem::create([
                    'trade_uuid' => $trade->uuid,
                    'character_uuid' => $character->uuid,
                    'item_uuid' => null,
                    'resource_uuid' => $tradeResource->uuid,
                    'template_slug' => $templateSlug,
                    'quantity' => $chunkQty,
                ]);
            }

            $trade->update([
                'initiator_accepted' => false,
                'partner_accepted' => false,
            ]);

            $this->eventStore->record(
                'trade.resource_added',
                'trade',
                $trade->uuid,
                [
                    'character_uuid' => $character->uuid,
                    'template_slug' => $templateSlug,
                    'quantity' => $quantity,
                    'stacks' => count($chunks),
                ],
                $character->uuid
            );

            $this->recordTradeUpdated($trade, $character);

            return $lastTradeItem;
        });
    }

    public function confirmTrade(Character $character, TradeOffer $trade): TradeOffer
    {
        return DB::transaction(function () use ($character, $trade) {
            if (!$this->isParticipant($character, $trade)) {
                throw new \RuntimeException('Вы не участвуете в этом обмене');
            }

            if ($trade->status !== 'pending') {
                throw new \RuntimeException('Обмен не в статусе ожидания');
            }

            if ($character->uuid === $trade->initiator_uuid) {
                $trade->update(['initiator_accepted' => true]);
            } else {
                $trade->update(['partner_accepted' => true]);
            }

            $trade->refresh();

            if ($trade->initiator_accepted && $trade->partner_accepted) {
                $this->executeTrade($trade);
            }

            $this->eventStore->record(
                'trade.confirmed',
                'trade',
                $trade->uuid,
                [
                    'character_uuid' => $character->uuid,
                    'initiator_accepted' => $trade->initiator_accepted,
                    'partner_accepted' => $trade->partner_accepted,
                ],
                $character->uuid
            );

            $this->recordTradeUpdated($trade, $character);

            return $trade;
        });
    }

    private function executeTrade(TradeOffer $trade): void
    {
        $tradeItems = TradeItem::where('trade_uuid', $trade->uuid)->get();

        foreach ($tradeItems as $tradeItem) {
            $fromCharacter = Character::where('uuid', $tradeItem->character_uuid)->firstOrFail();
            $toCharacter = $this->getPartner($fromCharacter, $trade);

            if ($tradeItem->item_uuid) {
                $item = Item::where('uuid', $tradeItem->item_uuid)->firstOrFail();
                $this->transferItem($item, $fromCharacter, $toCharacter);
            } elseif ($tradeItem->resource_uuid) {
                $resource = Resources::where('uuid', $tradeItem->resource_uuid)->firstOrFail();
                $this->transferResource($resource, $tradeItem->quantity, $fromCharacter, $toCharacter);
            }
        }

        $trade->update(['status' => 'completed']);

        $this->eventStore->record(
            'trade.completed',
            'trade',
            $trade->uuid,
            [
                'items_count' => $tradeItems->whereNotNull('item_uuid')->count(),
                'resources_count' => $tradeItems->whereNotNull('resource_uuid')->count(),
            ],
            null
        );
    }

    private function transferItem(Item $item, Character $from, Character $to): void
    {
        $toInventory = Storage::where('characters_uuid', $to->uuid)
            ->where('storage_type', 'inventory')
            ->firstOrFail();

        $freeSlot = $this->getOrCreateSlot($toInventory);

        $item->update([
            'slot_uuid' => $freeSlot->uuid,
            'temporary_slot_uuid' => null,
        ]);

        $this->eventStore->record(
            'item.transferred',
            'item',
            $item->uuid,
            [
                'from_character_uuid' => $from->uuid,
                'to_character_uuid' => $to->uuid,
                'to_slot_uuid' => $freeSlot->uuid,
                'reason' => 'trade',
            ],
            null
        );
    }

    private function transferResource(Resources $resource, int $quantity, Character $from, Character $to): void
    {
        $templateSlug = $resource->template_slug;
        $resourceUuid = $resource->uuid;

        $resource->delete();

        app(SpecialSlotService::class)->depositResource($to, $templateSlug, $quantity);

        $this->eventStore->record(
            'resource.transferred',
            'resource',
            $resourceUuid,
            [
                'from_character_uuid' => $from->uuid,
                'to_character_uuid' => $to->uuid,
                'template_slug' => $templateSlug,
                'quantity' => $quantity,
                'reason' => 'trade',
            ],
            null
        );
    }

    public function cancelTrade(Character $character, TradeOffer $trade): TradeOffer
    {
        return DB::transaction(function () use ($character, $trade) {
            if (!$this->isParticipant($character, $trade)) {
                throw new \RuntimeException('Вы не участвуете в этом обмене');
            }

            if ($trade->status !== 'pending') {
                throw new \RuntimeException('Можно отменить только ожидающий обмен');
            }

            $tradeItems = TradeItem::where('trade_uuid', $trade->uuid)->get();

            foreach ($tradeItems as $tradeItem) {
                if ($tradeItem->item_uuid) {
                    $item = Item::where('uuid', $tradeItem->item_uuid)->first();
                    if ($item) {
                        $item->update(['temporary_slot_uuid' => null]);
                    }
                } elseif ($tradeItem->resource_uuid) {
                    $resource = Resources::where('uuid', $tradeItem->resource_uuid)->first();
                    if ($resource) {
                        $resource->update(['temporary_slot_uuid' => null]);
                    }
                }
            }

            TradeItem::where('trade_uuid', $trade->uuid)->delete();

            $trade->update(['status' => 'cancelled']);

            $this->eventStore->record(
                'trade.cancelled',
                'trade',
                $trade->uuid,
                [
                    'cancelled_by' => $character->uuid,
                ],
                $character->uuid
            );

            $this->recordTradeUpdated($trade, $character);

            return $trade;
        });
    }

    public function getCharacterTrades(Character $character): \Illuminate\Support\Collection
    {
        return TradeOffer::where('initiator_uuid', $character->uuid)
            ->orWhere('partner_uuid', $character->uuid)
            ->with(['initiator', 'partner', 'items.item.template', 'items.resource'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function reserveResourceForTrade(Character $character, string $templateSlug, int $quantity): Resources
    {
        $remaining = $quantity;
        $storageUuids = $character->storages()->pluck('uuid');
        $slotUuids = Slot::whereIn('storage_uuid', $storageUuids)->pluck('uuid');

        $resources = Resources::whereIn('slot_uuid', $slotUuids)
            ->where('template_slug', $templateSlug)
            ->whereNull('temporary_slot_uuid')
            ->orderBy('quantity', 'asc')
            ->get();

        $reservedResource = null;

        foreach ($resources as $resource) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $resource->quantity);

            if (!$reservedResource) {
                if ($take === $resource->quantity) {
                    $reservedResource = $resource;
                } else {
                    $resource->update(['quantity' => $resource->quantity - $take]);
                    $reservedResource = Resources::create([
                        'uuid' => Str::uuid()->toString(),
                        'slot_uuid' => $resource->slot_uuid,
                        'recipe_slug' => $resource->recipe_slug,
                        'template_slug' => $resource->template_slug,
                        'slot_type' => $resource->slot_type,
                        'max_stack' => $resource->max_stack,
                        'quantity' => $take,
                    ]);
                }
            } elseif ($take === $resource->quantity) {
                $reservedResource->update(['quantity' => $reservedResource->quantity + $take]);
                $resource->delete();
            } else {
                $resource->update(['quantity' => $resource->quantity - $take]);
                $reservedResource->update(['quantity' => $reservedResource->quantity + $take]);
            }

            $remaining -= $take;
        }

        if ($remaining > 0 || !$reservedResource) {
            throw new \RuntimeException("Не удалось зарезервировать {$quantity} {$templateSlug}");
        }

        return $reservedResource;
    }

    private function getOrCreateSlot(Storage $storage): Slot
    {
        $freeSlot = $this->inventoryService->findFreeSlot($storage);

        if ($freeSlot) {
            return $freeSlot;
        }

        return Slot::create([
            'uuid' => Str::uuid()->toString(),
            'storage_uuid' => $storage->uuid,
            'slot_type' => null,
        ]);
    }

    private function isParticipant(Character $character, TradeOffer $trade): bool
    {
        return $character->uuid === $trade->initiator_uuid || $character->uuid === $trade->partner_uuid;
    }

    private function recordTradeUpdated(TradeOffer $trade, Character $actor): void
    {
        $partner = $this->getPartner($actor, $trade);

        $this->eventStore->record(
            'trade.updated',
            'trade',
            $trade->uuid,
            [
                'character_uuid' => $actor->uuid,
                'partner_uuid' => $partner->uuid,
                'status' => $trade->status,
                'initiator_accepted' => $trade->initiator_accepted,
                'partner_accepted' => $trade->partner_accepted,
            ],
            $actor->uuid
        );
    }

    private function getPartner(Character $character, TradeOffer $trade): Character
    {
        $partnerUuid = $character->uuid === $trade->initiator_uuid
            ? $trade->partner_uuid
            : $trade->initiator_uuid;

        return Character::where('uuid', $partnerUuid)->firstOrFail();
    }
}
