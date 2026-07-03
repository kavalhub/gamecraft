<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\User;
use App\Services\CraftingService;
use App\Services\InventoryService;
use App\Services\MailService;
use App\Services\StorageQuickMoveService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WorkbenchHelper;
use Tests\TestCase;

class StorageQuickMoveServiceTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    private StorageQuickMoveService $service;

    private InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGameDatabase();

        $user = User::where('email', 'test@example.com')->first();
        $this->character = $user->characters()->where('character_type', 'player')->first();
        $this->service = app(StorageQuickMoveService::class);
        $this->inventoryService = app(InventoryService::class);
    }

    public function test_quick_move_equip_item(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->character);
        $item->refresh();

        $result = $this->service->quickMove($this->character, $item->slot_uuid, 'equipment');

        $this->assertArrayNotHasKey('noop', $result);
        $item->refresh();
        $this->assertNotNull($item->slot_uuid);
        $this->assertNull($item->buffer_slot_uuid);

        $equipment = $this->character->storages()->where('storage_type', 'equipment')->firstOrFail();
        $equipSlot = $equipment->slots()->where('slot_type', 'equipment_weapon')->firstOrFail();
        $this->assertEquals($equipSlot->uuid, $item->slot_uuid);
    }

    public function test_quick_move_inventory_from_equipment(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->character);
        $this->service->quickMove($this->character, $item->slot_uuid, 'equipment');
        $item->refresh();

        $result = $this->service->quickMove($this->character, $item->slot_uuid, 'inventory');

        $this->assertArrayNotHasKey('noop', $result);
        $item->refresh();
        $inventory = $this->character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $this->assertTrue(
            $inventory->slots()->where('uuid', $item->slot_uuid)->exists()
        );
    }

    public function test_quick_move_wood_to_craft_material_slot(): void
    {
        $crafting = app(CraftingService::class);
        $blueprint = $crafting->createBlueprint($this->character, 'craft_wooden_sword');
        WorkbenchHelper::placeOnBlueprintSlot($this->character, $blueprint);

        $this->inventoryService->addResource($this->character, 'wood', 5);
        $wood = \App\Models\Resources::query()
            ->whereIn('slot_uuid', $this->character->storages()->where('storage_type', 'inventory')->firstOrFail()->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->whereNull('buffer_slot_uuid')
            ->firstOrFail();

        $result = $this->service->quickMove($this->character, $wood->slot_uuid, 'craft', 'material');

        $this->assertArrayNotHasKey('noop', $result);
        $wood->refresh();
        $this->assertNotNull($wood->buffer_slot_uuid);
    }

    public function test_quick_move_iron_sword_materials(): void
    {
        $crafting = app(CraftingService::class);
        $this->inventoryService->addResource($this->character, 'wooden_plank', 10);
        $this->inventoryService->addResource($this->character, 'iron_ingot', 10);

        $blueprint = $crafting->createBlueprint($this->character, 'craft_iron_sword');
        WorkbenchHelper::placeOnBlueprintSlot($this->character, $blueprint);

        $inventorySlotUuids = $this->character->storages()->where('storage_type', 'inventory')->firstOrFail()->slots()->pluck('uuid');

        $plank = \App\Models\Resources::query()
            ->whereIn('slot_uuid', $inventorySlotUuids)
            ->where('template_slug', 'wooden_plank')
            ->whereNull('buffer_slot_uuid')
            ->firstOrFail();

        $ingot = \App\Models\Resources::query()
            ->whereIn('slot_uuid', $inventorySlotUuids)
            ->where('template_slug', 'iron_ingot')
            ->whereNull('buffer_slot_uuid')
            ->firstOrFail();

        $plankResult = $this->service->quickMove($this->character, $plank->slot_uuid, 'craft', 'material');
        $ingotResult = $this->service->quickMove($this->character, $ingot->slot_uuid, 'craft', 'material');

        $this->assertArrayNotHasKey('noop', $plankResult);
        $this->assertArrayNotHasKey('noop', $ingotResult);

        $plank->refresh();
        $ingot->refresh();
        $this->assertNotNull($plank->buffer_slot_uuid);
        $this->assertNotNull($ingot->buffer_slot_uuid);
    }

    public function test_quick_move_station_return(): void
    {
        $crafting = app(CraftingService::class);
        $blueprint = $crafting->createBlueprint($this->character, 'craft_wooden_sword');
        WorkbenchHelper::placeOnBlueprintSlot($this->character, $blueprint);
        $blueprint->refresh();

        $tempUuid = $blueprint->buffer_slot_uuid;
        $this->assertNotNull($tempUuid);

        $result = $this->service->quickMove($this->character, $tempUuid, 'station_return');

        $this->assertArrayNotHasKey('noop', $result);
        $blueprint->refresh();
        $this->assertNull($blueprint->buffer_slot_uuid);
    }

    public function test_quick_move_inventory_to_bank_and_back(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 3);
        $wood = \App\Models\Resources::query()
            ->whereIn('slot_uuid', $this->character->storages()->where('storage_type', 'inventory')->firstOrFail()->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->whereNull('buffer_slot_uuid')
            ->firstOrFail();

        $toBank = $this->service->quickMove($this->character, $wood->slot_uuid, 'bank');
        $this->assertArrayNotHasKey('noop', $toBank);

        $wood->refresh();
        $bank = $this->character->storages()->where('storage_type', 'bank')->firstOrFail();
        $this->assertTrue($bank->slots()->where('uuid', $wood->slot_uuid)->exists());

        $toInventory = $this->service->quickMove($this->character, $wood->slot_uuid, 'inventory');
        $this->assertArrayNotHasKey('noop', $toInventory);

        $wood->refresh();
        $inventory = $this->character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $this->assertTrue($inventory->slots()->where('uuid', $wood->slot_uuid)->exists());
    }

    public function test_post_outbox_quick_move_stacks_partial_resource(): void
    {
        $outbox = app(MailService::class)->ensureOutboxStorage($this->character);
        $slots = $outbox->slots()->whereNull('slot_type')->orderBy('id')->get();

        \App\Models\Resources::create([
            'slot_uuid' => $slots[0]->uuid,
            'recipe_slug' => 'wood',
            'template_slug' => 'wood',
            'slot_type' => 'material',
            'max_stack' => 20,
            'quantity' => 7,
        ]);

        $this->inventoryService->addResource($this->character, 'wood', 20);
        $wood = \App\Models\Resources::query()
            ->whereIn('slot_uuid', $this->character->storages()->where('storage_type', 'inventory')->firstOrFail()->slots()->pluck('uuid'))
            ->where('template_slug', 'wood')
            ->whereNull('buffer_slot_uuid')
            ->firstOrFail();

        $result = $this->service->quickMove($this->character, $wood->slot_uuid, 'post_outbox');

        $this->assertArrayNotHasKey('noop', $result);
        $this->assertEquals(20, \App\Models\Resources::where('slot_uuid', $slots[0]->uuid)->value('quantity'));
        $this->assertEquals(7, \App\Models\Resources::where('slot_uuid', $slots[1]->uuid)->value('quantity'));
        $this->assertFalse(\App\Models\Resources::where('slot_uuid', $wood->slot_uuid)->exists());
    }
}
