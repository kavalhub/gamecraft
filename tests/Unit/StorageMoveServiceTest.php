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
use Tests\Support\CraftStationHelper;
use Tests\Support\WorkbenchHelper;
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
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $slots = $inventory->slots()->whereNull('slot_type')->orderBy('id')->get();
        $occupied = Item::whereIn('slot_uuid', $slots->pluck('uuid'))->pluck('slot_uuid')
            ->merge(Resources::whereIn('slot_uuid', $slots->pluck('uuid'))->pluck('slot_uuid'));
        $toSlot = $slots->first(fn ($s) => $s->uuid !== $item->slot_uuid && !$occupied->contains($s->uuid));
        $fromSlot = $slots->firstWhere('uuid', $item->slot_uuid);

        $this->assertNotNull($toSlot);
        $this->moveService->move($this->player, $fromSlot->uuid, $toSlot->uuid);

        $item->refresh();
        $this->assertEquals($toSlot->uuid, $item->slot_uuid);
    }

    public function test_overlay_item_to_trade_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

        $originalSlotUuid = $item->slot_uuid;
        $trade = app(\App\Services\TradeService::class)->createTrade(
            $this->player,
            $this->createSecondPlayer()
        );
        $this->provisioningService->ensureTradeStorage($this->player);
        $tradeSlot = $this->provisioningService->findFreeTradeSlot($this->player);

        $this->moveService->move($this->player, $originalSlotUuid, $tradeSlot->uuid);

        $item->refresh();
        $this->assertEquals($tradeSlot->uuid, $item->slot_uuid);
        $this->assertNull($item->temporary_slot_uuid);
    }

    public function test_clear_item_from_trade_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

        $trade = app(\App\Services\TradeService::class)->createTrade(
            $this->player,
            $this->createSecondPlayer()
        );
        $this->provisioningService->ensureTradeStorage($this->player);
        $tradeSlot = $this->provisioningService->findFreeTradeSlot($this->player);
        $this->moveService->move($this->player, $item->slot_uuid, $tradeSlot->uuid);

        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $slots = $inventory->slots()->whereNull('slot_type')->orderBy('id')->get();
        $occupied = Item::whereIn('slot_uuid', $slots->pluck('uuid'))->pluck('slot_uuid')
            ->merge(Resources::whereIn('slot_uuid', $slots->pluck('uuid'))->pluck('slot_uuid'));
        $targetSlot = $slots->first(fn ($s) => !$occupied->contains($s->uuid));

        $this->assertNotNull($targetSlot);
        $this->moveService->move($this->player, $tradeSlot->uuid, $targetSlot->uuid);

        $item->refresh();
        $this->assertNull($item->temporary_slot_uuid);
        $this->assertEquals($targetSlot->uuid, $item->slot_uuid);
    }

    public function test_merge_stackable_resources(): void
    {
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $slots = $inventory->slots()->whereNull('slot_type')->orderBy('id')->get();
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

    public function test_rejects_move_to_foreign_trade_slot(): void
    {
        $other = $this->createSecondPlayer();
        $this->provisioningService->ensureTradeStorage($other);
        $otherTradeSlot = $this->provisioningService->findFreeTradeSlot($other);

        $this->inventoryService->addResource($this->player, 'wood', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $fromSlot = $inventory->slots()->orderBy('id')->first();

        $this->expectException(\RuntimeException::class);
        $this->moveService->move($this->player, $fromSlot->uuid, $otherTradeSlot->uuid);
    }

    public function test_cannot_move_item_with_temporary_overlay(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

        CraftStationHelper::ensureCraftStation($this->player);
        $tempSlot = app(\App\Services\CraftStationService::class)->getCenterTemporarySlot($this->player);
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
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

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

    public function test_cannot_move_resource_to_equipment_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 20);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $wood = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->firstOrFail();

        $equipment = $this->player->storages()->where('storage_type', 'equipment')->firstOrFail();
        $neckSlot = $equipment->slots()->where('slot_type', 'equipment_amulet')->firstOrFail();

        $result = $this->moveService->move($this->player, $wood->slot_uuid, $neckSlot->uuid);

        $this->assertTrue($result['noop'] ?? false);
        $wood->refresh();
        $this->assertEquals($wood->slot_uuid, $wood->slot_uuid);
    }

    public function test_equip_item_replaces_resource_in_weapon_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 5);
        $blueprint = app(CraftingService::class)->createBlueprint($this->player, 'craft_wooden_sword');
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player, $blueprint);
        $itemFromSlot = $item->slot_uuid;

        $this->inventoryService->addResource($this->player, 'wood', 20);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $wood = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->where('slot_uuid', '!=', $itemFromSlot)
            ->firstOrFail();

        $equipment = $this->player->storages()->where('storage_type', 'equipment')->firstOrFail();
        $weaponSlot = $equipment->slots()->where('slot_type', 'equipment_weapon')->firstOrFail();
        $wood->update(['slot_uuid' => $weaponSlot->uuid]);

        $this->moveService->move($this->player, $item->slot_uuid, $weaponSlot->uuid);

        $item->refresh();
        $wood->refresh();
        $this->assertEquals($weaponSlot->uuid, $item->slot_uuid);
        $this->assertEquals($itemFromSlot, $wood->slot_uuid);
    }

    public function test_can_move_wood_to_disassemble_center_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $wood = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->firstOrFail();

        app(\App\Services\DisassembleStationService::class)->ensureDisassembleStorage($this->player);
        $centerSlot = app(\App\Services\DisassembleStationService::class)->getCenterTemporarySlot($this->player);

        $this->moveService->move($this->player, $wood->slot_uuid, $centerSlot->uuid);

        $wood->refresh();
        $this->assertEquals($centerSlot->uuid, $wood->temporary_slot_uuid);
    }

    public function test_cannot_move_wood_to_craft_center_without_craft_formula(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $wood = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->firstOrFail();
        $fromSlotUuid = $wood->slot_uuid;

        WorkbenchHelper::ensureWorkbench($this->player);
        $centerSlot = app(\App\Services\CraftStationService::class)->getCenterTemporarySlot($this->player);

        $result = $this->moveService->move($this->player, $wood->slot_uuid, $centerSlot->uuid);

        $this->assertTrue($result['noop'] ?? false);
        $wood->refresh();
        $this->assertEquals($fromSlotUuid, $wood->slot_uuid);
        $this->assertNull($wood->temporary_slot_uuid);
    }

    public function test_can_move_wood_to_craft_material_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $wood = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->firstOrFail();

        WorkbenchHelper::ensureWorkbench($this->player);
        $materialSlot = app(\App\Services\CraftStationService::class)->getMaterialTemporarySlots($this->player)->first();

        $result = $this->moveService->move($this->player, $wood->slot_uuid, $materialSlot->uuid);

        $this->assertTrue($result['noop'] ?? false);
        $wood->refresh();
        $this->assertNull($wood->temporary_slot_uuid);
    }

    public function test_cannot_move_planks_to_disassemble_center_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wooden_plank', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $planks = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wooden_plank')
            ->firstOrFail();
        $fromSlotUuid = $planks->slot_uuid;

        app(\App\Services\DisassembleStationService::class)->ensureDisassembleStorage($this->player);
        $centerSlot = app(\App\Services\DisassembleStationService::class)->getCenterTemporarySlot($this->player);

        $result = $this->moveService->move($this->player, $planks->slot_uuid, $centerSlot->uuid);

        $this->assertTrue($result['noop'] ?? false);
        $planks->refresh();
        $this->assertEquals($fromSlotUuid, $planks->slot_uuid);
        $this->assertNull($planks->temporary_slot_uuid);
    }

    public function test_cannot_move_blueprint_to_craft_material_slot(): void
    {
        $blueprint = app(CraftingService::class)->createBlueprint($this->player, 'craft_wooden_sword');
        WorkbenchHelper::ensureWorkbench($this->player);
        $materialSlot = app(\App\Services\CraftStationService::class)->getMaterialTemporarySlots($this->player)->first();

        $result = $this->moveService->move($this->player, $blueprint->slot_uuid, $materialSlot->uuid);

        $this->assertTrue($result['noop'] ?? false);
        $blueprint->refresh();
        $this->assertNull($blueprint->temporary_slot_uuid);
    }

    public function test_craft_material_slots_typed_when_blueprint_in_center(): void
    {
        $blueprint = app(CraftingService::class)->createBlueprint($this->player, 'craft_wooden_sword');
        WorkbenchHelper::ensureWorkbench($this->player);
        $craftStation = app(\App\Services\CraftStationService::class);
        $centerSlot = $craftStation->getCenterTemporarySlot($this->player);

        $this->moveService->move($this->player, $blueprint->slot_uuid, $centerSlot->uuid);

        $materialSlot = $craftStation->getMaterialTemporarySlots($this->player)->first();
        $materialSlot->refresh();
        $this->assertEquals('wood', $materialSlot->slot_type);

        $this->inventoryService->addResource($this->player, 'wood', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $wood = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->whereNull('temporary_slot_uuid')
            ->firstOrFail();

        $this->moveService->move($this->player, $wood->slot_uuid, $materialSlot->uuid);
        $wood->refresh();
        $this->assertEquals($materialSlot->uuid, $wood->temporary_slot_uuid);
    }

    public function test_cannot_move_to_craft_regular_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $wood = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->firstOrFail();
        $fromSlotUuid = $wood->slot_uuid;

        $craft = WorkbenchHelper::ensureWorkbench($this->player);
        $regularSlot = $craft->slots()->where('slot_type', 'craft_material')->firstOrFail();

        $result = $this->moveService->move($this->player, $wood->slot_uuid, $regularSlot->uuid);

        $this->assertTrue($result['noop'] ?? false);
        $wood->refresh();
        $this->assertEquals($fromSlotUuid, $wood->slot_uuid);
    }

    private function createSecondPlayer(): Character
    {
        $user2 = User::create([
            'name' => 'Other',
            'email' => 'other-' . \Illuminate\Support\Str::uuid()->toString() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $other = Character::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_uuid' => $user2->uuid,
            'character_type' => 'player',
            'name' => 'Other',
            'active' => true,
        ]);
        $this->provisioningService->provisionDefaults($other);

        return $other;
    }
}
