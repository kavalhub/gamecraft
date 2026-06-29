<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use App\Models\TradeItem;
use App\Models\TradeOffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorageMoveService
{
    public function __construct(
        private EventStore $eventStore,
        private StorageProvisioningService $provisioningService,
        private ResourceStackingService $stackingService,
        private SpecialSlotService $specialSlotService
    ) {}

    public function move(
        Character $actor,
        string $fromCellUuid,
        string $toCellUuid,
        ?int $quantity = null
    ): array {
        if ($fromCellUuid === $toCellUuid) {
            throw new \RuntimeException('Нельзя переместить слот в самого себя');
        }

        return DB::transaction(function () use ($actor, $fromCellUuid, $toCellUuid, $quantity) {
            $this->provisioningService->consolidateInventoryResources($actor);

            $from = $this->resolveCell($fromCellUuid);
            $to = $this->resolveCell($toCellUuid);

            $this->validateMove($actor, $from, $to);

            if ($from['kind'] === 'regular') {
                /** @var Slot $fromSlot */
                $fromSlot = $from['cell'];
                if (Item::where('slot_uuid', $fromSlot->uuid)->whereNotNull('temporary_slot_uuid')->exists()) {
                    throw new \RuntimeException('Предмет занят (обмен или аукцион)');
                }
                if (Resources::where('slot_uuid', $fromSlot->uuid)->whereNotNull('temporary_slot_uuid')->exists()) {
                    throw new \RuntimeException('Ресурс занят (обмен)');
                }
            }

            $occupant = $this->getOccupantFromCell($from);
            if (!$occupant) {
                throw new \RuntimeException('Исходный слот пуст');
            }

            if ($from['kind'] === 'regular' && $to['kind'] === 'regular') {
                return $this->moveRegularToRegular($actor, $from['cell'], $to['cell'], $occupant, $quantity);
            }

            if ($from['kind'] === 'regular' && $to['kind'] === 'temporary') {
                return $this->moveRegularToTemporary($actor, $from['cell'], $to['cell'], $occupant, $quantity);
            }

            if ($from['kind'] === 'temporary' && $to['kind'] === 'regular') {
                return $this->moveTemporaryToRegular($actor, $from['cell'], $to['cell'], $occupant, $quantity);
            }

            return $this->moveTemporaryToTemporary($actor, $from['cell'], $to['cell'], $occupant);
        });
    }

    private function resolveCell(string $uuid): array
    {
        $slot = Slot::where('uuid', $uuid)->lockForUpdate()->first();
        if ($slot) {
            return ['kind' => 'regular', 'cell' => $slot];
        }

        $tempSlot = TemporarySlot::where('uuid', $uuid)->lockForUpdate()->first();
        if ($tempSlot) {
            return ['kind' => 'temporary', 'cell' => $tempSlot];
        }

        throw new \RuntimeException('Слот не найден');
    }

    private function validateMove(Character $actor, array $from, array $to): void
    {
        if ($from['kind'] === 'temporary') {
            /** @var TemporarySlot $fromTemp */
            $fromTemp = $from['cell'];
            if ($fromTemp->character_uuid !== $actor->uuid) {
                throw new \RuntimeException('Нельзя перемещать из чужого слота обмена');
            }
        } else {
            $this->assertOwnsRegularSlot($actor, $from['cell']);
        }

        if ($to['kind'] === 'temporary') {
            /** @var TemporarySlot $toTemp */
            $toTemp = $to['cell'];
            if ($toTemp->character_uuid !== $actor->uuid) {
                throw new \RuntimeException('Нельзя перемещать в чужой слот обмена');
            }
        } else {
            $this->assertOwnsRegularSlot($actor, $to['cell']);
        }
    }

    private function assertOwnsRegularSlot(Character $actor, Slot $slot): void
    {
        $storage = Storage::where('uuid', $slot->storage_uuid)->firstOrFail();
        if ($storage->characters_uuid !== $actor->uuid) {
            throw new \RuntimeException('Слот не принадлежит персонажу');
        }
    }

    private function assertItemFitsSlot(Item $item, Slot $slot): void
    {
        if ($slot->slot_type === null) {
            return;
        }

        if ($slot->slot_type === 'gold') {
            throw new \RuntimeException('В золотой слот можно класть только золото');
        }

        $template = ItemTemplate::where('slug', $item->template_slug)->first();
        $itemSlotType = $item->slot_type ?? $template?->slot_type;

        if ($itemSlotType !== $slot->slot_type) {
            throw new \RuntimeException('Предмет не подходит для этого слота экипировки');
        }
    }

    private function getOccupantFromCell(array $cell): Item|Resources|null
    {
        if ($cell['kind'] === 'regular') {
            /** @var Slot $slot */
            $slot = $cell['cell'];
            $item = Item::where('slot_uuid', $slot->uuid)->first();
            if ($item && !$item->temporary_slot_uuid) {
                return $item;
            }
            $resource = Resources::where('slot_uuid', $slot->uuid)
                ->whereNull('temporary_slot_uuid')
                ->first();

            return $resource;
        }

        /** @var TemporarySlot $tempSlot */
        $tempSlot = $cell['cell'];

        return $this->provisioningService->getOccupantForTemporarySlot($tempSlot);
    }

    private function getTargetOccupant(array $cell): Item|Resources|null
    {
        if ($cell['kind'] === 'regular') {
            /** @var Slot $slot */
            $slot = $cell['cell'];

            return Item::where('slot_uuid', $slot->uuid)->first()
                ?? Resources::where('slot_uuid', $slot->uuid)->first();
        }

        /** @var TemporarySlot $tempSlot */
        $tempSlot = $cell['cell'];

        return $this->provisioningService->getOccupantForTemporarySlot($tempSlot);
    }

    private function moveRegularToRegular(
        Character $actor,
        Slot $fromSlot,
        Slot $toSlot,
        Item|Resources $occupant,
        ?int $quantity
    ): array {
        $toOccupant = $this->getTargetOccupant(['kind' => 'regular', 'cell' => $toSlot]);

        if ($occupant instanceof Item) {
            $this->assertItemFitsSlot($occupant, $toSlot);
            if ($toOccupant instanceof Item) {
                $this->assertItemFitsSlot($toOccupant, $fromSlot);
            }

            if ($toOccupant) {
                if ($toOccupant instanceof Item) {
                    $fromUuid = $occupant->slot_uuid;
                    $toUuid = $toOccupant->slot_uuid;
                    $occupant->update(['slot_uuid' => $toUuid]);
                    $toOccupant->update(['slot_uuid' => $fromUuid]);
                } else {
                    throw new \RuntimeException('Нельзя поместить предмет в слот с ресурсом');
                }
            } else {
                $occupant->update(['slot_uuid' => $toSlot->uuid]);
            }

            $this->recordItemMoved($occupant, $fromSlot->uuid, $toSlot->uuid, $actor);

            return ['type' => 'item', 'uuid' => $occupant->uuid];
        }

        /** @var Resources $occupant */
        return $this->moveResourceBetweenRegularSlots($actor, $fromSlot, $toSlot, $occupant, $toOccupant, $quantity);
    }

    private function moveResourceBetweenRegularSlots(
        Character $actor,
        Slot $fromSlot,
        Slot $toSlot,
        Resources $occupant,
        Item|Resources|null $toOccupant,
        ?int $quantity
    ): array {
        $storage = Storage::where('uuid', $toSlot->storage_uuid)->firstOrFail();

        if ($toSlot->slot_type === 'gold' && $occupant->template_slug !== 'gold') {
            throw new \RuntimeException('В золотой слот можно класть только золото');
        }

        if ($occupant->template_slug === 'gold' && $this->specialSlotService->isGridSlot($toSlot)) {
            $reclaimSlot = $this->specialSlotService->resolveAutoReclaimTarget($storage, 'gold');
            if ($reclaimSlot) {
                $toSlot = $reclaimSlot;
                $toOccupant = Resources::where('slot_uuid', $reclaimSlot->uuid)
                    ->whereNull('temporary_slot_uuid')
                    ->first();
            }
        }

        $moveQty = $quantity ?? $occupant->quantity;
        if ($moveQty < 1 || $moveQty > $occupant->quantity) {
            throw new \RuntimeException('Некорректное количество');
        }

        if (!$toOccupant) {
            if ($moveQty === $occupant->quantity) {
                $occupant->update(['slot_uuid' => $toSlot->uuid]);
            } else {
                $occupant->update(['quantity' => $occupant->quantity - $moveQty]);
                Resources::create([
                    'uuid' => Str::uuid()->toString(),
                    'slot_uuid' => $toSlot->uuid,
                    'recipe_slug' => $occupant->recipe_slug,
                    'template_slug' => $occupant->template_slug,
                    'slot_type' => $occupant->slot_type,
                    'max_stack' => $occupant->max_stack,
                    'quantity' => $moveQty,
                ]);
            }

            $this->recordResourceMoved($occupant, $fromSlot->uuid, $toSlot->uuid, $moveQty, $actor);

            return ['type' => 'resource', 'uuid' => $occupant->uuid, 'quantity' => $moveQty];
        }

        if ($toOccupant instanceof Item) {
            throw new \RuntimeException('Нельзя поместить ресурс в слот с предметом');
        }

        if ($toOccupant->template_slug !== $occupant->template_slug) {
            if ($occupant->template_slug === 'gold') {
                $reclaimSlot = $this->specialSlotService->resolveAutoReclaimTarget($storage, 'gold');
                if ($reclaimSlot) {
                    return $this->moveResourceBetweenRegularSlots(
                        $actor,
                        $fromSlot,
                        $reclaimSlot,
                        $occupant,
                        Resources::where('slot_uuid', $reclaimSlot->uuid)->whereNull('temporary_slot_uuid')->first(),
                        $quantity
                    );
                }
            }

            $fromUuid = $occupant->slot_uuid;
            $toUuid = $toOccupant->slot_uuid;
            $occupant->update(['slot_uuid' => $toUuid]);
            $toOccupant->update(['slot_uuid' => $fromUuid]);

            return ['type' => 'resource', 'uuid' => $occupant->uuid, 'swapped' => true];
        }

        $maxStack = $occupant->max_stack;
        if ($maxStack !== null && $toOccupant->quantity + $moveQty > $maxStack) {
            $space = $maxStack - $toOccupant->quantity;
            if ($space <= 0) {
                return ['type' => 'resource', 'uuid' => $occupant->uuid, 'noop' => true];
            }
            $moveQty = min($moveQty, $space);
        }

        $toOccupant->update(['quantity' => $toOccupant->quantity + $moveQty]);
        if ($moveQty === $occupant->quantity) {
            $occupant->delete();
        } else {
            $occupant->update(['quantity' => $occupant->quantity - $moveQty]);
        }

        $this->recordResourceMoved($occupant, $fromSlot->uuid, $toSlot->uuid, $moveQty, $actor);

        return ['type' => 'resource', 'uuid' => $toOccupant->uuid, 'quantity' => $moveQty];
    }

    private function moveRegularToTemporary(
        Character $actor,
        Slot $fromSlot,
        TemporarySlot $toTempSlot,
        Item|Resources $occupant,
        ?int $quantity
    ): array {
        $tempStorage = Storage::where('uuid', $toTempSlot->storage_uuid)->firstOrFail();
        if ($tempStorage->storage_type !== 'trade') {
            throw new \RuntimeException('Временные слоты поддерживаются только для обмена');
        }

        $toOccupant = $this->provisioningService->getOccupantForTemporarySlot($toTempSlot);

        if ($occupant instanceof Item) {
            if ($quantity !== null && $quantity !== 1) {
                throw new \RuntimeException('Предмет нельзя разделить');
            }
            if ($toOccupant) {
                throw new \RuntimeException('Целевой слот обмена занят');
            }

            $occupant->update(['temporary_slot_uuid' => $toTempSlot->uuid]);
            $this->syncTradeItemOnOverlay($actor, $occupant, $toTempSlot);

            return ['type' => 'item', 'uuid' => $occupant->uuid, 'temporary_slot_uuid' => $toTempSlot->uuid];
        }

        /** @var Resources $occupant */
        return $this->overlayResourceToTemporary($actor, $fromSlot, $toTempSlot, $occupant, $toOccupant, $quantity);
    }

    private function overlayResourceToTemporary(
        Character $actor,
        Slot $fromSlot,
        TemporarySlot $toTempSlot,
        Resources $occupant,
        Item|Resources|null $toOccupant,
        ?int $quantity
    ): array {
        if ($toOccupant) {
            throw new \RuntimeException('Целевой слот обмена занят');
        }

        $moveQty = $quantity ?? $occupant->quantity;
        if ($moveQty < 1 || $moveQty > $occupant->quantity) {
            throw new \RuntimeException('Некорректное количество');
        }

        $tradedResource = $occupant;
        if ($moveQty < $occupant->quantity) {
            $occupant->update(['quantity' => $occupant->quantity - $moveQty]);
            $tradedResource = Resources::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $fromSlot->uuid,
                'recipe_slug' => $occupant->recipe_slug,
                'template_slug' => $occupant->template_slug,
                'slot_type' => $occupant->slot_type,
                'max_stack' => $occupant->max_stack,
                'quantity' => $moveQty,
            ]);
        }

        $tradedResource->update(['temporary_slot_uuid' => $toTempSlot->uuid]);
        $this->syncTradeResourceOnOverlay($actor, $tradedResource, $toTempSlot, $moveQty);

        return [
            'type' => 'resource',
            'uuid' => $tradedResource->uuid,
            'quantity' => $moveQty,
            'temporary_slot_uuid' => $toTempSlot->uuid,
        ];
    }

    private function moveTemporaryToRegular(
        Character $actor,
        TemporarySlot $fromTempSlot,
        Slot $toSlot,
        Item|Resources $occupant,
        ?int $quantity
    ): array {
        $toOccupant = $this->getTargetOccupant(['kind' => 'regular', 'cell' => $toSlot]);

        if ($occupant instanceof Item) {
            if ($toOccupant) {
                throw new \RuntimeException('Целевой слот занят');
            }

            $occupant->update([
                'temporary_slot_uuid' => null,
                'slot_uuid' => $toSlot->uuid,
            ]);
            $this->removeTradeItemForOccupant($actor, $occupant);

            return ['type' => 'item', 'uuid' => $occupant->uuid];
        }

        /** @var Resources $occupant */
        if ($toOccupant instanceof Item) {
            throw new \RuntimeException('Нельзя поместить ресурс в слот с предметом');
        }

        $storage = Storage::where('uuid', $toSlot->storage_uuid)->firstOrFail();
        if ($this->specialSlotService->shouldAutoReclaim($storage, $toSlot, $occupant->template_slug)) {
            $reclaimSlot = $this->specialSlotService->resolveAutoReclaimTarget($storage, $occupant->template_slug);
            if ($reclaimSlot) {
                $toSlot = $reclaimSlot;
                $toOccupant = Resources::where('slot_uuid', $reclaimSlot->uuid)
                    ->whereNull('temporary_slot_uuid')
                    ->first();
            }
        }

        $moveQty = $quantity ?? $occupant->quantity;

        if (!$toOccupant) {
            $occupant->update([
                'temporary_slot_uuid' => null,
                'slot_uuid' => $toSlot->uuid,
            ]);
            $this->removeTradeItemForOccupant($actor, $occupant);

            return ['type' => 'resource', 'uuid' => $occupant->uuid, 'quantity' => $moveQty];
        }

        if ($toOccupant->template_slug === $occupant->template_slug) {
            $maxStack = $occupant->max_stack;
            $space = $maxStack === null ? $moveQty : max(0, $maxStack - $toOccupant->quantity);
            $merged = min($moveQty, $space, $occupant->quantity);

            $toOccupant->update(['quantity' => $toOccupant->quantity + $merged]);
            if ($merged === $occupant->quantity) {
                $occupant->delete();
            } else {
                $occupant->update(['quantity' => $occupant->quantity - $merged]);
            }
            $this->removeTradeItemForOccupant($actor, $occupant);

            return ['type' => 'resource', 'uuid' => $toOccupant->uuid, 'quantity' => $merged];
        }

        throw new \RuntimeException('Несовместимые слоты');
    }

    private function moveTemporaryToTemporary(
        Character $actor,
        TemporarySlot $fromTempSlot,
        TemporarySlot $toTempSlot,
        Item|Resources $occupant
    ): array {
        $toOccupant = $this->provisioningService->getOccupantForTemporarySlot($toTempSlot);

        if (!$toOccupant) {
            $occupant->update(['temporary_slot_uuid' => $toTempSlot->uuid]);
            $this->updateTradeItemTempSlot($actor, $occupant, $toTempSlot);

            return [
                'type' => $occupant instanceof Item ? 'item' : 'resource',
                'uuid' => $occupant->uuid,
                'temporary_slot_uuid' => $toTempSlot->uuid,
            ];
        }

        $fromUuid = $occupant->temporary_slot_uuid;
        $toUuid = $toOccupant->temporary_slot_uuid;
        $occupant->update(['temporary_slot_uuid' => $toUuid]);
        $toOccupant->update(['temporary_slot_uuid' => $fromUuid]);

        return ['type' => 'swap', 'swapped' => true];
    }

    private function syncTradeItemOnOverlay(Character $actor, Item $item, TemporarySlot $tempSlot): void
    {
        $trade = $this->getActiveTrade($actor);
        if (!$trade) {
            return;
        }

        TradeItem::firstOrCreate(
            [
                'trade_uuid' => $trade->uuid,
                'item_uuid' => $item->uuid,
            ],
            [
                'character_uuid' => $actor->uuid,
                'resource_uuid' => null,
                'quantity' => 1,
            ]
        );

        $trade->update(['initiator_accepted' => false, 'partner_accepted' => false]);
        $this->recordTradeOverlayUpdated($actor, $trade);
    }

    private function recordTradeOverlayUpdated(Character $actor, TradeOffer $trade): void
    {
        $partnerUuid = $trade->initiator_uuid === $actor->uuid
            ? $trade->partner_uuid
            : $trade->initiator_uuid;

        $this->eventStore->record(
            'trade.updated',
            'trade',
            $trade->uuid,
            [
                'character_uuid' => $actor->uuid,
                'partner_uuid' => $partnerUuid,
                'status' => $trade->status,
                'initiator_accepted' => $trade->initiator_accepted,
                'partner_accepted' => $trade->partner_accepted,
            ],
            $actor->uuid
        );
    }

    private function syncTradeResourceOnOverlay(
        Character $actor,
        Resources $resource,
        TemporarySlot $tempSlot,
        int $quantity
    ): void {
        $trade = $this->getActiveTrade($actor);
        if (!$trade) {
            return;
        }

        TradeItem::firstOrCreate(
            [
                'trade_uuid' => $trade->uuid,
                'resource_uuid' => $resource->uuid,
            ],
            [
                'character_uuid' => $actor->uuid,
                'item_uuid' => null,
                'template_slug' => $resource->template_slug,
                'quantity' => $quantity,
            ]
        );

        $trade->update(['initiator_accepted' => false, 'partner_accepted' => false]);
        $this->recordTradeOverlayUpdated($actor, $trade);
    }

    private function removeTradeItemForOccupant(Character $actor, Item|Resources $occupant): void
    {
        $trade = $this->getActiveTrade($actor);
        if (!$trade) {
            return;
        }

        if ($occupant instanceof Item) {
            TradeItem::where('trade_uuid', $trade->uuid)
                ->where('item_uuid', $occupant->uuid)
                ->delete();
        } else {
            TradeItem::where('trade_uuid', $trade->uuid)
                ->where('resource_uuid', $occupant->uuid)
                ->delete();
        }

        $trade->update(['initiator_accepted' => false, 'partner_accepted' => false]);
        $this->recordTradeOverlayUpdated($actor, $trade);
    }

    private function updateTradeItemTempSlot(Character $actor, Item|Resources $occupant, TemporarySlot $tempSlot): void
    {
        // TradeItem references item/resource uuid, not temp slot — no update needed
    }

    private function getActiveTrade(Character $actor): ?TradeOffer
    {
        return TradeOffer::where('status', 'pending')
            ->where(function ($q) use ($actor) {
                $q->where('initiator_uuid', $actor->uuid)
                    ->orWhere('partner_uuid', $actor->uuid);
            })
            ->first();
    }

    private function recordItemMoved(Item $item, string $fromSlotUuid, string $toSlotUuid, Character $actor): void
    {
        $this->eventStore->recordItemEvent(
            'item.moved',
            $item->uuid,
            [
                'from_slot_uuid' => $fromSlotUuid,
                'to_slot_uuid' => $toSlotUuid,
            ],
            $actor->uuid
        );
    }

    private function recordResourceMoved(
        Resources $resource,
        string $fromSlotUuid,
        string $toSlotUuid,
        int $quantity,
        Character $actor
    ): void {
        $this->eventStore->recordResourceEvent(
            'resource.moved',
            $resource->uuid,
            [
                'from_slot_uuid' => $fromSlotUuid,
                'to_slot_uuid' => $toSlotUuid,
                'quantity' => $quantity,
            ],
            $actor->uuid
        );
    }
}
