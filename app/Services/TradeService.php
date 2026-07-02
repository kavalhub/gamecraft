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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TradeService
{
    public function __construct(
        private InventoryService $inventoryService,
        private EventStore $eventStore,
        private ResourceStackingService $stackingService,
        private StorageProvisioningService $provisioningService,
        private SpecialSlotService $specialSlotService,
        private InventoryResourcePlacementService $placementService,
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
            $this->assertCanModifyTrade($character, $trade);

            $item = Item::where('uuid', $itemUuid)->firstOrFail();

            if (!in_array($item->stage, ['item', 'blueprint'])) {
                throw new \RuntimeException('Этот предмет нельзя обменять');
            }

            if ($item->buffer_slot_uuid) {
                throw new \RuntimeException('Предмет уже участвует в другом обмене или аукционе');
            }

            $fromSlot = Slot::where('uuid', $item->slot_uuid)->firstOrFail();
            $fromStorage = Storage::where('uuid', $fromSlot->storage_uuid)->firstOrFail();

            if ($fromStorage->characters_uuid !== $character->uuid || $fromStorage->storage_type !== 'inventory') {
                throw new \RuntimeException('Предмет не принадлежит вам');
            }

            if (TradeItem::where('trade_uuid', $trade->uuid)->where('item_uuid', $itemUuid)->exists()) {
                throw new \RuntimeException('Предмет уже в обмене');
            }

            $this->provisioningService->ensureTradeStorage($character);
            $tradeSlot = $this->provisioningService->findFreeTradeSlot($character);
            if (!$tradeSlot) {
                throw new \RuntimeException('Нет свободных слотов в обмене');
            }

            $originSlotUuid = $item->slot_uuid;
            $item->update(['slot_uuid' => $tradeSlot->uuid]);

            $tradeItem = TradeItem::create([
                'trade_uuid' => $trade->uuid,
                'character_uuid' => $character->uuid,
                'item_uuid' => $itemUuid,
                'resource_uuid' => null,
                'origin_slot_uuid' => $originSlotUuid,
                'quantity' => 1,
            ]);

            $this->resetAcceptFlags($trade);
            $this->recordItemAdded($trade, $character, $itemUuid, $originSlotUuid, $tradeSlot->uuid);

            return $tradeItem;
        });
    }

    public function addResourceToTrade(Character $character, TradeOffer $trade, string $templateSlug, int $quantity): TradeItem
    {
        return DB::transaction(function () use ($character, $trade, $templateSlug, $quantity) {
            $this->assertCanModifyTrade($character, $trade);

            if ($quantity < 1) {
                throw new \RuntimeException('Количество должно быть больше 0');
            }

            $available = $this->inventoryService->getResourceQuantity($character, $templateSlug);
            if ($available < $quantity) {
                throw new \RuntimeException("Недостаточно ресурса {$templateSlug}: доступно {$available}, нужно {$quantity}");
            }

            $this->provisioningService->ensureTradeStorage($character);

            $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
            $remaining = $quantity;
            $lastTradeItem = null;

            while ($remaining > 0) {
                $partial = $this->findPartialTradeStack($character, $templateSlug, $template->max_stack);
                if ($partial) {
                    $space = $template->max_stack === null
                        ? $remaining
                        : max(0, $template->max_stack - $partial->quantity);
                    $toAdd = min($remaining, $space);
                    if ($toAdd < 1) {
                        break;
                    }

                    $reserved = $this->reserveResourceFromInventory($character, $templateSlug, $toAdd);
                    $partial->update(['quantity' => $partial->quantity + $toAdd]);
                    $reserved->delete();

                    $existingTradeItem = TradeItem::where('resource_uuid', $partial->uuid)->first();
                    if ($existingTradeItem) {
                        $existingTradeItem->update(['quantity' => $partial->quantity]);
                        $lastTradeItem = $existingTradeItem;
                    } else {
                        $lastTradeItem = TradeItem::create([
                            'trade_uuid' => $trade->uuid,
                            'character_uuid' => $character->uuid,
                            'item_uuid' => null,
                            'resource_uuid' => $partial->uuid,
                            'origin_slot_uuid' => $reserved->slot_uuid,
                            'template_slug' => $templateSlug,
                            'quantity' => $toAdd,
                        ]);
                    }

                    $remaining -= $toAdd;
                    continue;
                }

                $tradeSlot = $this->provisioningService->findFreeTradeSlot($character);
                if (!$tradeSlot) {
                    throw new \RuntimeException('Нет свободных слотов в обмене');
                }

                $chunkQty = $template->max_stack === null
                    ? $remaining
                    : min($remaining, $template->max_stack);

                $reserved = $this->reserveResourceFromInventory($character, $templateSlug, $chunkQty);
                $originSlotUuid = $reserved->slot_uuid;
                $reserved->update(['slot_uuid' => $tradeSlot->uuid]);

                $lastTradeItem = TradeItem::create([
                    'trade_uuid' => $trade->uuid,
                    'character_uuid' => $character->uuid,
                    'item_uuid' => null,
                    'resource_uuid' => $reserved->uuid,
                    'origin_slot_uuid' => $originSlotUuid,
                    'template_slug' => $templateSlug,
                    'quantity' => $chunkQty,
                ]);

                $remaining -= $chunkQty;
            }

            if ($remaining > 0) {
                throw new \RuntimeException('Нет свободных слотов в обмене');
            }

            $this->resetAcceptFlags($trade);
            $this->eventStore->record(
                'trade.resource_added',
                'trade',
                $trade->uuid,
                [
                    'character_uuid' => $character->uuid,
                    'template_slug' => $templateSlug,
                    'quantity' => $quantity,
                    'stacks' => TradeItem::where('trade_uuid', $trade->uuid)
                        ->where('character_uuid', $character->uuid)
                        ->where('template_slug', $templateSlug)
                        ->count(),
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

    public function cancelTrade(Character $character, TradeOffer $trade): TradeOffer
    {
        return DB::transaction(function () use ($character, $trade) {
            if (!$this->isParticipant($character, $trade)) {
                throw new \RuntimeException('Вы не участвуете в этом обмене');
            }

            if ($trade->status !== 'pending') {
                throw new \RuntimeException('Можно отменить только ожидающий обмен');
            }

            foreach (TradeItem::where('trade_uuid', $trade->uuid)->get() as $tradeItem) {
                $this->returnTradeEntryToOrigin($tradeItem);
            }

            TradeItem::where('trade_uuid', $trade->uuid)->delete();

            $trade->update(['status' => 'cancelled']);

            $this->eventStore->record(
                'trade.cancelled',
                'trade',
                $trade->uuid,
                ['cancelled_by' => $character->uuid],
                $character->uuid
            );

            $this->recordTradeUpdated($trade, $character);

            return $trade;
        });
    }

    public function getCharacterTrades(Character $character): Collection
    {
        return TradeOffer::where('initiator_uuid', $character->uuid)
            ->orWhere('partner_uuid', $character->uuid)
            ->with(['initiator', 'partner', 'items.item.template', 'items.resource'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function executeTrade(TradeOffer $trade): void
    {
        $tradeItems = TradeItem::where('trade_uuid', $trade->uuid)->orderBy('id')->get();
        $initiator = Character::where('uuid', $trade->initiator_uuid)->firstOrFail();
        $partner = Character::where('uuid', $trade->partner_uuid)->firstOrFail();

        $plan = [];
        foreach ([$initiator, $partner] as $recipient) {
            $plan = array_merge($plan, $this->buildTransferPlan($trade, $recipient, $tradeItems));
        }

        foreach ($plan as $transfer) {
            $this->applyTransfer($transfer);
        }

        TradeItem::where('trade_uuid', $trade->uuid)->delete();
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

    /**
     * @return list<array{
     *   occupant: Item|Resources,
     *   from: Character,
     *   to: Character,
     *   target_slot_uuid: string,
     *   quantity: int,
     * }>
     */
    private function buildTransferPlan(TradeOffer $trade, Character $recipient, Collection $tradeItems): array
    {
        $partner = $this->getPartner($recipient, $trade);
        $outgoing = $tradeItems->where('character_uuid', $recipient->uuid)->values();
        $incoming = $tradeItems->where('character_uuid', $partner->uuid)->values();

        $freedGridSlots = $outgoing
            ->pluck('origin_slot_uuid')
            ->filter()
            ->filter(fn (string $uuid) => $this->isFreedGridSlot($uuid))
            ->values();

        $inventory = $recipient->storages()->where('storage_type', 'inventory')->firstOrFail();
        $gridSlots = $this->specialSlotService->getGridSlots($inventory);
        $gridSlotUuids = $gridSlots->pluck('uuid');

        $availableGridTargets = $freedGridSlots
            ->merge($gridSlotUuids->filter(fn (string $uuid) => $this->isInventorySlotEmpty($uuid)))
            ->unique()
            ->values();

        $plan = [];
        $reservedGridSlots = [];

        foreach ($incoming as $tradeItem) {
            $occupant = $this->resolveTradeOccupant($tradeItem);
            if (!$occupant) {
                continue;
            }

            if ($occupant instanceof Item) {
                $targetSlot = $availableGridTargets
                    ->first(fn (string $uuid) => !in_array($uuid, $reservedGridSlots, true));
                if (!$targetSlot || !$this->isFreedGridSlot($targetSlot)) {
                    throw new \RuntimeException('Недостаточно места в инвентаре для завершения обмена');
                }
                $reservedGridSlots[] = $targetSlot;

                $plan[] = [
                    'occupant' => $occupant,
                    'from' => $partner,
                    'to' => $recipient,
                    'target_slot_uuid' => $targetSlot,
                    'quantity' => 1,
                ];

                continue;
            }

            $remaining = $tradeItem->quantity;

            try {
                $placementSteps = $this->placementService->plan(
                    $inventory,
                    $occupant->template_slug,
                    $remaining,
                    $freedGridSlots->all(),
                    $reservedGridSlots,
                );
            } catch (\RuntimeException) {
                throw new \RuntimeException('Недостаточно места в инвентаре для завершения обмена');
            }

            foreach ($placementSteps as $step) {
                $transfer = [
                    'occupant' => $occupant,
                    'from' => $partner,
                    'to' => $recipient,
                    'target_slot_uuid' => $step->targetSlotUuid,
                    'quantity' => $step->quantity,
                ];

                if ($step->mergeIntoResourceUuid !== null) {
                    $transfer['merge_into'] = $step->mergeIntoResourceUuid;
                } else {
                    $slot = Slot::where('uuid', $step->targetSlotUuid)->first();
                    if ($slot && $this->specialSlotService->isGridSlot($slot)) {
                        $reservedGridSlots[] = $step->targetSlotUuid;
                    }
                }

                $plan[] = $transfer;
            }
        }

        return $plan;
    }

    private function isFreedGridSlot(string $slotUuid): bool
    {
        $slot = Slot::where('uuid', $slotUuid)->first();
        if (!$slot || !$this->specialSlotService->isGridSlot($slot)) {
            return false;
        }

        return $this->isInventorySlotEmpty($slotUuid);
    }

    /**
     * @param  array<string, mixed>  $transfer
     */
    private function applyTransfer(array $transfer): void
    {
        /** @var Item|Resources $occupant */
        $occupant = $transfer['occupant'];
        /** @var Character $from */
        $from = $transfer['from'];
        /** @var Character $to */
        $to = $transfer['to'];
        $targetSlotUuid = $transfer['target_slot_uuid'];
        $quantity = (int) $transfer['quantity'];

        if ($occupant instanceof Item) {
            $occupant->update(['slot_uuid' => $targetSlotUuid]);

            $this->eventStore->record(
                'item.transferred',
                'item',
                $occupant->uuid,
                [
                    'from_character_uuid' => $from->uuid,
                    'to_character_uuid' => $to->uuid,
                    'to_slot_uuid' => $targetSlotUuid,
                    'reason' => 'trade',
                ],
                null
            );

            return;
        }

        if (isset($transfer['merge_into'])) {
            $target = Resources::where('uuid', $transfer['merge_into'])->firstOrFail();
            $target->update(['quantity' => $target->quantity + $quantity]);

            if ($quantity === $occupant->quantity) {
                $occupant->delete();
            } else {
                $occupant->update(['quantity' => $occupant->quantity - $quantity]);
            }
        } elseif ($quantity === $occupant->quantity) {
            $occupant->update(['slot_uuid' => $targetSlotUuid]);
        } else {
            $occupant->update(['quantity' => $occupant->quantity - $quantity]);
            Resources::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $targetSlotUuid,
                'recipe_slug' => $occupant->recipe_slug,
                'template_slug' => $occupant->template_slug,
                'slot_type' => $occupant->slot_type,
                'max_stack' => $occupant->max_stack,
                'quantity' => $quantity,
            ]);
        }

        $this->eventStore->record(
            'resource.transferred',
            'resource',
            $occupant->uuid,
            [
                'from_character_uuid' => $from->uuid,
                'to_character_uuid' => $to->uuid,
                'to_slot_uuid' => $targetSlotUuid,
                'template_slug' => $occupant->template_slug,
                'quantity' => $quantity,
                'reason' => 'trade',
            ],
            null
        );
    }

    private function returnTradeEntryToOrigin(TradeItem $tradeItem): void
    {
        if ($tradeItem->item_uuid) {
            $item = Item::where('uuid', $tradeItem->item_uuid)->first();
            if ($item && $tradeItem->origin_slot_uuid) {
                $item->update(['slot_uuid' => $tradeItem->origin_slot_uuid]);
            }

            return;
        }

        if (!$tradeItem->resource_uuid || !$tradeItem->origin_slot_uuid) {
            return;
        }

        $resource = Resources::where('uuid', $tradeItem->resource_uuid)->first();
        if (!$resource) {
            return;
        }

        $originOccupant = Resources::where('slot_uuid', $tradeItem->origin_slot_uuid)
            ->whereNull('buffer_slot_uuid')
            ->first();

        if ($originOccupant && $originOccupant->template_slug === $resource->template_slug) {
            $maxStack = $resource->max_stack;
            $space = $maxStack === null
                ? $resource->quantity
                : max(0, $maxStack - $originOccupant->quantity);
            $merged = min($resource->quantity, $space);

            if ($merged > 0) {
                $originOccupant->update(['quantity' => $originOccupant->quantity + $merged]);
                if ($merged === $resource->quantity) {
                    $resource->delete();
                } else {
                    $resource->update(['quantity' => $resource->quantity - $merged]);
                }

                return;
            }
        }

        $resource->update(['slot_uuid' => $tradeItem->origin_slot_uuid]);
    }

    private function resolveTradeOccupant(TradeItem $tradeItem): Item|Resources|null
    {
        if ($tradeItem->item_uuid) {
            return Item::where('uuid', $tradeItem->item_uuid)->first();
        }

        if ($tradeItem->resource_uuid) {
            return Resources::where('uuid', $tradeItem->resource_uuid)->first();
        }

        return null;
    }

    private function findPartialTradeStack(Character $character, string $templateSlug, ?int $maxStack): ?Resources
    {
        $tradeSlotUuids = $this->provisioningService->getTradeSlots($character)->pluck('uuid');
        $query = Resources::whereIn('slot_uuid', $tradeSlotUuids)
            ->where('template_slug', $templateSlug)
            ->whereNull('buffer_slot_uuid');

        if ($maxStack !== null) {
            $query->where('quantity', '<', $maxStack);
        }

        return $query->first();
    }

    private function reserveResourceFromInventory(Character $character, string $templateSlug, int $quantity): Resources
    {
        $remaining = $quantity;
        $inventory = $character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $slotUuids = $inventory->slots()->pluck('uuid');

        $resources = Resources::whereIn('slot_uuid', $slotUuids)
            ->where('template_slug', $templateSlug)
            ->whereNull('buffer_slot_uuid')
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

    private function isInventorySlotEmpty(string $slotUuid): bool
    {
        if (Item::where('slot_uuid', $slotUuid)->exists()) {
            return false;
        }

        return !Resources::where('slot_uuid', $slotUuid)->exists();
    }

    private function assertCanModifyTrade(Character $character, TradeOffer $trade): void
    {
        if (!$this->isParticipant($character, $trade)) {
            throw new \RuntimeException('Вы не участвуете в этом обмене');
        }

        if ($trade->status !== 'pending') {
            throw new \RuntimeException('Обмен не в статусе ожидания');
        }
    }

    private function resetAcceptFlags(TradeOffer $trade): void
    {
        $trade->update([
            'initiator_accepted' => false,
            'partner_accepted' => false,
        ]);
    }

    private function recordItemAdded(
        TradeOffer $trade,
        Character $character,
        string $itemUuid,
        string $originSlotUuid,
        string $tradeSlotUuid,
    ): void {
        $this->eventStore->record(
            'trade.item_added',
            'trade',
            $trade->uuid,
            [
                'character_uuid' => $character->uuid,
                'item_uuid' => $itemUuid,
                'origin_slot_uuid' => $originSlotUuid,
                'trade_slot_uuid' => $tradeSlotUuid,
            ],
            $character->uuid
        );

        $this->recordTradeUpdated($trade, $character);
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
