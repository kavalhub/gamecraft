<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use App\Models\TradeItem;
use App\Models\TradeOffer;
use App\Services\Storage\PlayerStorageAccess;
use App\Services\Storage\StorageTransferPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorageMoveService
{
    public function __construct(
        private EventStore $eventStore,
        private StorageProvisioningService $provisioningService,
        private ResourceStackingService $stackingService,
        private SpecialSlotService $specialSlotService,
        private CraftStationService $craftStationService,
        private DisassembleStationService $disassembleStationService,
        private QuestStorageService $questStorageService,
        private WorldStorageService $worldStorageService,
        private SlotFitService $slotFitService,
        private SlotCellResolver $slotCellResolver,
        private CorpseLootService $corpseLootService,
        private InventoryResourcePlacementService $placementService,
        private StorageTransferPolicy $transferPolicy,
        private PlayerStorageAccess $playerStorageAccess,
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
                if ($this->slotCellResolver->hasBufferedOccupantOnRegularSlot($fromSlot)) {
                    throw new \RuntimeException('Предмет занят');
                }
            }

            $occupant = $this->getOccupantFromCell($from);
            if (!$occupant) {
                throw new \RuntimeException('Исходный слот пуст');
            }

            $this->assertQuestItemMoveAllowed($from, $to, $occupant);

            if ($from['kind'] === 'regular' && $to['kind'] === 'regular') {
                $result = $this->moveRegularToRegular($actor, $from['cell'], $to['cell'], $occupant, $quantity);
                $this->maybeMarkMailClaimed($actor, $from);

                return $result;
            }

            if ($from['kind'] === 'regular' && $to['kind'] === 'temporary') {
                return $this->moveRegularToTemporary($actor, $from['cell'], $to['cell'], $occupant, $quantity);
            }

            if ($from['kind'] === 'temporary' && $to['kind'] === 'regular') {
                $result = $this->moveTemporaryToRegular($actor, $from['cell'], $to['cell'], $occupant, $quantity);
                $this->maybeMarkMailClaimed($actor, $from);

                return $result;
            }

            $result = $this->moveTemporaryToTemporary($actor, $from['cell'], $to['cell'], $occupant);
            $this->maybeMarkMailClaimed($actor, $from);

            return $result;
        });
    }

    /**
     * Перенос ресурса в сетку хранилища с заполнением частичных стаков и разбиением остатка.
     *
     * @return array<string, mixed>
     */
    public function transferResourceToStorageGrid(
        Character $actor,
        string $fromSlotUuid,
        Storage $targetStorage,
        ?int $quantity = null,
    ): array {
        return DB::transaction(function () use ($actor, $fromSlotUuid, $targetStorage, $quantity) {
            $from = $this->resolveCell($fromSlotUuid);

            if ($from['kind'] === 'temporary') {
                return $this->transferResourceFromTemporaryToStorageGrid(
                    $actor,
                    $from['cell'],
                    $targetStorage,
                    $quantity,
                );
            }

            if ($from['kind'] !== 'regular') {
                return $this->noop('resource');
            }

            /** @var Slot $fromSlot */
            $fromSlot = $from['cell'];
            if ($this->slotCellResolver->hasBufferedOccupantOnRegularSlot($fromSlot)) {
                throw new \RuntimeException('Предмет занят');
            }

            $occupant = $this->getOccupantFromCell($from);
            if (!$occupant instanceof Resources) {
                return $this->noop('resource');
            }

            $moveQty = $quantity ?? $occupant->quantity;
            $moveQty = min($moveQty, $occupant->quantity);
            if ($moveQty < 1) {
                return $this->noop('resource');
            }

            $sourceStorage = Storage::where('uuid', $fromSlot->storage_uuid)->first();
            $reservedSlotUuids = $sourceStorage?->uuid === $targetStorage->uuid
                ? [$fromSlot->uuid]
                : [];

            try {
                $steps = $this->placementService->plan(
                    $targetStorage,
                    $occupant->template_slug,
                    $moveQty,
                    reservedSlotUuids: $reservedSlotUuids,
                );
            } catch (\RuntimeException) {
                return $this->noop('resource');
            }

            return $this->executeResourcePlacementSteps($actor, $steps, $occupant);
        });
    }

    /**
     * @param  list<\App\Services\Slots\ResourcePlacementStep>  $steps
     * @return array<string, mixed>
     */
    private function executeResourcePlacementSteps(
        Character $actor,
        array $steps,
        Resources $occupant,
    ): array {
        $lastResult = null;
        foreach ($steps as $step) {
            $occupant->refresh();
            if (!$occupant->exists) {
                break;
            }

            $result = $this->move($actor, $occupant->slot_uuid, $step->targetSlotUuid, $step->quantity);
            if (($result['noop'] ?? false) === true) {
                break;
            }

            $lastResult = $result;
        }

        return $lastResult ?? $this->noop('resource');
    }

    /**
     * @return array<string, mixed>
     */
    private function transferResourceFromTemporaryToStorageGrid(
        Character $actor,
        TemporarySlot $fromTempSlot,
        Storage $targetStorage,
        ?int $quantity = null,
    ): array {
        $occupant = $this->slotCellResolver->getOccupantForTemporarySlot($fromTempSlot);
        if (!$occupant instanceof Resources) {
            return $this->noop('resource');
        }

        $moveQty = $quantity ?? $occupant->quantity;
        $moveQty = min($moveQty, $occupant->quantity);
        if ($moveQty < 1) {
            return $this->noop('resource');
        }

        try {
            $steps = $this->placementService->plan(
                $targetStorage,
                $occupant->template_slug,
                $moveQty,
            );
        } catch (\RuntimeException) {
            return $this->noop('resource');
        }

        $lastResult = null;
        foreach ($steps as $step) {
            $occupant = $this->slotCellResolver->getOccupantForTemporarySlot($fromTempSlot);
            if (!$occupant instanceof Resources) {
                break;
            }

            $result = $this->move($actor, $fromTempSlot->uuid, $step->targetSlotUuid, $step->quantity);
            if (($result['noop'] ?? false) === true) {
                break;
            }

            $lastResult = $result;
        }

        return $lastResult ?? $this->noop('resource');
    }

    private function maybeMarkMailClaimed(Character $actor, array $from): void
    {
        if ($from['kind'] !== 'temporary') {
            return;
        }

        /** @var TemporarySlot $fromTemp */
        $fromTemp = $from['cell'];
        $storage = Storage::where('uuid', $fromTemp->storage_uuid)->first();
        if ($storage?->storage_type !== 'post_inbox') {
            return;
        }

        $message = app(MailService::class)->findMessageByInboxSlot($fromTemp);
        if (!$message) {
            return;
        }

        if ($message->status === 'unread') {
            app(MailService::class)->markRead($actor, $message->uuid);
        }

        if (!$this->slotCellResolver->getOccupantForTemporarySlot($fromTemp)) {
            $fromTemp->update(['active' => false]);
        }

        app(MailService::class)->markClaimedIfEmpty($message->fresh());
    }

    private function resolveCell(string $uuid): array
    {
        return $this->slotCellResolver->resolve($uuid, true);
    }

    private function validateMove(Character $actor, array $from, array $to): void
    {
        if ($from['kind'] === 'temporary') {
            /** @var TemporarySlot $fromTemp */
            $fromTemp = $from['cell'];
            $fromStorage = Storage::where('uuid', $fromTemp->storage_uuid)->first();
            if ($fromStorage?->storage_type === 'corpse') {
                $this->corpseLootService->assertCanClaimSlot($actor, $fromTemp);
            } elseif ($fromStorage?->storage_type === 'post_inbox') {
                app(MailService::class)->assertRecipientOwnsInboxSlot($actor, $fromTemp);
            } elseif ($fromTemp->character_uuid !== null && $fromTemp->character_uuid !== $actor->uuid) {
                throw new \RuntimeException('Нельзя перемещать из чужого слота обмена');
            }
        } else {
            $this->assertCanUseRegularSlot($actor, $from['cell']);
        }

        if ($to['kind'] === 'temporary') {
            /** @var TemporarySlot $toTemp */
            $toTemp = $to['cell'];
            $toStorage = Storage::where('uuid', $toTemp->storage_uuid)->first();
            if ($toStorage?->storage_type === 'corpse') {
                throw new \RuntimeException('Нельзя класть предметы на труп');
            }
            if ($toStorage?->storage_type === 'post_inbox') {
                throw new \RuntimeException('Входящие вложения создаются только при доставке письма');
            }
            if ($toTemp->character_uuid !== null && $toTemp->character_uuid !== $actor->uuid) {
                throw new \RuntimeException('Нельзя перемещать в чужой слот обмена');
            }
        } else {
            $this->assertCanUseRegularSlot($actor, $to['cell']);
        }

        $this->assertTradeStorageMoveAllowed($actor, $from, $to);
        $this->transferPolicy->assertAllowed($actor, $from, $to);
    }

    private function assertTradeStorageMoveAllowed(Character $actor, array $from, array $to): void
    {
        $fromType = $this->resolveRegularStorageType($from);
        $toType = $this->resolveRegularStorageType($to);

        if ($from['kind'] === 'temporary' || $to['kind'] === 'temporary') {
            $fromStorage = $from['kind'] === 'temporary'
                ? Storage::where('uuid', $from['cell']->storage_uuid)->first()
                : Storage::where('uuid', $from['cell']->storage_uuid)->first();
            $toStorage = $to['kind'] === 'temporary'
                ? Storage::where('uuid', $to['cell']->storage_uuid)->first()
                : Storage::where('uuid', $to['cell']->storage_uuid)->first();

            if ($fromStorage?->storage_type === 'trade' || $toStorage?->storage_type === 'trade') {
                throw new \RuntimeException('Обмен использует обычные слоты trade-хранилища');
            }
        }

        if ($fromType === 'trade' || $toType === 'trade') {
            if (!$this->getActiveTrade($actor)) {
                throw new \RuntimeException('Нет активного обмена');
            }
        }
    }

    private function resolveRegularStorageType(array $cell): ?string
    {
        if ($cell['kind'] !== 'regular') {
            return null;
        }

        return Storage::where('uuid', $cell['cell']->storage_uuid)->value('storage_type');
    }

    private function assertCanUseRegularSlot(Character $actor, Slot $slot): void
    {
        $storage = Storage::where('uuid', $slot->storage_uuid)->firstOrFail();
        if ($storage->characters_uuid === $actor->uuid) {
            return;
        }

        if ($storage->storage_type === 'guild_bank') {
            if ($this->isGuildBankMember($actor, $storage->characters_uuid)) {
                return;
            }

            throw new \RuntimeException('Нет доступа к банку гильдии');
        }

        throw new \RuntimeException('Слот не принадлежит персонажу');
    }

    private function isGuildBankMember(Character $actor, string $guildUuid): bool
    {
        return DB::table('guilds_members')
            ->where('head_uuid', $guildUuid)
            ->where('member_uuid', $actor->uuid)
            ->where('active', true)
            ->exists();
    }

    private function assertOwnsRegularSlot(Character $actor, Slot $slot): void
    {
        $this->assertCanUseRegularSlot($actor, $slot);
    }

    private function assertQuestItemMoveAllowed(array $from, array $to, Item|Resources $occupant): void
    {
        if (!$occupant instanceof Item) {
            return;
        }

        if (!$this->worldStorageService->isQuestItem($occupant)) {
            return;
        }

        if ($to['kind'] === 'temporary') {
            throw new \RuntimeException('Квестовый предмет можно перемещать только в инвентаре');
        }

        if ($from['kind'] !== 'regular' || $to['kind'] !== 'regular') {
            throw new \RuntimeException('Квестовый предмет можно перемещать только в инвентаре');
        }

        /** @var Slot $fromSlot */
        $fromSlot = $from['cell'];
        /** @var Slot $toSlot */
        $toSlot = $to['cell'];

        $fromStorage = Storage::where('uuid', $fromSlot->storage_uuid)->firstOrFail();
        $toStorage = Storage::where('uuid', $toSlot->storage_uuid)->firstOrFail();

        if ($fromStorage->storage_type !== 'inventory' || $toStorage->storage_type !== 'inventory') {
            throw new \RuntimeException('Квестовый предмет можно перемещать только в инвентаре');
        }
    }

    private function noop(string $occupantType = 'item'): array
    {
        return ['type' => $occupantType, 'noop' => true];
    }

    private function maybeSyncCraftStation(Character $actor, TemporarySlot $tempSlot): void
    {
        $storage = Storage::where('uuid', $tempSlot->storage_uuid)->first();
        if ($storage?->storage_type !== 'craft') {
            return;
        }

        if ($tempSlot->slot_index === CraftStationService::CENTER_SLOT_INDEX) {
            $this->craftStationService->syncAfterCenterChange($actor);
        }
    }

    private function maybeDeactivateEmptyCorpse(TemporarySlot $fromTempSlot): void
    {
        $storage = Storage::where('uuid', $fromTempSlot->storage_uuid)->first();
        if ($storage?->storage_type !== 'corpse') {
            return;
        }

        $corpse = Character::where('uuid', $storage->characters_uuid)
            ->where('character_type', 'corpse')
            ->first();

        if (!$corpse) {
            return;
        }

        $hasLoot = $this->corpseLootService->getLootSlots($corpse)
            ->contains(fn (TemporarySlot $slot) => $this->slotCellResolver->getOccupantForTemporarySlot($slot));

        if (!$hasLoot) {
            $corpse->update(['active' => false]);
        }
    }

    private function isEquipmentSlot(Slot $slot): bool
    {
        return $slot->slot_type !== null
            && str_starts_with($slot->slot_type, 'equipment_');
    }

    private function getOccupantFromCell(array $cell): Item|Resources|null
    {
        if ($cell['kind'] === 'regular') {
            /** @var Slot $slot */
            $slot = $cell['cell'];

            return $this->slotCellResolver->getOccupantForRegularSlot($slot);
        }

        /** @var TemporarySlot $tempSlot */
        $tempSlot = $cell['cell'];

        return $this->slotCellResolver->getOccupantForTemporarySlot($tempSlot);
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
        $fromCell = ['kind' => 'regular', 'cell' => $fromSlot];
        $toCell = ['kind' => 'regular', 'cell' => $toSlot];

        if ($this->slotFitService->isManualMoveBlocked($actor, $occupant, $fromCell, $toCell)) {
            return $this->noop($occupant instanceof Item ? 'item' : 'resource');
        }

        $toOccupant = $this->getTargetOccupant($toCell);

        if ($occupant instanceof Item) {
            if (!$this->slotFitService->occupantFitsRegularSlot($occupant, $toSlot)) {
                return $this->noop('item');
            }
            if ($toOccupant instanceof Item && !$this->slotFitService->occupantFitsRegularSlot($toOccupant, $fromSlot)) {
                return $this->noop('item');
            }

            if ($toOccupant) {
                if ($toOccupant instanceof Item) {
                    $fromUuid = $occupant->slot_uuid;
                    $toUuid = $toOccupant->slot_uuid;
                    $occupant->update(['slot_uuid' => $toUuid]);
                    $toOccupant->update(['slot_uuid' => $fromUuid]);
                    $this->maybeSyncTradeMove($actor, $fromSlot, $toSlot, $occupant);
                    $this->maybeSyncTradeMove($actor, $toSlot, $fromSlot, $toOccupant);
                } elseif ($fromSlot->slot_type === null && $toSlot->slot_type !== null) {
                    $resourceQty = $toOccupant->quantity;
                    $toOccupant->update(['slot_uuid' => $fromSlot->uuid]);
                    $occupant->update(['slot_uuid' => $toSlot->uuid]);
                    $this->recordResourceMoved($toOccupant, $toSlot->uuid, $fromSlot->uuid, $resourceQty, $actor);
                } else {
                    return $this->noop('item');
                }
            } else {
                $occupant->update(['slot_uuid' => $toSlot->uuid]);
            }

            $this->recordItemMoved($occupant, $fromSlot->uuid, $toSlot->uuid, $actor);
            $this->maybeSyncTradeMove($actor, $fromSlot, $toSlot, $occupant);

            return ['type' => 'item', 'uuid' => $occupant->uuid];
        }

        /** @var Resources $occupant */
        if (!$this->slotFitService->occupantFitsRegularSlot($occupant, $toSlot)) {
            return $this->noop('resource');
        }

        $result = $this->moveResourceBetweenRegularSlots($actor, $fromSlot, $toSlot, $occupant, $toOccupant, $quantity);
        $this->maybeSyncTradeMove($actor, $fromSlot, $toSlot, $occupant);

        return $result;
    }

    private function moveResourceBetweenRegularSlots(
        Character $actor,
        Slot $fromSlot,
        Slot $toSlot,
        Resources $occupant,
        Item|Resources|null $toOccupant,
        ?int $quantity
    ): array {
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
            if (in_array($occupant->template_slug, ['gold', 'experience'], true)) {
                throw new \RuntimeException('Валюту нельзя перемещать вручную');
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
        if (!in_array($tempStorage->storage_type, ['trade', 'craft', 'disassemble', 'quest'], true)) {
            throw new \RuntimeException('Временные слоты поддерживаются только для обмена, станций и квеста');
        }

        $fromCell = ['kind' => 'regular', 'cell' => $fromSlot];
        $toCell = ['kind' => 'temporary', 'cell' => $toTempSlot];

        if ($this->slotFitService->isManualMoveBlocked($actor, $occupant, $fromCell, $toCell)) {
            return $this->noop($occupant instanceof Item ? 'item' : 'resource');
        }

        if (!$this->slotFitService->occupantFitsTemporarySlot($actor, $occupant, $toTempSlot)) {
            return $this->noop($occupant instanceof Item ? 'item' : 'resource');
        }

        $toOccupant = $this->provisioningService->getOccupantForTemporarySlot($toTempSlot);
        $isCraft = $tempStorage->storage_type === 'craft';
        $isDisassemble = $tempStorage->storage_type === 'disassemble';
        $isStation = $isCraft || $isDisassemble;
        $isQuest = $tempStorage->storage_type === 'quest';

        if ($occupant instanceof Item) {
            if ($quantity !== null && $quantity !== 1) {
                throw new \RuntimeException('Предмет нельзя разделить');
            }
            if ($toOccupant) {
                throw new \RuntimeException($isStation ? 'Слот станции занят' : ($isQuest ? 'Слот квеста занят' : 'Целевой слот обмена занят'));
            }

            $occupant->update(['buffer_slot_uuid' => $toTempSlot->uuid]);
            if (!$isStation && !$isQuest) {
                $this->syncTradeItemOnOverlay($actor, $occupant, $toTempSlot);
            }

            $this->maybeSyncCraftStation($actor, $toTempSlot);

            return ['type' => 'item', 'uuid' => $occupant->uuid, 'buffer_slot_uuid' => $toTempSlot->uuid];
        }

        /** @var Resources $occupant */
        return $this->overlayResourceToTemporary($actor, $fromSlot, $toTempSlot, $occupant, $toOccupant, $quantity, $isCraft, $isDisassemble, $isQuest);
    }

    private function overlayResourceToTemporary(
        Character $actor,
        Slot $fromSlot,
        TemporarySlot $toTempSlot,
        Resources $occupant,
        Item|Resources|null $toOccupant,
        ?int $quantity,
        bool $isCraft = false,
        bool $isDisassemble = false,
        bool $isQuest = false
    ): array {
        if ($toOccupant instanceof Resources) {
            if ($toOccupant->template_slug !== $occupant->template_slug) {
                throw new \RuntimeException(($isCraft || $isDisassemble) ? 'Слот станции занят' : ($isQuest ? 'Слот квеста занят' : 'Целевой слот обмена занят'));
            }

            $moveQty = $quantity ?? $occupant->quantity;
            if ($moveQty < 1 || $moveQty > $occupant->quantity) {
                throw new \RuntimeException('Некорректное количество');
            }

            $maxStack = $occupant->max_stack;
            $space = $maxStack === null ? $moveQty : max(0, $maxStack - $toOccupant->quantity);
            $merged = min($moveQty, $space);

            if ($merged < 1) {
                throw new \RuntimeException(($isCraft || $isDisassemble) ? 'Слот станции занят' : ($isQuest ? 'Слот квеста занят' : 'Целевой слот обмена занят'));
            }

            if ($merged < $occupant->quantity) {
                $occupant->update(['quantity' => $occupant->quantity - $merged]);
            } else {
                $occupant->delete();
            }

            $toOccupant->update(['quantity' => $toOccupant->quantity + $merged]);
            if (!$isCraft && !$isDisassemble && !$isQuest) {
                $this->syncTradeResourceOnOverlay($actor, $toOccupant, $toTempSlot, $merged);
            }

            return [
                'type' => 'resource',
                'uuid' => $toOccupant->uuid,
                'quantity' => $merged,
                'buffer_slot_uuid' => $toTempSlot->uuid,
            ];
        }

        if ($toOccupant) {
            throw new \RuntimeException(($isCraft || $isDisassemble) ? 'Слот станции занят' : ($isQuest ? 'Слот квеста занят' : 'Целевой слот обмена занят'));
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

        $tradedResource->update(['buffer_slot_uuid' => $toTempSlot->uuid]);
        if (!$isCraft && !$isDisassemble && !$isQuest) {
            $this->syncTradeResourceOnOverlay($actor, $tradedResource, $toTempSlot, $moveQty);
        }

        $this->maybeSyncCraftStation($actor, $toTempSlot);

        return [
            'type' => 'resource',
            'uuid' => $tradedResource->uuid,
            'quantity' => $moveQty,
            'buffer_slot_uuid' => $toTempSlot->uuid,
        ];
    }

    private function moveTemporaryToRegular(
        Character $actor,
        TemporarySlot $fromTempSlot,
        Slot $toSlot,
        Item|Resources $occupant,
        ?int $quantity
    ): array {
        $fromCell = ['kind' => 'temporary', 'cell' => $fromTempSlot];
        $toCell = ['kind' => 'regular', 'cell' => $toSlot];

        if ($this->slotFitService->isManualMoveBlocked($actor, $occupant, $fromCell, $toCell)) {
            return $this->noop($occupant instanceof Item ? 'item' : 'resource');
        }

        if (!$this->slotFitService->occupantFitsRegularSlot($occupant, $toSlot)) {
            return $this->noop($occupant instanceof Item ? 'item' : 'resource');
        }

        $tempStorage = Storage::where('uuid', $fromTempSlot->storage_uuid)->firstOrFail();
        $isCraft = $tempStorage->storage_type === 'craft';
        $isDisassemble = $tempStorage->storage_type === 'disassemble';
        $isCorpse = $tempStorage->storage_type === 'corpse';
        $isStation = $isCraft || $isDisassemble;
        $isQuest = $tempStorage->storage_type === 'quest';
        $isPostInbox = $tempStorage->storage_type === 'post_inbox';
        $toOccupant = $this->getTargetOccupant($toCell);

        if ($occupant instanceof Item) {
            if ($toOccupant && $toSlot->uuid !== $occupant->slot_uuid) {
                throw new \RuntimeException('Целевой слот занят');
            }

            if ($isStation && $toSlot->uuid === $occupant->slot_uuid) {
                $occupant->update(['buffer_slot_uuid' => null]);
                $this->maybeSyncCraftStation($actor, $fromTempSlot);

                return ['type' => 'item', 'uuid' => $occupant->uuid];
            }

            if ($toOccupant) {
                throw new \RuntimeException('Целевой слот занят');
            }

            $occupant->update([
                'buffer_slot_uuid' => null,
                'slot_uuid' => $toSlot->uuid,
            ]);
            if (!$isStation && !$isQuest && !$isCorpse) {
                $this->removeTradeItemForOccupant($actor, $occupant);
            }

            $this->maybeSyncCraftStation($actor, $fromTempSlot);
            if ($isCorpse) {
                $this->maybeDeactivateEmptyCorpse($fromTempSlot);
            }
            if ($isPostInbox && !$this->slotCellResolver->getOccupantForTemporarySlot($fromTempSlot)) {
                $fromTempSlot->update(['active' => false]);
            }

            return ['type' => 'item', 'uuid' => $occupant->uuid];
        }

        /** @var Resources $occupant */
        if ($toOccupant instanceof Item) {
            return $this->noop('resource');
        }

        if ($isStation && $toSlot->uuid === $occupant->slot_uuid) {
            $occupant->update(['buffer_slot_uuid' => null]);
            $this->maybeSyncCraftStation($actor, $fromTempSlot);

            return ['type' => 'resource', 'uuid' => $occupant->uuid, 'quantity' => $occupant->quantity];
        }

        $moveQty = $quantity ?? $occupant->quantity;

        if (!$toOccupant) {
            $occupant->update([
                'buffer_slot_uuid' => null,
                'slot_uuid' => $toSlot->uuid,
            ]);
            if (!$isStation && !$isQuest && !$isCorpse) {
                $this->removeTradeItemForOccupant($actor, $occupant);
            }

            if ($isCorpse) {
                $this->maybeDeactivateEmptyCorpse($fromTempSlot);
            }

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
            if (!$isQuest && !$isCorpse) {
                $this->removeTradeItemForOccupant($actor, $occupant);
            }

            if ($isCorpse) {
                $this->maybeDeactivateEmptyCorpse($fromTempSlot);
            }

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
        $fromCell = ['kind' => 'temporary', 'cell' => $fromTempSlot];
        $toCell = ['kind' => 'temporary', 'cell' => $toTempSlot];

        if ($this->slotFitService->isManualMoveBlocked($actor, $occupant, $fromCell, $toCell)) {
            return $this->noop($occupant instanceof Item ? 'item' : 'resource');
        }

        if (!$this->slotFitService->occupantFitsTemporarySlot($actor, $occupant, $toTempSlot)) {
            return $this->noop($occupant instanceof Item ? 'item' : 'resource');
        }

        $fromStorage = Storage::where('uuid', $fromTempSlot->storage_uuid)->firstOrFail();
        $toStorage = Storage::where('uuid', $toTempSlot->storage_uuid)->firstOrFail();
        $fromStation = in_array($fromStorage->storage_type, ['craft', 'disassemble'], true);
        $toCraft = $toStorage->storage_type === 'craft';
        $toDisassemble = $toStorage->storage_type === 'disassemble';
        $toStation = $toCraft || $toDisassemble;

        $toOccupant = $this->provisioningService->getOccupantForTemporarySlot($toTempSlot);

        if ($occupant instanceof Resources && $toOccupant instanceof Resources
            && $occupant->template_slug === $toOccupant->template_slug) {
            $maxStack = $occupant->max_stack;
            $moveQty = $occupant->quantity;
            $space = $maxStack === null ? $moveQty : max(0, $maxStack - $toOccupant->quantity);
            $merged = min($moveQty, $space);

            if ($merged > 0) {
                $toOccupant->update(['quantity' => $toOccupant->quantity + $merged]);
                if ($merged === $occupant->quantity) {
                    $occupant->delete();
                } else {
                    $occupant->update(['quantity' => $occupant->quantity - $merged]);
                }
                if (!$fromStation && !$toStation) {
                    $this->updateTradeItemTempSlot($actor, $toOccupant, $toTempSlot);
                }

                $this->maybeSyncCraftStation($actor, $fromTempSlot);
                $this->maybeSyncCraftStation($actor, $toTempSlot);

                return [
                    'type' => 'resource',
                    'uuid' => $toOccupant->uuid,
                    'quantity' => $merged,
                    'buffer_slot_uuid' => $toTempSlot->uuid,
                ];
            }
        }

        if (!$toOccupant) {
            $occupant->update(['buffer_slot_uuid' => $toTempSlot->uuid]);
            if (!$fromStation && !$toStation) {
                $this->updateTradeItemTempSlot($actor, $occupant, $toTempSlot);
            }

            $this->maybeSyncCraftStation($actor, $fromTempSlot);
            $this->maybeSyncCraftStation($actor, $toTempSlot);

            return [
                'type' => $occupant instanceof Item ? 'item' : 'resource',
                'uuid' => $occupant->uuid,
                'buffer_slot_uuid' => $toTempSlot->uuid,
            ];
        }

        $fromUuid = $occupant->buffer_slot_uuid;
        $toUuid = $toOccupant->buffer_slot_uuid;
        $occupant->update(['buffer_slot_uuid' => $toUuid]);
        $toOccupant->update(['buffer_slot_uuid' => $fromUuid]);

        $this->maybeSyncCraftStation($actor, $fromTempSlot);
        $this->maybeSyncCraftStation($actor, $toTempSlot);

        return ['type' => 'swap', 'swapped' => true];
    }

    private function maybeSyncTradeMove(
        Character $actor,
        Slot $fromSlot,
        Slot $toSlot,
        Item|Resources $occupant,
    ): void {
        $fromStorage = Storage::where('uuid', $fromSlot->storage_uuid)->first();
        $toStorage = Storage::where('uuid', $toSlot->storage_uuid)->first();
        if (!$fromStorage || !$toStorage) {
            return;
        }

        if ($this->playerStorageAccess->isTradeableSourceStorage($actor, $fromStorage) && $toStorage->storage_type === 'trade') {
            $trade = $this->getActiveTrade($actor);
            if (!$trade) {
                throw new \RuntimeException('Нет активного обмена');
            }

            if ($occupant instanceof Item) {
                TradeItem::firstOrCreate(
                    [
                        'trade_uuid' => $trade->uuid,
                        'item_uuid' => $occupant->uuid,
                    ],
                    [
                        'character_uuid' => $actor->uuid,
                        'resource_uuid' => null,
                        'origin_slot_uuid' => $fromSlot->uuid,
                        'quantity' => 1,
                    ]
                );
            } else {
                TradeItem::firstOrCreate(
                    [
                        'trade_uuid' => $trade->uuid,
                        'resource_uuid' => $occupant->uuid,
                    ],
                    [
                        'character_uuid' => $actor->uuid,
                        'item_uuid' => null,
                        'origin_slot_uuid' => $fromSlot->uuid,
                        'template_slug' => $occupant->template_slug,
                        'quantity' => $occupant->quantity,
                    ]
                );
            }

            $trade->update(['initiator_accepted' => false, 'partner_accepted' => false]);
            $this->recordTradeOverlayUpdated($actor, $trade);

            return;
        }

        if ($fromStorage->storage_type === 'trade' && $this->playerStorageAccess->isTradeReturnDestination($actor, $toStorage)) {
            $this->removeTradeItemForOccupant($actor, $occupant);
        }
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
