<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterQuest;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\Quest;
use App\Models\QuestObjective;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuestStorageService
{
    public const SLOT_ROLE_GRANT = 'grant';
    public const SLOT_ROLE_REQUIREMENT = 'requirement';
    public const SLOT_ROLE_REWARD = 'reward';

    public function __construct(
        private StorageProvisioningService $provisioningService,
        private InventoryService $inventoryService,
        private WorldStorageService $worldStorageService,
        private EventStore $eventStore,
    ) {}

    public function ensureQuestStorage(Character $character): Storage
    {
        return $this->provisioningService->grantStorage($character, 'quest');
    }

    public function getTemporarySlots(Character $character, ?string $questSlug = null): Collection
    {
        $storage = $this->ensureQuestStorage($character);

        $query = TemporarySlot::where('storage_uuid', $storage->uuid)
            ->where('character_uuid', $character->uuid)
            ->where('active', true)
            ->orderBy('slot_index');

        if ($questSlug !== null) {
            $query->where('quest_slug', $questSlug);
        }

        return $query->get();
    }

    public function slotRole(TemporarySlot $slot): string
    {
        if ($slot->slot_role) {
            return match ($slot->slot_role) {
                self::SLOT_ROLE_GRANT => 'quest_grant',
                self::SLOT_ROLE_REQUIREMENT => 'quest_requirement',
                self::SLOT_ROLE_REWARD => 'quest_turnin',
                default => 'quest_turnin',
            };
        }

        return $slot->slot_index < 6 ? 'quest_grant' : 'quest_turnin';
    }

    public function isQuestTemporarySlot(TemporarySlot $slot): bool
    {
        $storage = Storage::where('uuid', $slot->storage_uuid)->first();

        return $storage?->storage_type === 'quest';
    }

    public function prepareOfferSession(Character $character, Quest $quest): void
    {
        $grantCount = $this->countGrantSlots($quest);
        if ($grantCount === 0) {
            return;
        }

        $existing = $this->getSlotsByRole($character, $quest->slug, self::SLOT_ROLE_GRANT);
        if ($existing->isNotEmpty()) {
            return;
        }

        $this->ensureQuestStorage($character);
        $this->createSlots($character, $quest->slug, self::SLOT_ROLE_GRANT, $grantCount);
        $this->populateGrantSlots($character, $quest);
    }

    public function ensureRewardPreview(Character $character, Quest $quest): void
    {
        $rewardCount = $this->countRewardSlots($quest);
        if ($rewardCount <= 0) {
            return;
        }

        $this->ensureQuestStorage($character);

        if ($this->getSlotsByRole($character, $quest->slug, self::SLOT_ROLE_REWARD)->count() < $rewardCount) {
            $this->destroySlotsByRole($character, $quest->slug, self::SLOT_ROLE_REWARD);
            $this->createSlots($character, $quest->slug, self::SLOT_ROLE_REWARD, $rewardCount);
        }

        $this->populateRewardSlots($character, $quest);
    }

    public function hasStarterItemInInventory(Character $character, string $templateSlug): bool
    {
        return $this->findQuestItemInInventory($character, $templateSlug) !== null;
    }

    public function ensureActiveSession(Character $character, Quest $quest): void
    {
        $this->ensureQuestStorage($character);

        $requirementCount = $this->countRequirementSlots($quest);
        $rewardCount = $this->countRewardSlots($quest);

        if ($this->getSlotsByRole($character, $quest->slug, self::SLOT_ROLE_REQUIREMENT)->count() < $requirementCount) {
            $this->destroySlotsByRole($character, $quest->slug, self::SLOT_ROLE_REQUIREMENT);
            $this->createSlots($character, $quest->slug, self::SLOT_ROLE_REQUIREMENT, $requirementCount);
        }

        if ($this->getSlotsByRole($character, $quest->slug, self::SLOT_ROLE_REWARD)->count() < $rewardCount) {
            $this->destroySlotsByRole($character, $quest->slug, self::SLOT_ROLE_REWARD);
            $this->createSlots($character, $quest->slug, self::SLOT_ROLE_REWARD, $rewardCount);
        }

        $this->autoPlaceRequirements($character, $quest);
        $this->populateRewardSlots($character, $quest);
    }

    public function assertInventoryFitsGrants(Character $character, Quest $quest): void
    {
        $grants = $quest->accept_grants ?? [];

        foreach ($grants['items'] ?? [] as $itemGrant) {
            $templateSlug = $itemGrant['template_slug'] ?? null;
            $count = (int) ($itemGrant['count'] ?? 1);
            if (!$templateSlug) {
                continue;
            }

            $max = $this->inventoryService->getMaxAddableQuantity($character, $templateSlug);
            if ($max < $count) {
                throw new \RuntimeException('Недостаточно места в инвентаре для предметов квеста');
            }
        }

        foreach ($grants['resources'] ?? [] as $templateSlug => $quantity) {
            $qty = (int) $quantity;
            if ($qty <= 0) {
                continue;
            }

            $max = $this->inventoryService->getMaxAddableResourceQuantity($character, (string) $templateSlug);
            if ($max < $qty) {
                throw new \RuntimeException('Недостаточно места в инвентаре для ресурсов квеста');
            }
        }
    }

    public function autolootGrantsToInventory(Character $character, Quest $quest): void
    {
        $grantSlots = $this->getSlotsByRole($character, $quest->slug, self::SLOT_ROLE_GRANT);

        if ($grantSlots->isNotEmpty()) {
            $tempUuids = $grantSlots->pluck('uuid');

            foreach (Item::whereIn('temporary_slot_uuid', $tempUuids)->get() as $item) {
                $this->transferOverlayItemToInventory($character, $item);
            }

            foreach (Resources::whereIn('temporary_slot_uuid', $tempUuids)->get() as $resource) {
                $this->transferOverlayResourceToInventory($character, $resource);
            }

            $this->destroySlotsByRole($character, $quest->slug, self::SLOT_ROLE_GRANT);

            return;
        }

        $grants = $quest->accept_grants ?? [];

        foreach ($grants['items'] ?? [] as $itemGrant) {
            $templateSlug = $itemGrant['template_slug'] ?? null;
            $stage = $itemGrant['stage'] ?? 'item';
            $count = (int) ($itemGrant['count'] ?? 1);
            if (!$templateSlug) {
                continue;
            }

            $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
            $recipeSlug = $itemGrant['recipe_slug'] ?? $template->recipe_slug ?? 'quest_item_stub';

            for ($i = 0; $i < $count; $i++) {
                $this->inventoryService->addItem(
                    $character,
                    $templateSlug,
                    $stage,
                    null,
                    $recipeSlug,
                    null,
                    null
                );
            }
        }

        foreach ($grants['resources'] ?? [] as $templateSlug => $quantity) {
            $qty = (int) $quantity;
            if ($qty > 0) {
                $this->grantResourceToInventory($character, (string) $templateSlug, $qty);
            }
        }
    }

    public function executeTurnInExchange(Character $character, Quest $quest, CharacterQuest $characterQuest): void
    {
        $this->assertRequirementsMet($character, $quest);
        $this->assertRewardsFitInventory($character, $quest);

        $requirementSlots = $this->getSlotsByRole($character, $quest->slug, self::SLOT_ROLE_REQUIREMENT);
        $tempUuids = $requirementSlots->pluck('uuid');

        foreach (Item::whereIn('temporary_slot_uuid', $tempUuids)->get() as $item) {
            $item->update(['temporary_slot_uuid' => null]);
            $this->worldStorageService->depositItem($item);
        }

        if ($quest->starter_item_template_slug) {
            $starter = $this->findQuestItemInInventory($character, $quest->starter_item_template_slug);
            if ($starter) {
                $this->worldStorageService->depositItem($starter);
            }
        }

        $rewardSlots = $this->getSlotsByRole($character, $quest->slug, self::SLOT_ROLE_REWARD);
        $rewardTempUuids = $rewardSlots->pluck('uuid');

        foreach (Item::whereIn('temporary_slot_uuid', $rewardTempUuids)->get() as $item) {
            $this->transferOverlayItemToInventory($character, $item);
        }

        foreach (Resources::whereIn('temporary_slot_uuid', $rewardTempUuids)->get() as $resource) {
            $this->transferOverlayResourceToInventory($character, $resource);
        }

        $this->destroyQuestSession($character, $quest->slug);
    }

    public function autoPlaceRequirements(Character $character, Quest $quest): void
    {
        $requirementSlots = $this->getSlotsByRole($character, $quest->slug, self::SLOT_ROLE_REQUIREMENT);
        $slotIndex = 0;

        foreach ($quest->objectives as $objective) {
            if ($objective->type !== 'have_item') {
                continue;
            }

            $templateSlug = $objective->config['template_slug'] ?? null;
            $stage = $objective->config['stage'] ?? 'item';
            if (!$templateSlug) {
                continue;
            }

            for ($i = 0; $i < $objective->required_count; $i++) {
                if ($slotIndex >= $requirementSlots->count()) {
                    break 2;
                }

                $tempSlot = $requirementSlots[$slotIndex];
                if ($this->provisioningService->getOccupantForTemporarySlot($tempSlot)) {
                    $slotIndex++;
                    continue;
                }

                $inventoryItem = $this->findItemInInventory($character, $templateSlug, $stage);
                if (!$inventoryItem) {
                    $slotIndex++;
                    continue;
                }

                $inventoryItem->update(['temporary_slot_uuid' => $tempSlot->uuid]);
                $slotIndex++;
            }
        }
    }

    public function countRequirementItems(Character $character, string $questSlug, string $templateSlug, string $stage): int
    {
        $requirementSlots = $this->getSlotsByRole($character, $questSlug, self::SLOT_ROLE_REQUIREMENT);
        $tempUuids = $requirementSlots->pluck('uuid');

        return Item::whereIn('temporary_slot_uuid', $tempUuids)
            ->where('template_slug', $templateSlug)
            ->where('stage', $stage)
            ->count();
    }

    public function populateGrantSlots(Character $character, Quest $quest): void
    {
        $grants = $quest->accept_grants ?? [];
        $grantSlots = $this->getSlotsByRole($character, $quest->slug, self::SLOT_ROLE_GRANT);
        $slotIndex = 0;

        foreach ($grants['items'] ?? [] as $itemGrant) {
            $templateSlug = $itemGrant['template_slug'] ?? null;
            $stage = $itemGrant['stage'] ?? 'item';
            $count = (int) ($itemGrant['count'] ?? 1);
            if (!$templateSlug) {
                continue;
            }

            $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
            $recipeSlug = $itemGrant['recipe_slug'] ?? $template->recipe_slug ?? 'quest_item_stub';

            for ($i = 0; $i < $count; $i++) {
                if ($slotIndex >= $grantSlots->count()) {
                    throw new \RuntimeException('Недостаточно слотов выдачи квеста');
                }
                $tempSlot = $grantSlots[$slotIndex++];
                if ($this->provisioningService->getOccupantForTemporarySlot($tempSlot)) {
                    continue;
                }
                $this->spawnItemOnTemporarySlot($character, $tempSlot, $templateSlug, $stage, $recipeSlug);
            }
        }

        foreach ($grants['resources'] ?? [] as $templateSlug => $quantity) {
            $qty = (int) $quantity;
            if ($qty <= 0) {
                continue;
            }
            if ($slotIndex >= $grantSlots->count()) {
                throw new \RuntimeException('Недостаточно слотов выдачи квеста');
            }
            $tempSlot = $grantSlots[$slotIndex++];
            if ($this->provisioningService->getOccupantForTemporarySlot($tempSlot)) {
                continue;
            }
            $this->spawnResourceOnTemporarySlot($character, $tempSlot, (string) $templateSlug, $qty);
        }
    }

    public function populateRewardSlots(Character $character, Quest $quest): void
    {
        $rewards = $quest->rewards ?? [];
        $rewardSlots = $this->getSlotsByRole($character, $quest->slug, self::SLOT_ROLE_REWARD);
        $slotIndex = 0;

        foreach ($rewards['resources'] ?? [] as $templateSlug => $quantity) {
            $qty = (int) $quantity;
            if ($qty <= 0) {
                continue;
            }
            if ($slotIndex >= $rewardSlots->count()) {
                throw new \RuntimeException('Недостаточно слотов награды квеста');
            }
            $tempSlot = $rewardSlots[$slotIndex++];
            if ($this->provisioningService->getOccupantForTemporarySlot($tempSlot)) {
                continue;
            }
            $this->spawnResourceOnTemporarySlot($character, $tempSlot, (string) $templateSlug, $qty);
        }

        foreach ($rewards['items'] ?? [] as $itemReward) {
            $templateSlug = is_string($itemReward) ? $itemReward : ($itemReward['template_slug'] ?? null);
            $stage = is_array($itemReward) ? ($itemReward['stage'] ?? 'item') : 'item';
            $count = is_array($itemReward) ? (int) ($itemReward['count'] ?? 1) : 1;
            if (!$templateSlug) {
                continue;
            }

            $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
            $recipeSlug = is_array($itemReward) ? ($itemReward['recipe_slug'] ?? $template->recipe_slug) : $template->recipe_slug;

            for ($i = 0; $i < $count; $i++) {
                if ($slotIndex >= $rewardSlots->count()) {
                    throw new \RuntimeException('Недостаточно слотов награды квеста');
                }
                $tempSlot = $rewardSlots[$slotIndex++];
                if ($this->provisioningService->getOccupantForTemporarySlot($tempSlot)) {
                    continue;
                }
                $this->spawnItemOnTemporarySlot(
                    $character,
                    $tempSlot,
                    $templateSlug,
                    $stage,
                    $recipeSlug,
                    is_array($itemReward) ? ($itemReward['materials_used'] ?? null) : null,
                    is_array($itemReward) ? ($itemReward['stats'] ?? null) : null,
                );
            }
        }
    }

    public function clearOverlays(Character $character, ?string $questSlug = null): int
    {
        $questStorage = $this->ensureQuestStorage($character);
        $backingUuids = $questStorage->slots()->where('slot_type', 'quest_backing')->pluck('uuid');
        $tempSlots = $this->getTemporarySlots($character, $questSlug);
        $tempUuids = $tempSlots->pluck('uuid');

        $cleared = 0;

        $items = Item::whereIn('temporary_slot_uuid', $tempUuids)->get();
        foreach ($items as $item) {
            if ($backingUuids->contains($item->slot_uuid)) {
                $item->delete();
            } else {
                $item->update(['temporary_slot_uuid' => null]);
            }
            $cleared++;
        }

        $resources = Resources::whereIn('temporary_slot_uuid', $tempUuids)->get();
        foreach ($resources as $resource) {
            if ($backingUuids->contains($resource->slot_uuid)) {
                $resource->delete();
            } else {
                $resource->update(['temporary_slot_uuid' => null]);
            }
            $cleared++;
        }

        if ($questSlug) {
            $this->destroyQuestSession($character, $questSlug);
        }

        return $cleared;
    }

    public function getGrantTemporarySlots(Character $character, ?string $questSlug = null): Collection
    {
        if ($questSlug) {
            return $this->getSlotsByRole($character, $questSlug, self::SLOT_ROLE_GRANT);
        }

        return $this->getTemporarySlots($character)
            ->filter(fn (TemporarySlot $slot) => $slot->slot_role === self::SLOT_ROLE_GRANT
                || ($slot->slot_role === null && $slot->slot_index < 6))
            ->values();
    }

    public function getTurnInTemporarySlots(Character $character, ?string $questSlug = null): Collection
    {
        if ($questSlug) {
            return $this->getSlotsByRole($character, $questSlug, self::SLOT_ROLE_REWARD);
        }

        return $this->getTemporarySlots($character)
            ->filter(fn (TemporarySlot $slot) => in_array($slot->slot_role, [self::SLOT_ROLE_REWARD, self::SLOT_ROLE_REQUIREMENT], true)
                || ($slot->slot_role === null && $slot->slot_index >= 6))
            ->values();
    }

    public function getRequirementTemporarySlots(Character $character, string $questSlug): Collection
    {
        return $this->getSlotsByRole($character, $questSlug, self::SLOT_ROLE_REQUIREMENT);
    }

    public function countGrantSlots(Quest $quest): int
    {
        $grants = $quest->accept_grants ?? [];
        $count = 0;

        foreach ($grants['items'] ?? [] as $itemGrant) {
            $count += (int) ($itemGrant['count'] ?? 1);
        }

        foreach ($grants['resources'] ?? [] as $quantity) {
            if ((int) $quantity > 0) {
                $count++;
            }
        }

        return $count;
    }

    public function countRequirementSlots(Quest $quest): int
    {
        return $quest->objectives
            ->filter(fn (QuestObjective $o) => $o->type === 'have_item')
            ->sum(fn (QuestObjective $o) => $o->required_count);
    }

    public function countRewardSlots(Quest $quest): int
    {
        $rewards = $quest->rewards ?? [];
        $count = 0;

        foreach ($rewards['resources'] ?? [] as $quantity) {
            if ((int) $quantity > 0) {
                $count++;
            }
        }

        foreach ($rewards['items'] ?? [] as $itemReward) {
            $count += is_array($itemReward) ? (int) ($itemReward['count'] ?? 1) : 1;
        }

        return $count;
    }

    private function getSlotsByRole(Character $character, string $questSlug, string $role): Collection
    {
        return $this->getTemporarySlots($character, $questSlug)
            ->filter(fn (TemporarySlot $slot) => $slot->slot_role === $role)
            ->values();
    }

    private function createSlots(Character $character, string $questSlug, string $role, int $count): void
    {
        $storage = $this->ensureQuestStorage($character);
        $maxIndex = (int) TemporarySlot::where('storage_uuid', $storage->uuid)
            ->where('character_uuid', $character->uuid)
            ->max('slot_index');

        for ($i = 0; $i < $count; $i++) {
            TemporarySlot::create([
                'uuid' => Str::uuid()->toString(),
                'storage_uuid' => $storage->uuid,
                'character_uuid' => $character->uuid,
                'slot_index' => $maxIndex + 1 + $i,
                'quest_slug' => $questSlug,
                'slot_role' => $role,
                'active' => true,
            ]);
        }
    }

    private function destroySlotsByRole(Character $character, string $questSlug, string $role): void
    {
        $slots = $this->getSlotsByRole($character, $questSlug, $role);
        $this->clearSlotOverlays($character, $slots);
        TemporarySlot::whereIn('uuid', $slots->pluck('uuid'))->delete();
    }

    private function destroyQuestSession(Character $character, string $questSlug): void
    {
        $slots = $this->getTemporarySlots($character, $questSlug);
        $this->clearSlotOverlays($character, $slots);
        TemporarySlot::whereIn('uuid', $slots->pluck('uuid'))->delete();
    }

    private function clearSlotOverlays(Character $character, Collection $slots): void
    {
        $questStorage = $this->ensureQuestStorage($character);
        $backingUuids = $questStorage->slots()->where('slot_type', 'quest_backing')->pluck('uuid');
        $tempUuids = $slots->pluck('uuid');

        foreach (Item::whereIn('temporary_slot_uuid', $tempUuids)->get() as $item) {
            if ($backingUuids->contains($item->slot_uuid)) {
                $item->delete();
            } else {
                $item->update(['temporary_slot_uuid' => null]);
            }
        }

        foreach (Resources::whereIn('temporary_slot_uuid', $tempUuids)->get() as $resource) {
            if ($backingUuids->contains($resource->slot_uuid)) {
                $resource->delete();
            } else {
                $resource->update(['temporary_slot_uuid' => null]);
            }
        }
    }

    private function assertRequirementsMet(Character $character, Quest $quest): void
    {
        foreach ($quest->objectives as $objective) {
            if ($objective->type !== 'have_item') {
                continue;
            }

            $templateSlug = $objective->config['template_slug'] ?? null;
            $stage = $objective->config['stage'] ?? 'item';
            if (!$templateSlug) {
                continue;
            }

            $inSlots = $this->countRequirementItems($character, $quest->slug, $templateSlug, $stage);
            if ($inSlots < $objective->required_count) {
                throw new \RuntimeException('Квест ещё не выполнен');
            }
        }
    }

    private function assertRewardsFitInventory(Character $character, Quest $quest): void
    {
        $rewards = $quest->rewards ?? [];

        foreach ($rewards['items'] ?? [] as $itemReward) {
            $templateSlug = is_string($itemReward) ? $itemReward : ($itemReward['template_slug'] ?? null);
            $count = is_array($itemReward) ? (int) ($itemReward['count'] ?? 1) : 1;
            if (!$templateSlug) {
                continue;
            }

            if ($this->inventoryService->getMaxAddableQuantity($character, $templateSlug) < $count) {
                throw new \RuntimeException('Недостаточно места в инвентаре для награды');
            }
        }

        foreach ($rewards['resources'] ?? [] as $templateSlug => $quantity) {
            $qty = (int) $quantity;
            if ($qty <= 0) {
                continue;
            }

            if ($this->inventoryService->getMaxAddableResourceQuantity($character, (string) $templateSlug) < $qty) {
                throw new \RuntimeException('Недостаточно места в инвентаре для награды');
            }
        }
    }

    private function transferOverlayItemToInventory(Character $character, Item $item): void
    {
        $inventory = $character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $template = ItemTemplate::where('slug', $item->template_slug)->firstOrFail();
        $slot = $this->inventoryService->findFreeSlot($inventory, $template->slot_type);

        if (!$slot) {
            throw new \RuntimeException('Нет свободных слотов в инвентаре');
        }

        $item->update([
            'slot_uuid' => $slot->uuid,
            'temporary_slot_uuid' => null,
        ]);
    }

    private function transferOverlayResourceToInventory(Character $character, Resources $resource): void
    {
        $qty = $resource->quantity;
        $templateSlug = $resource->template_slug;

        $resource->delete();
        $this->grantResourceToInventory($character, $templateSlug, $qty);
    }

    private function grantResourceToInventory(Character $character, string $templateSlug, int $qty): void
    {
        if ($templateSlug === 'gold') {
            app(CurrencyService::class)->credit($character, $qty, 'quest', []);

            return;
        }

        if ($templateSlug === 'experience') {
            app(ExperienceService::class)->credit($character, $qty, 'quest', []);

            return;
        }

        $this->inventoryService->addResource($character, $templateSlug, $qty);
    }

    private function findItemInInventory(Character $character, string $templateSlug, string $stage): ?Item
    {
        $inventoryUuids = $character->storages()
            ->where('storage_type', 'inventory')
            ->pluck('uuid');
        $slotUuids = Slot::whereIn('storage_uuid', $inventoryUuids)->pluck('uuid');

        return Item::whereIn('slot_uuid', $slotUuids)
            ->where('template_slug', $templateSlug)
            ->where('stage', $stage)
            ->whereNull('temporary_slot_uuid')
            ->first();
    }

    private function findQuestItemInInventory(Character $character, string $templateSlug): ?Item
    {
        $inventoryUuids = $character->storages()
            ->where('storage_type', 'inventory')
            ->pluck('uuid');
        $slotUuids = Slot::whereIn('storage_uuid', $inventoryUuids)->pluck('uuid');

        return Item::whereIn('slot_uuid', $slotUuids)
            ->where('template_slug', $templateSlug)
            ->whereNull('temporary_slot_uuid')
            ->first();
    }

    private function spawnItemOnTemporarySlot(
        Character $character,
        TemporarySlot $tempSlot,
        string $templateSlug,
        string $stage,
        ?string $recipeSlug,
        ?array $materialsUsed = null,
        ?array $stats = null,
    ): Item {
        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
        $backingSlot = $this->allocateBackingSlot($character);

        return Item::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $backingSlot->uuid,
            'temporary_slot_uuid' => $tempSlot->uuid,
            'recipe_slug' => $recipeSlug ?? 'quest_item_stub',
            'template_slug' => $templateSlug,
            'stage' => $stage,
            'slot_type' => $template->slot_type,
            'durability' => 100,
            'materials_used' => $materialsUsed,
            'stats' => $stats ?? $template->base_stats,
        ]);
    }

    private function spawnResourceOnTemporarySlot(
        Character $character,
        TemporarySlot $tempSlot,
        string $templateSlug,
        int $quantity,
    ): Resources {
        $template = ItemTemplate::where('slug', $templateSlug)->firstOrFail();
        $backingSlot = $this->allocateBackingSlot($character);

        return Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $backingSlot->uuid,
            'temporary_slot_uuid' => $tempSlot->uuid,
            'recipe_slug' => $templateSlug,
            'template_slug' => $templateSlug,
            'slot_type' => $template->slot_type,
            'max_stack' => $template->max_stack,
            'quantity' => $quantity,
        ]);
    }

    private function allocateBackingSlot(Character $character): Slot
    {
        $storage = $this->ensureQuestStorage($character);
        $backingSlots = $storage->slots()->where('slot_type', 'quest_backing')->orderBy('id')->get();

        foreach ($backingSlots as $slot) {
            $hasItem = Item::where('slot_uuid', $slot->uuid)->exists();
            $hasResource = Resources::where('slot_uuid', $slot->uuid)->exists();
            if (!$hasItem && !$hasResource) {
                return $slot;
            }
        }

        return Slot::create([
            'uuid' => Str::uuid()->toString(),
            'storage_uuid' => $storage->uuid,
            'slot_type' => 'quest_backing',
        ]);
    }
}
