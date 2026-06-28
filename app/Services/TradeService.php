<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use App\Models\TradeOffer;
use App\Models\TradeItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TradeService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore
    ) {}

    public function createTrade(Character $initiator, Character $partner): TradeOffer
    {
        if ($initiator->uuid === $partner->uuid) {
            throw new \RuntimeException('Нельзя обмениваться с самим собой');
        }

        // Проверяем что инициатор не участвует в других активных обменах
        $initiatorHasActiveTrade = TradeOffer::where('status', 'pending')
            ->where(function ($q) use ($initiator) {
                $q->where('initiator_uuid', $initiator->uuid)
                  ->orWhere('partner_uuid', $initiator->uuid);
            })
            ->exists();

        if ($initiatorHasActiveTrade) {
            throw new \RuntimeException('Вы уже участвуете в другом обмене');
        }

        // Проверяем что партнёр не участвует в других активных обменах
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

            $tradeStorage = $this->getTradeStorage();
            $temporarySlot = TemporarySlot::create([
                'storage_uuid' => $tradeStorage->uuid,
                'character_uuid' => $character->uuid,
                'active' => true,
                'timestamps_end' => now()->addMinutes(10),
            ]);

            $item->update(['temporary_slot_uuid' => $temporarySlot->uuid]);

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
                    'temporary_slot_uuid' => $temporarySlot->uuid,
                ],
                $character->uuid
            );

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

            $available = $this->inventoryService->getResourceQuantity($character, $templateSlug);
            if ($available < $quantity) {
                throw new \RuntimeException("Недостаточно ресурса {$templateSlug}: есть {$available}, нужно {$quantity}");
            }

            $storageUuids = $character->storages()->pluck('uuid');
            $slotUuids = Slot::whereIn('storage_uuid', $storageUuids)->pluck('uuid');
            $resource = Resource::whereIn('slot_uuid', $slotUuids)
                ->where('template_slug', $templateSlug)
                ->whereNull('temporary_slot_uuid')
                ->firstOrFail();

            $existingTradeItem = TradeItem::where('trade_uuid', $trade->uuid)
                ->where('template_slug', $templateSlug)
                ->where('character_uuid', $character->uuid)
                ->first();

            if ($existingTradeItem) {
                // Обновляем количество
                $existingTradeItem->update(['quantity' => $existingTradeItem->quantity + $quantity]);
                
                // Создаём temporary slot для нового количества
                $tempSlot = TemporarySlot::create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'storage_uuid' => $resource->slot->storage_uuid,
                    'character_uuid' => $character->uuid,
                    'timestamps_end' => now()->addHours(24),
                    'active' => true,
                ]);
                $resource->update(['temporary_slot_uuid' => $tempSlot->uuid]);
                
                // Обновляем количество в TradeItem
                $existingTradeItem->update(['quantity' => $existingTradeItem->quantity + $quantity]);
                
                return $existingTradeItem;
            }
            
            // Создаём temporary slot
            $tempSlot = TemporarySlot::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'storage_uuid' => $resource->slot->storage_uuid,
                'character_uuid' => $character->uuid,
                'timestamps_end' => now()->addHours(24),
                'active' => true,
            ]);
            
            // Привязываем ресурс к temporary slot
            $resource->update(['temporary_slot_uuid' => $tempSlot->uuid]);

            $tradeStorage = $this->getTradeStorage();
            $temporarySlot = TemporarySlot::create([
                'storage_uuid' => $tradeStorage->uuid,
                'character_uuid' => $character->uuid,
                'active' => true,
                'timestamps_end' => now()->addMinutes(10),
            ]);

            $resource->update(['temporary_slot_uuid' => $temporarySlot->uuid]);

            $tradeItem = TradeItem::create([
                'trade_uuid' => $trade->uuid,
                'character_uuid' => $character->uuid,
                'item_uuid' => null,
                'resource_uuid' => $resource->uuid,
                'template_slug' => $templateSlug,
                'quantity' => $quantity,
            ]);

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
                    'resource_uuid' => $resource->uuid,
                    'template_slug' => $templateSlug,
                    'quantity' => $quantity,
                    'temporary_slot_uuid' => $temporarySlot->uuid,
                ],
                $character->uuid
            );

            return $tradeItem;
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
                $resource = Resource::where('uuid', $tradeItem->resource_uuid)->firstOrFail();
                $this->transferResource($resource, $tradeItem->quantity, $fromCharacter, $toCharacter);
            }
        }

        $trade->update(['status' => 'completed']);

        // Деактивируем все временные слоты
        $temporarySlotUuids = [];
        foreach ($tradeItems as $tradeItem) {
            if ($tradeItem->item_uuid) {
                $item = Item::where('uuid', $tradeItem->item_uuid)->first();
                if ($item && $item->temporary_slot_uuid) {
                    $temporarySlotUuids[] = $item->temporary_slot_uuid;
                }
            } elseif ($tradeItem->resource_uuid) {
                $resource = Resource::where('uuid', $tradeItem->resource_uuid)->first();
                if ($resource && $resource->temporary_slot_uuid) {
                    $temporarySlotUuids[] = $resource->temporary_slot_uuid;
                }
            }
        }

        if (!empty($temporarySlotUuids)) {
            TemporarySlot::whereIn('uuid', $temporarySlotUuids)->update(['active' => false]);
        }

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

        $freeSlot = $this->inventoryService->findFreeSlot($toInventory);

        if (!$freeSlot) {
            throw new \RuntimeException('У получателя нет свободных слотов');
        }

        $oldSlotUuid = $item->slot_uuid;
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
                'from_slot_uuid' => $oldSlotUuid,
                'to_slot_uuid' => $freeSlot->uuid,
                'reason' => 'trade',
            ],
            null
        );
    }

    private function transferResource(Resource $resource, int $quantity, Character $from, Character $to): void
    {
        // Если передаём всё количество ресурса
        if ($quantity >= $resource->quantity) {
            $toInventory = Storage::where('characters_uuid', $to->uuid)
                ->where('storage_type', 'inventory')
                ->firstOrFail();

            $freeSlot = $this->inventoryService->findFreeSlot($toInventory);

            if (!$freeSlot) {
                throw new \RuntimeException('У получателя нет свободных слотов');
            }

            // Перемещаем весь ресурс
            $resource->update([
                'slot_uuid' => $freeSlot->uuid,
                'temporary_slot_uuid' => null,
            ]);
        } else {
            // Передаём часть количества
            $resource->quantity -= $quantity;
            $resource->save();

            // Добавляем часть получателю
            $this->inventoryService->addResource($to, $resource->template_slug, $quantity);
        }

        $this->eventStore->record(
            'resource.transferred',
            'resource',
            $resource->uuid,
            [
                'from_character_uuid' => $from->uuid,
                'to_character_uuid' => $to->uuid,
                'template_slug' => $resource->template_slug,
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
            $temporarySlotUuids = [];

            foreach ($tradeItems as $tradeItem) {
                if ($tradeItem->item_uuid) {
                    $item = Item::where('uuid', $tradeItem->item_uuid)->first();
                    if ($item) {
                        if ($item->temporary_slot_uuid) {
                            $temporarySlotUuids[] = $item->temporary_slot_uuid;
                        }
                        $item->update(['temporary_slot_uuid' => null]);
                    }
                } elseif ($tradeItem->resource_uuid) {
                    $resource = Resource::where('uuid', $tradeItem->resource_uuid)->first();
                    if ($resource) {
                        if ($resource->temporary_slot_uuid) {
                            $temporarySlotUuids[] = $resource->temporary_slot_uuid;
                        }
                        $resource->update(['temporary_slot_uuid' => null]);
                    }
                }
            }

            if (!empty($temporarySlotUuids)) {
                TemporarySlot::whereIn('uuid', $temporarySlotUuids)->update(['active' => false]);
            }

            $trade->update(['status' => 'cancelled']);
            
            // Сбрасываем temporary_slot_uuid у всех предметов и ресурсов
            $tradeItems = TradeItem::where('trade_uuid', $trade->uuid)->get();
            foreach ($tradeItems as $tradeItem) {
                if ($tradeItem->item_uuid) {
                    Item::where('uuid', $tradeItem->item_uuid)->update(['temporary_slot_uuid' => null]);
                } elseif ($tradeItem->resource_uuid) {
                    Resource::where('uuid', $tradeItem->resource_uuid)->update(['temporary_slot_uuid' => null]);
                }
            }
            
            // Деактивируем temporary slots
            $tempSlotUuids = TemporarySlot::where('active', true)
                ->whereIn('uuid', function($q) use ($trade) {
                    $q->select('temporary_slot_uuid')
                      ->from('items')
                      ->join('trade_items', 'items.uuid', '=', 'trade_items.item_uuid')
                      ->where('trade_items.trade_uuid', $trade->uuid)
                      ->whereNotNull('items.temporary_slot_uuid');
                })->orWhereIn('uuid', function($q) use ($trade) {
                    $q->select('temporary_slot_uuid')
                      ->from('resources')
                      ->join('trade_items', 'resources.uuid', '=', 'trade_items.resource_uuid')
                      ->where('trade_items.trade_uuid', $trade->uuid)
                      ->whereNotNull('resources.temporary_slot_uuid');
                })->pluck('uuid');
            
            if ($tempSlotUuids->isNotEmpty()) {
                TemporarySlot::whereIn('uuid', $tempSlotUuids)->update(['active' => false]);
            }

            $this->eventStore->record(
                'trade.cancelled',
                'trade',
                $trade->uuid,
                [
                    'cancelled_by' => $character->uuid,
                ],
                $character->uuid
            );

            return $trade;
        });
    }

    public function getCharacterTrades(Character $character): \Illuminate\Support\Collection
    {
        return TradeOffer::where('initiator_uuid', $character->uuid)
            ->orWhere('partner_uuid', $character->uuid)
            ->with(['initiator', 'partner', 'items'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function isParticipant(Character $character, TradeOffer $trade): bool
    {
        return $character->uuid === $trade->initiator_uuid || $character->uuid === $trade->partner_uuid;
    }

    private function getPartner(Character $character, TradeOffer $trade): Character
    {
        $partnerUuid = $character->uuid === $trade->initiator_uuid
            ? $trade->partner_uuid
            : $trade->initiator_uuid;

        return Character::where('uuid', $partnerUuid)->firstOrFail();
    }

    private function getTradeStorage(): Storage
    {
        $systemCharacter = Character::firstOrCreate(
            ['character_type' => 'system', 'name' => 'System'],
            ['active' => true]
        );

        return Storage::firstOrCreate(
            ['characters_uuid' => $systemCharacter->uuid, 'storage_type' => 'trade'],
            ['name' => 'Обмен', 'active' => true]
        );
    }
}
