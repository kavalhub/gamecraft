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
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Лут на трупе NPC: temporary_slots принадлежат storage монстра,
 * character_uuid на слоте — право на claim (null = общедоступно).
 */
class CorpseLootService
{
    public const LOOT_SLOT_COUNT = 8;

    public function __construct(
        private SlotCellResolver $slotCellResolver,
        private SlotDepositService $slotDepositService,
        private CurrencyService $currencyService,
        private ExperienceService $experienceService,
    ) {}

    public function spawnCorpse(Character $killer, string $encounterSlug, string $displayName): Character
    {
        return Character::create([
            'uuid' => Str::uuid()->toString(),
            'user_uuid' => null,
            'character_type' => 'corpse',
            'name' => $displayName,
            'active' => true,
        ]);
    }

    public function ensureCorpseStorage(Character $corpse): Storage
    {
        return Storage::firstOrCreate(
            [
                'characters_uuid' => $corpse->uuid,
                'storage_type' => 'corpse',
            ],
            [
                'name' => $corpse->name,
                'active' => true,
            ]
        );
    }

    public function provisionLootSlots(Character $corpse, Character $claimer, int $count = self::LOOT_SLOT_COUNT): void
    {
        $storage = $this->ensureCorpseStorage($corpse);

        $existing = TemporarySlot::where('storage_uuid', $storage->uuid)->count();

        for ($i = $existing; $i < $count; $i++) {
            TemporarySlot::create([
                'uuid' => Str::uuid()->toString(),
                'storage_uuid' => $storage->uuid,
                'character_uuid' => $claimer->uuid,
                'slot_index' => $i,
                'active' => true,
            ]);
        }
    }

    public function getLootSlots(Character $corpse): Collection
    {
        $storage = $this->ensureCorpseStorage($corpse);

        return TemporarySlot::where('storage_uuid', $storage->uuid)
            ->where('active', true)
            ->orderBy('slot_index')
            ->get();
    }

    public function getLootSlotsForClaimer(Character $player): Collection
    {
        return TemporarySlot::where('character_uuid', $player->uuid)
            ->where('active', true)
            ->whereNotNull('timestamps_end')
            ->where('timestamps_end', '>', now())
            ->orderByDesc('id')
            ->get();
    }

    public function hasUnclaimedLoot(Character $player): bool
    {
        foreach ($this->getLootSlotsForClaimer($player) as $slot) {
            if ($this->slotCellResolver->getOccupantForTemporarySlot($slot)) {
                return true;
            }
        }

        return false;
    }

    public function clearExpiredLootForPlayer(Character $player): int
    {
        return $this->clearSlots(
            TemporarySlot::where('character_uuid', $player->uuid)
                ->whereNotNull('timestamps_end')
                ->where('timestamps_end', '<=', now())
                ->get()
        );
    }

    public function clearCorpse(Character $corpse): void
    {
        $this->clearSlots($this->getLootSlots($corpse));
        $corpse->update(['active' => false]);
    }

  /**
   * @param  array<string, int>  $outputs  resources only
   * @return array{corpse_uuid: string, deposited: array<string, int>}
   */
    public function createCorpseWithLoot(
        Character $killer,
        string $encounterSlug,
        string $displayName,
        array $outputs,
        CarbonInterface $expiresAt,
    ): array {
        $corpse = $this->spawnCorpse($killer, $encounterSlug, $displayName);
        $this->provisionLootSlots($corpse, $killer);
        $deposited = $this->depositResourcesOnCorpse($corpse, $killer, $outputs);

        foreach ($this->getLootSlots($corpse) as $slot) {
            if ($this->slotCellResolver->getOccupantForTemporarySlot($slot)) {
                $slot->update(['timestamps_end' => $expiresAt]);
            }
        }

        return [
            'corpse_uuid' => $corpse->uuid,
            'deposited' => $deposited,
        ];
    }

  /**
   * @param  array<string, int>  $outputs
   * @return array<string, int>
   */
    public function depositResourcesOnCorpse(Character $corpse, Character $claimer, array $outputs): array
    {
        $deposited = [];
        $slots = $this->getLootSlots($corpse);
        $slotIndex = 0;

        foreach ($outputs as $templateSlug => $quantity) {
            if ($quantity < 1) {
                continue;
            }

            $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
            $remaining = (int) $quantity;
            $maxStack = $template->max_stack;

            while ($remaining > 0) {
                /** @var TemporarySlot|null $cell */
                $cell = $this->findPartialOnCorpse($slots, $templateSlug, $maxStack)
                    ?? $this->findEmptyLootSlot($slots, $slotIndex);

                if (!$cell) {
                    throw new \RuntimeException('Нет свободных слотов на трупе');
                }

                $existing = Resources::where('slot_uuid', $cell->uuid)
                    ->whereNull('buffer_slot_uuid')
                    ->where('template_slug', $templateSlug)
                    ->first();

                if ($existing && $maxStack !== null && $existing->quantity < $maxStack) {
                    $space = $maxStack - $existing->quantity;
                    $toAdd = min($remaining, $space);
                    $existing->update(['quantity' => $existing->quantity + $toAdd]);
                    $remaining -= $toAdd;
                } else {
                    $toAdd = $maxStack === null ? $remaining : min($remaining, $maxStack);
                    Resources::create([
                        'uuid' => Str::uuid()->toString(),
                        'slot_uuid' => $cell->uuid,
                        'buffer_slot_uuid' => null,
                        'recipe_slug' => $templateSlug,
                        'template_slug' => $templateSlug,
                        'slot_type' => $template->slot_type,
                        'max_stack' => $template->max_stack,
                        'quantity' => $toAdd,
                    ]);
                    $remaining -= $toAdd;
                }

                $deposited[$templateSlug] = ($deposited[$templateSlug] ?? 0) + $toAdd;
                $slotIndex++;
            }
        }

        return $deposited;
    }

    public function assertCanClaimSlot(Character $actor, TemporarySlot $slot): void
    {
        if ($slot->timestamps_end && $slot->timestamps_end->isPast()) {
            throw new \RuntimeException('Время на получение добычи истекло');
        }

        if ($slot->character_uuid === null) {
            return;
        }

        if ($slot->character_uuid !== $actor->uuid) {
            throw new \RuntimeException('Эта добыча принадлежит другому игроку');
        }
    }

    public function refuseLoot(Character $corpse): void
    {
        foreach ($this->getLootSlots($corpse) as $slot) {
            if ($this->slotCellResolver->getOccupantForTemporarySlot($slot)) {
                $slot->update(['character_uuid' => null]);
            }
        }
    }

    public function claimCorpseToInventory(Character $actor, Character $corpse): int
    {
        $moveService = app(StorageMoveService::class);
        $moved = 0;

        foreach ($this->getLootSlots($corpse) as $slot) {
            $this->assertCanClaimSlot($actor, $slot);

            $occupant = $this->slotCellResolver->getOccupantForTemporarySlot($slot);
            if (!$occupant) {
                continue;
            }

            if ($occupant instanceof Item) {
                throw new \RuntimeException('Предметы с трупа пока не поддерживаются');
            }

            /** @var Resources $resource */
            $resource = $occupant;

            if ($resource->template_slug === 'gold') {
                $qty = $resource->quantity;
                $resource->delete();
                $this->currencyService->credit($actor, $qty, 'encounter.loot');
                $moved++;
                continue;
            }

            if ($resource->template_slug === 'experience') {
                $qty = $resource->quantity;
                $resource->delete();
                $this->experienceService->credit($actor, $qty, 'encounter.loot');
                $moved++;
                continue;
            }

            $targetSlot = $this->slotDepositService->resolveInventoryTargetSlot($actor, $resource);
            $moveService->move($actor, $slot->uuid, $targetSlot->uuid);
            $moved++;
        }

        foreach ($this->getLootSlots($corpse) as $slot) {
            $slot->update(['timestamps_end' => null]);
        }

        if (!$this->getLootSlots($corpse)->contains(fn (TemporarySlot $s) => $this->slotCellResolver->getOccupantForTemporarySlot($s))) {
            $corpse->update(['active' => false]);
        }

        return $moved;
    }

    /**
     * @param  Collection<int, TemporarySlot>  $slots
     */
    private function findPartialOnCorpse(Collection $slots, string $templateSlug, ?int $maxStack): ?TemporarySlot
    {
        if ($maxStack === null || $maxStack < 2) {
            return null;
        }

        foreach ($slots as $slot) {
            $resource = Resources::where('slot_uuid', $slot->uuid)
                ->whereNull('buffer_slot_uuid')
                ->where('template_slug', $templateSlug)
                ->where('quantity', '<', $maxStack)
                ->first();

            if ($resource) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, TemporarySlot>  $slots
     */
    private function findEmptyLootSlot(Collection $slots, int $startIndex): ?TemporarySlot
    {
        $ordered = $slots->sortBy('slot_index')->values();
        $count = $ordered->count();

        for ($i = 0; $i < $count; $i++) {
            $slot = $ordered->get(($startIndex + $i) % $count);
            if ($slot && $this->slotCellResolver->isTemporarySlotEmpty($slot)) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, TemporarySlot>  $slots
     */
    private function clearSlots(Collection $slots): int
    {
        $cleared = 0;

        foreach ($slots as $slot) {
            $occupant = $this->slotCellResolver->getOccupantForTemporarySlot($slot);
            if ($occupant) {
                $occupant->delete();
                $cleared++;
            }
            $slot->update(['timestamps_end' => null, 'active' => false]);
        }

        return $cleared;
    }
}
