<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\User;
use App\Services\CraftingService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    public function test_craft_resource_wood_to_planks(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 10);
        $result = $this->service->craftResource($this->character, 'craft_wooden_plank');

        $this->assertEquals('wooden_plank', $result['result_template_slug']);
        $this->assertEquals(5, $result['result_quantity']);
        $this->assertEquals(9, $this->inventoryService->getResourceQuantity($this->character, 'wood'));
        $this->assertEquals(5, $this->inventoryService->getResourceQuantity($this->character, 'wooden_plank'));
    }

    public function test_craft_resource_fails_if_not_enough(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->craftResource($this->character, 'craft_wooden_plank');
    }

    public function test_craft_resource_multiple_times(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 10);
        $result = $this->service->craftResource($this->character, 'craft_wooden_plank', 2);

        $this->assertEquals(10, $result['result_quantity']);
        $this->assertEquals(8, $this->inventoryService->getResourceQuantity($this->character, 'wood'));
        $this->assertEquals(10, $this->inventoryService->getResourceQuantity($this->character, 'wooden_plank'));
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
        $this->inventoryService->addResource($this->character, 'wood', 10);
        $blueprint = $this->service->createBlueprint($this->character, 'craft_wooden_sword');

        $item = $this->service->craftItem(
            $this->character,
            'craft_wooden_sword',
            $blueprint->uuid,
            'Мой первый меч'
        );

        $this->assertEquals('item', $item->stage);
        $this->assertEquals('wooden_sword', $item->template_slug); // Теперь правильно!
        $this->assertEquals('Мой первый меч', $item->custom_name);
        $this->assertEquals(['wood' => 5], $item->materials_used);
        $this->assertNotNull($item->stats);
        $this->assertEquals(5, $this->inventoryService->getResourceQuantity($this->character, 'wood'));
    }

    public function test_craft_item_fails_without_blueprint(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 10);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->craftItem($this->character, 'craft_wooden_sword', 'non-existent-uuid');
    }

    public function test_craft_item_fails_without_resources(): void
    {
        $blueprint = $this->service->createBlueprint($this->character, 'craft_wooden_sword');

        $this->expectException(\RuntimeException::class);
        $this->service->craftItem($this->character, 'craft_wooden_sword', $blueprint->uuid);
    }

    public function test_disassemble_item_returns_blueprint_and_resources(): void
    {
        $this->inventoryService->addResource($this->character, 'wood', 10);
        $blueprint = $this->service->createBlueprint($this->character, 'craft_wooden_sword');
        $item = $this->service->craftItem($this->character, 'craft_wooden_sword', $blueprint->uuid);

        $result = $this->service->disassembleItem($this->character, $item->uuid);

        $this->assertEquals('blueprint', $result['item']->stage);
        $this->assertEquals('recipe_wooden_sword', $result['item']->template_slug); // Вернулся в blueprint!
        $this->assertNull($result['item']->custom_name);
        $this->assertNull($result['item']->materials_used);
        $this->assertEquals(['wood' => 2], $result['returned_resources']);
    }

    public function test_blueprint_can_be_recrafted(): void
    {
        // Создаём предмет
        $this->inventoryService->addResource($this->character, 'wood', 20);
        $blueprint = $this->service->createBlueprint($this->character, 'craft_wooden_sword');
        $item = $this->service->craftItem($this->character, 'craft_wooden_sword', $blueprint->uuid);

        // Разбираем
        $this->service->disassembleItem($this->character, $item->uuid);

        // Снова крафтим из того же blueprint (он вернулся в blueprint stage)
        $item2 = $this->service->craftItem($this->character, 'craft_wooden_sword', $item->uuid);

        $this->assertEquals('item', $item2->stage);
        $this->assertEquals('wooden_sword', $item2->template_slug);
    }

    public function test_disassemble_iron_sword_can_trigger_easter_egg(): void
    {
        $this->inventoryService->addResource($this->character, 'wooden_plank', 100);
        $this->inventoryService->addResource($this->character, 'iron_ingot', 100);
        
        $blueprint = $this->service->createBlueprint($this->character, 'craft_iron_sword');
        
        // Пытаемся разобрать много раз, чтобы поймать пасхалку
        $easterEggTriggered = false;
        for ($i = 0; $i < 30; $i++) {
            $item = $this->service->craftItem($this->character, 'craft_iron_sword', $blueprint->uuid);
            $result = $this->service->disassembleItem($this->character, $item->uuid);
            
            if ($result['formula']->description === 'Пасхальный разбор') {
                $easterEggTriggered = true;
                $this->assertEquals(['wooden_plank' => 3, 'iron_ingot' => 4], $result['returned_resources']);
                break;
            }
        }

        // Статистически за 30 попыток при 10% шансе должно сработать
        $this->assertTrue($easterEggTriggered, 'Пасхальная разборка не сработала за 30 попыток');
    }
}
