<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\User;
use App\Services\CraftStationService;
use App\Services\DisassembleStationService;
use App\Services\CraftingService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CraftStationHelper;
use Tests\Support\DisassembleStationHelper;
use Tests\Support\WorkbenchHelper;
use Tests\TestCase;

class CraftingServiceTest extends TestCase
{
    use RefreshDatabase;

    private CraftingService $service;
    private InventoryService $inventoryService;
    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->service = app(CraftingService::class);
        $this->inventoryService = app(InventoryService::class);
        $user = User::where('email', 'test@example.com')->first();
        $this->character = $user->characters()->where('character_type', 'player')->first();
    }

    public function test_get_available_recipes(): void
    {
        $recipes = $this->service->getAvailableRecipes($this->character);
        $this->assertGreaterThan(0, $recipes->count());

        $wood = $recipes->firstWhere('slug', 'wood');
        $this->assertNotNull($wood);
        $this->assertEquals(['wooden_plank' => 5], $wood['disassemble_formula']);
        $this->assertEquals('saw', $wood['disassemble_action']['slug']);
        $this->assertEquals('Распилить', $wood['disassemble_action']['label']);

        $ironOre = $recipes->firstWhere('slug', 'iron_ore');
        $this->assertNotNull($ironOre);
        $this->assertEquals('iron_ingot', $ironOre['result_template_slug']);
        $this->assertEquals(2, $ironOre['result_quantity']);
        $this->assertEquals('smelt', $ironOre['craft_action']['slug']);
    }

    public function test_disassemble_resource_wood_to_planks(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 10);
        $wood = DisassembleStationHelper::placeResource($this->character, 'wood', 1);

        $result = $this->service->disassembleResource($this->character, 'wood');

        $this->assertEquals(['wooden_plank' => 5], $result['returned_resources']);
        $wood->refresh();
        $this->assertEquals(9, $wood->quantity);
        $this->assertEquals(0, $this->inventoryService->getResourceQuantity($this->character, 'wooden_plank'));
        $this->assertEquals(5, $this->disassembleOutputQuantity('wooden_plank'));
    }

    public function test_disassemble_resource_wood_fails_if_not_on_station(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->disassembleResource($this->character, 'wood');
    }

    public function test_disassemble_resource_wood_multiple_times(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 10);
        $wood = DisassembleStationHelper::placeResource($this->character, 'wood', 2);

        $result = $this->service->disassembleResource($this->character, 'wood', 2);

        $this->assertEquals(['wooden_plank' => 10], $result['returned_resources']);
        $wood->refresh();
        $this->assertEquals(8, $wood->quantity);
        $this->assertEquals(0, $this->inventoryService->getResourceQuantity($this->character, 'wooden_plank'));
        $this->assertEquals(10, $this->disassembleOutputQuantity('wooden_plank'));
    }

    public function test_disassemble_resource_wood_stacks_output_across_separate_runs(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 1);
        DisassembleStationHelper::placeResource($this->character, 'wood', 1);
        $this->service->disassembleResource($this->character, 'wood');

        $this->inventoryService->addResource($this->character, 'wood', 1);
        DisassembleStationHelper::placeResource($this->character, 'wood', 1);
        $this->service->disassembleResource($this->character, 'wood');

        $outputSlots = app(DisassembleStationService::class)->getOutputTemporarySlots($this->character);
        $stacks = \App\Models\Resources::whereIn('temporary_slot_uuid', $outputSlots->pluck('uuid'))
            ->where('template_slug', 'wooden_plank')
            ->get();

        $this->assertCount(1, $stacks);
        $this->assertEquals(10, $stacks->first()->quantity);
        $this->assertEquals(10, $this->disassembleOutputQuantity('wooden_plank'));
    }

    public function test_disassemble_station_return_all_to_inventory(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 2);
        DisassembleStationHelper::placeResource($this->character, 'wood', 1);
        $this->service->disassembleResource($this->character, 'wood');

        $this->assertEquals(5, $this->disassembleOutputQuantity('wooden_plank'));
        $this->assertEquals(0, $this->inventoryService->getResourceQuantity($this->character, 'wooden_plank'));

        app(DisassembleStationService::class)->returnAllToInventory($this->character);

        $this->assertEquals(0, $this->disassembleOutputQuantity('wooden_plank'));
        $this->assertEquals(5, $this->inventoryService->getResourceQuantity($this->character, 'wooden_plank'));
    }

    public function test_craft_resource_iron_ore_to_ingots(): void
    {
        $this->inventoryService->addResource($this->character, 'iron_ore', 20);
        WorkbenchHelper::ensureWorkbench($this->character);

        $inventory = $this->character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $ore = \App\Models\Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', 'iron_ore')
            ->whereNull('temporary_slot_uuid')
            ->firstOrFail();
        $center = app(CraftStationService::class)->getCenterTemporarySlot($this->character);
        app(\App\Services\StorageMoveService::class)->move($this->character, $ore->slot_uuid, $center->uuid, 20);

        $result = $this->service->craftResource($this->character, 'iron_ore');

        $this->assertEquals('iron_ingot', $result['result_template_slug']);
        $this->assertEquals(2, $result['result_quantity']);
        $this->assertNull(\App\Models\Resources::where('temporary_slot_uuid', $center->uuid)->first());
        $this->assertEquals(10, $this->inventoryService->getResourceQuantity($this->character, 'iron_ore'));
        $this->assertEquals(2, $this->inventoryService->getResourceQuantity($this->character, 'iron_ingot'));
    }

    public function test_create_blueprint(): void
    {
        $blueprint = $this->service->createBlueprint($this->character, 'craft_wooden_sword');

        $this->assertEquals('recipe_wooden_sword', $blueprint->template_slug);
        $this->assertEquals('blueprint', $blueprint->stage);
        $this->assertEquals('craft_wooden_sword', $blueprint->recipe_slug);
    }

    public function test_craft_item_from_blueprint(): void
    {
        $blueprint = $this->service->createBlueprint($this->character, 'craft_wooden_sword');
        WorkbenchHelper::prepareCraft($this->character, $blueprint, ['wood' => 5]);

        $item = $this->service->craftItem(
            $this->character,
            'craft_wooden_sword',
            $blueprint->uuid,
            'Мой первый меч'
        );

        $this->assertEquals('item', $item->stage);
        $this->assertEquals('wooden_sword', $item->template_slug);
        $this->assertEquals('Мой первый меч', $item->custom_name);
        $this->assertNull($item->temporary_slot_uuid);
        $this->assertEquals(['wood' => 5], \App\Support\ItemMaterialsUsed::resources($item->materials_used));
        $this->assertEquals($this->character->uuid, $item->materials_used['crafter']['character_uuid'] ?? null);
        $this->assertNotNull($item->stats);
        $this->assertEquals(0, $this->inventoryService->getResourceQuantity($this->character, 'wood'));
    }

    public function test_craft_item_fails_without_blueprint(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->craftItem($this->character, 'craft_wooden_sword', 'non-existent-uuid');
    }

    public function test_craft_item_fails_without_resources(): void
    {
        $blueprint = $this->service->createBlueprint($this->character, 'craft_wooden_sword');
        WorkbenchHelper::placeOnBlueprintSlot($this->character, $blueprint);

        $this->expectException(\RuntimeException::class);
        $this->service->craftItem($this->character, 'craft_wooden_sword', $blueprint->uuid);
    }

    public function test_disassemble_item_returns_blueprint_and_resources(): void
    {
        $blueprint = $this->service->createBlueprint($this->character, 'craft_wooden_sword');
        WorkbenchHelper::prepareCraft($this->character, $blueprint, ['wood' => 5]);
        $item = $this->service->craftItem($this->character, 'craft_wooden_sword', $blueprint->uuid);

        $disSlot = app(DisassembleStationService::class)->getCenterTemporarySlot($this->character);
        app(\App\Services\StorageMoveService::class)->move($this->character, $item->slot_uuid, $disSlot->uuid);
        $item->refresh();

        $result = $this->service->disassembleItem($this->character, $item->uuid);

        $this->assertEquals('blueprint', $result['item']->stage);
        $this->assertEquals('recipe_wooden_sword', $result['item']->template_slug);
        $this->assertNull($result['item']->custom_name);
        $this->assertNull($result['item']->materials_used);
        $this->assertEquals(['wood' => 2], $result['returned_resources']);
        $this->assertEquals(2, $this->disassembleOutputQuantity('wood'));
    }

    public function test_blueprint_can_be_recrafted(): void
    {
        $blueprint = $this->service->createBlueprint($this->character, 'craft_wooden_sword');
        WorkbenchHelper::prepareCraft($this->character, $blueprint, ['wood' => 5]);
        $item = $this->service->craftItem($this->character, 'craft_wooden_sword', $blueprint->uuid);

        $disSlot = app(DisassembleStationService::class)->getCenterTemporarySlot($this->character);
        app(\App\Services\StorageMoveService::class)->move($this->character, $item->slot_uuid, $disSlot->uuid);
        $item->refresh();

        $this->service->disassembleItem($this->character, $item->uuid);
        $item->refresh();

        $craftCenter = app(CraftStationService::class)->getCenterTemporarySlot($this->character);
        $disCenter = app(DisassembleStationService::class)->getCenterTemporarySlot($this->character);
        app(\App\Services\StorageMoveService::class)->move($this->character, $disCenter->uuid, $craftCenter->uuid);
        $item->refresh();

        $this->inventoryService->addResource($this->character, 'wood', 5);
        WorkbenchHelper::placeMaterials($this->character, ['wood' => 5]);
        $item2 = $this->service->craftItem($this->character, 'craft_wooden_sword', $item->uuid);

        $this->assertEquals('item', $item2->stage);
        $this->assertEquals('wooden_sword', $item2->template_slug);
    }

    public function test_disassemble_iron_sword_can_trigger_easter_egg(): void
    {
        $this->inventoryService->addResource($this->character, 'wooden_plank', 100);
        $this->inventoryService->addResource($this->character, 'iron_ingot', 100);

        $blueprint = $this->service->createBlueprint($this->character, 'craft_iron_sword');

        $easterEggTriggered = false;
        for ($i = 0; $i < 30; $i++) {
            app(CraftStationService::class)->clearOverlays($this->character);
            app(DisassembleStationService::class)->clearOverlays($this->character);
            WorkbenchHelper::placeOnBlueprintSlot($this->character, $blueprint);
            WorkbenchHelper::moveMaterialsToWorkbench($this->character, [
                'wooden_plank' => 3,
                'iron_ingot' => 2,
            ]);
            $item = $this->service->craftItem($this->character, 'craft_iron_sword', $blueprint->uuid);
            $disSlot = app(DisassembleStationService::class)->getCenterTemporarySlot($this->character);
            app(\App\Services\StorageMoveService::class)->move($this->character, $item->slot_uuid, $disSlot->uuid);
            $item->refresh();
            $result = $this->service->disassembleItem($this->character, $item->uuid);

            if ($result['formula']->description === 'Пасхальный разбор') {
                $easterEggTriggered = true;
                $this->assertEquals(['wooden_plank' => 3, 'iron_ingot' => 4], $result['returned_resources']);
                break;
            }
        }

        $this->assertTrue($easterEggTriggered, 'Пасхальная разборка не сработала за 30 попыток');
    }

    public function test_craft_resource_rejects_empty_formula(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('не задана формула преобразования');

        $this->service->craftResource($this->character, 'gold');
    }

    public function test_craft_item_clears_station_and_returns_leftovers(): void
    {
        $blueprint = $this->service->createBlueprint($this->character, 'craft_wooden_sword');
        WorkbenchHelper::placeOnBlueprintSlot($this->character, $blueprint);
        $this->inventoryService->addResource($this->character, 'wood', 10);
        CraftStationHelper::moveMaterialsToCraftStation($this->character, ['wood' => 10]);

        $this->service->craftItem($this->character, 'craft_wooden_sword', $blueprint->uuid);

        $this->assertEquals(5, $this->inventoryService->getResourceQuantity($this->character, 'wood'));
        $this->assertNull(app(CraftStationService::class)->getCenterItem($this->character));
        $this->assertSame([], app(CraftStationService::class)->getMaterialQuantities($this->character));

        $materialSlots = app(CraftStationService::class)->getMaterialTemporarySlots($this->character);
        foreach ($materialSlots as $slot) {
            $this->assertEquals(CraftStationService::DISABLED_SLOT_TYPE, $slot->fresh()->slot_type);
        }
    }

    private function disassembleOutputQuantity(string $templateSlug): int
    {
        $outputSlots = app(DisassembleStationService::class)->getOutputTemporarySlots($this->character);

        return (int) \App\Models\Resources::whereIn('temporary_slot_uuid', $outputSlots->pluck('uuid'))
            ->where('template_slug', $templateSlug)
            ->sum('quantity');
    }
}
