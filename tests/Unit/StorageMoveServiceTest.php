<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\TemporarySlot;
use App\Models\User;
use App\Services\CraftingService;
use App\Services\InventoryService;
use App\Services\StorageMoveService;
use App\Services\StorageProvisioningService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorageMoveServiceTest extends TestCase
{
    use RefreshDatabase;

    private StorageMoveService $moveService;
    private InventoryService $inventoryService;
    private StorageProvisioningService $provisioningService;
    private Character $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->moveService = app(StorageMoveService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->provisioningService = app(StorageProvisioningService::class);

        $user = User::where('email', 'test@example.com')->first();
        $this->player = $user->characters()->where('character_type', 'player')->first();
    }

    public function test_move_item_between_empty_inventory_slots(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $blueprint = app(CraftingService::class)->createBlueprint($this->player, 'craft_wooden_sword');
        $item = app(CraftingService::class)->craftItem($this->player, 'craft_wooden_sword', $blueprint->uuid);

        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $slots = $inventory->slots()->orderBy('id')->get();
        $occupied = Item::whereIn('slot_uuid', $slots->pluck('uuid'))->pluck('slot_uuid')
            ->merge(Resources::whereIn('slot_uuid', $slots->pluck('uuid'))->pluck('slot_uuid'));
        $toSlot = $slots->first(fn ($s) => $s->uuid !== $item->slot_uuid && !$occupied->contains($s->uuid));
        $fromSlot = $slots->firstWhere('uuid', $item->slot_uuid);

        $this->assertNotNull($toSlot);
        $this->moveService->move($this->player, $fromSlot->uuid, $toSlot->uuid);

        $item->refresh();
        $this->assertEquals($toSlot->uuid, $item->slot_uuid);
    }

    public function test_overlay_item_to_trade_temporary_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $blueprint = app(CraftingService::class)->createBlueprint($this->player, 'craft_wooden_sword');
        $item = app(CraftingService::class)->craftItem($this->player, 'craft_wooden_sword', $blueprint->uuid);

        $originalSlotUuid = $item->slot_uuid;
        $this->provisioningService->ensureTradeStorage($this->player);
        $tempSlot = $this->provisioningService->findFreeTradeTemporarySlot($this->player);

        $this->moveService->move($this->player, $originalSlotUuid, $tempSlot->uuid);

        $item->refresh();
        $this->assertEquals($originalSlotUuid, $item->slot_uuid);
        $this->assertEquals($tempSlot->uuid, $item->temporary_slot_uuid);
    }

    public function test_clear_overlay_from_trade_temporary_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $blueprint = app(CraftingService::class)->createBlueprint($this->player, 'craft_wooden_sword');
        $item = app(CraftingService::class)->craftItem($this->player, 'craft_wooden_sword', $blueprint->uuid);

        $this->provisioningService->ensureTradeStorage($this->player);
        $tempSlot = $this->provisioningService->findFreeTradeTemporarySlot($this->player);
        $item->update(['temporary_slot_uuid' => $tempSlot->uuid]);

        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $slots = $inventory->slots()->orderBy('id')->get();
        $occupied = Item::whereIn('slot_uuid', $slots->pluck('uuid'))->pluck('slot_uuid')
            ->merge(Resources::whereIn('slot_uuid', $slots->pluck('uuid'))->pluck('slot_uuid'));
        $targetSlot = $slots->first(fn ($s) => $s->uuid !== $item->slot_uuid && !$occupied->contains($s->uuid));

        $this->assertNotNull($targetSlot);
        $this->moveService->move($this->player, $tempSlot->uuid, $targetSlot->uuid);

        $item->refresh();
        $this->assertNull($item->temporary_slot_uuid);
        $this->assertEquals($targetSlot->uuid, $item->slot_uuid);
    }

    public function test_merge_stackable_resources(): void
    {
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $slots = $inventory->slots()->orderBy('id')->get();
        $occupied = Resources::whereIn('slot_uuid', $slots->pluck('uuid'))->pluck('slot_uuid')
            ->merge(Item::whereIn('slot_uuid', $slots->pluck('uuid'))->pluck('slot_uuid'));
        $freeSlots = $slots->filter(fn ($s) => !$occupied->contains($s->uuid))->values()->take(2);

        Resources::create([
            'slot_uuid' => $freeSlots[0]->uuid,
            'recipe_slug' => 'wood',
            'template_slug' => 'wood',
            'slot_type' => 'material',
            'max_stack' => 20,
            'quantity' => 10,
        ]);

        Resources::create([
            'slot_uuid' => $freeSlots[1]->uuid,
            'recipe_slug' => 'wood',
            'template_slug' => 'wood',
            'slot_type' => 'material',
            'max_stack' => 20,
            'quantity' => 5,
        ]);

        $this->moveService->move($this->player, $freeSlots[1]->uuid, $freeSlots[0]->uuid);

        $this->assertEquals(15, Resources::where('slot_uuid', $freeSlots[0]->uuid)->sum('quantity'));
        $this->assertFalse(Resources::where('slot_uuid', $freeSlots[1]->uuid)->exists());
    }

    public function test_rejects_move_to_foreign_temporary_slot(): void
    {
        $user2 = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);
        $other = Character::create([
            'user_uuid' => $user2->uuid,
            'character_type' => 'player',
            'name' => 'Other',
            'active' => true,
        ]);
        $this->provisioningService->provisionDefaults($other);
        $this->provisioningService->ensureTradeStorage($other);
        $otherTemp = $this->provisioningService->findFreeTradeTemporarySlot($other);

        $this->inventoryService->addResource($this->player, 'wood', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $fromSlot = $inventory->slots()->orderBy('id')->first();

        $this->expectException(\RuntimeException::class);
        $this->moveService->move($this->player, $fromSlot->uuid, $otherTemp->uuid);
    }

    public function test_cannot_move_item_with_temporary_overlay(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $blueprint = app(CraftingService::class)->createBlueprint($this->player, 'craft_wooden_sword');
        $item = app(CraftingService::class)->craftItem($this->player, 'craft_wooden_sword', $blueprint->uuid);

        $this->provisioningService->ensureTradeStorage($this->player);
        $tempSlot = $this->provisioningService->findFreeTradeTemporarySlot($this->player);
        $item->update(['temporary_slot_uuid' => $tempSlot->uuid]);

        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $targetSlot = $inventory->slots()->whereNull('slot_type')->orderBy('id')->get()
            ->first(fn (Slot $s) => !Item::where('slot_uuid', $s->uuid)->exists() && !Resources::where('slot_uuid', $s->uuid)->exists());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Предмет занят');
        $this->moveService->move($this->player, $item->slot_uuid, $targetSlot->uuid);
    }

    public function test_equip_item_to_weapon_slot_and_unequip(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $blueprint = app(CraftingService::class)->createBlueprint($this->player, 'craft_wooden_sword');
        $item = app(CraftingService::class)->craftItem($this->player, 'craft_wooden_sword', $blueprint->uuid);

        $equipment = $this->player->storages()->where('storage_type', 'equipment')->firstOrFail();
        $weaponSlot = $equipment->slots()->where('slot_type', 'equipment_weapon')->firstOrFail();

        $this->moveService->move($this->player, $item->slot_uuid, $weaponSlot->uuid);
        $item->refresh();
        $this->assertEquals($weaponSlot->uuid, $item->slot_uuid);

        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $emptySlot = $inventory->slots()->whereNull('slot_type')->orderBy('id')->get()
            ->first(fn (Slot $s) => !Item::where('slot_uuid', $s->uuid)->exists() && !Resources::where('slot_uuid', $s->uuid)->exists());
        $this->assertNotNull($emptySlot);

        $this->moveService->move($this->player, $weaponSlot->uuid, $emptySlot->uuid);
        $item->refresh();
        $this->assertEquals($emptySlot->uuid, $item->slot_uuid);
    }
}
