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

    public function test_overlay_item_to_trade_temporary_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

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
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

        $this->provisioningService->ensureTradeStorage($this->player);
        $tempSlot = $this->provisioningService->findFreeTradeTemporarySlot($this->player);
        $item->update(['temporary_slot_uuid' => $tempSlot->uuid]);

        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $slots = $inventory->slots()->whereNull('slot_type')->orderBy('id')->get();
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
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

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

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('В слот экипировки можно класть только предметы');
        $this->moveService->move($this->player, $wood->slot_uuid, $neckSlot->uuid);
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

    public function test_cannot_move_resource_to_craft_center_without_craft_formula(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $wood = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->firstOrFail();

        WorkbenchHelper::ensureWorkbench($this->player);
        $centerSlot = app(\App\Services\DisassembleStationService::class)->getCenterTemporarySlot($this->player);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('У ресурса нет формулы разбора');
        $this->moveService->move($this->player, $wood->slot_uuid, $centerSlot->uuid);
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

        $this->moveService->move($this->player, $wood->slot_uuid, $materialSlot->uuid);

        $wood->refresh();
        $this->assertEquals($materialSlot->uuid, $wood->temporary_slot_uuid);
    }

    public function test_can_move_planks_to_disassemble_center_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wooden_plank', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $planks = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wooden_plank')
            ->firstOrFail();

        app(\App\Services\DisassembleStationService::class)->ensureDisassembleStorage($this->player);
        $centerSlot = app(\App\Services\DisassembleStationService::class)->getCenterTemporarySlot($this->player);

        $this->moveService->move($this->player, $planks->slot_uuid, $centerSlot->uuid);

        $planks->refresh();
        $this->assertEquals($centerSlot->uuid, $planks->temporary_slot_uuid);
    }

    public function test_cannot_move_blueprint_to_craft_material_slot(): void
    {
        $blueprint = app(CraftingService::class)->createBlueprint($this->player, 'craft_wooden_sword');
        WorkbenchHelper::ensureWorkbench($this->player);
        $materialSlot = app(\App\Services\CraftStationService::class)->getMaterialTemporarySlots($this->player)->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Предмет можно положить только в центральный слот станции создания');
        $this->moveService->move($this->player, $blueprint->slot_uuid, $materialSlot->uuid);
    }

    public function test_cannot_move_to_craft_regular_slot(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 5);
        $inventory = $this->player->storages()->where('storage_type', 'inventory')->firstOrFail();
        $wood = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->firstOrFail();

        $craft = WorkbenchHelper::ensureWorkbench($this->player);
        $regularSlot = $craft->slots()->where('slot_type', 'craft_material')->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('На станцию кладите предметы через overlay-слоты');
        $this->moveService->move($this->player, $wood->slot_uuid, $regularSlot->uuid);
    }
}
