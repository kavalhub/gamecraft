<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Resource;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;
    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        
        $this->service = app(InventoryService::class);
        $user = User::where('email', 'test@example.com')->first();
        $this->character = $user->characters()->where('character_type', 'player')->first();
    }

    public function test_add_resource_creates_new_resource(): void
    {
        $resource = $this->service->addResource($this->character, 'wood', 10);

        $this->assertNotNull($resource);
        $this->assertEquals(10, $resource->quantity);
        $this->assertEquals('wood', $resource->template_slug);
    }

    public function test_add_resource_stacks_to_existing(): void
    {
        $this->service->addResource($this->character, 'wood', 10);
        $resource = $this->service->addResource($this->character, 'wood', 5);

        $this->assertEquals(15, $resource->quantity);
    }

    public function test_remove_resource_decrements_quantity(): void
    {
        $this->service->addResource($this->character, 'wood', 10);
        $this->service->removeResource($this->character, 'wood', 3);

        $quantity = $this->service->getResourceQuantity($this->character, 'wood');
        $this->assertEquals(7, $quantity);
    }

    public function test_remove_resource_fails_if_not_enough(): void
    {
        $this->service->addResource($this->character, 'wood', 5);

        $this->expectException(\RuntimeException::class);
        $this->service->removeResource($this->character, 'wood', 10);
    }

    public function test_get_resource_quantity(): void
    {
        $this->service->addResource($this->character, 'wood', 10);
        $this->service->addResource($this->character, 'iron_ore', 5);

        $this->assertEquals(10, $this->service->getResourceQuantity($this->character, 'wood'));
        $this->assertEquals(5, $this->service->getResourceQuantity($this->character, 'iron_ore'));
        // Стартовое золото тоже учитывается
        $this->assertEquals(1000, $this->service->getResourceQuantity($this->character, 'gold'));
    }

    public function test_add_item_creates_new_item(): void
    {
        // recipe_slug теперь nullable - можно создавать предметы без рецепта
        $item = $this->service->addItem(
            $this->character,
            'wooden_sword',
            'item',
            'Мой меч',
            null, // recipe_slug nullable
            ['wood' => 5]
        );

        $this->assertNotNull($item);
        $this->assertEquals('wooden_sword', $item->template_slug);
        $this->assertEquals('item', $item->stage);
        $this->assertEquals('Мой меч', $item->custom_name);
    }

    public function test_add_item_with_recipe(): void
    {
        $item = $this->service->addItem(
            $this->character,
            'wooden_sword',
            'item',
            null,
            'craft_wooden_sword',
            ['wood' => 5]
        );

        $this->assertEquals('craft_wooden_sword', $item->recipe_slug);
    }

    public function test_get_character_items(): void
    {
        $this->service->addItem($this->character, 'wooden_sword', 'item');
        $this->service->addItem($this->character, 'iron_sword', 'item');

        $items = $this->service->getCharacterItems($this->character);
        $this->assertCount(2, $items);
    }

    public function test_get_character_resources(): void
    {
        // Стартовое золото уже есть (1000)
        $this->service->addResource($this->character, 'wood', 10);
        $this->service->addResource($this->character, 'iron_ore', 5);

        $resources = $this->service->getCharacterResources($this->character);
        // 3: золото (стартовое) + wood + iron_ore
        $this->assertCount(3, $resources);
        
        // Проверяем конкретные ресурсы
        $this->assertEquals(10, $resources->where('template_slug', 'wood')->first()->quantity);
        $this->assertEquals(5, $resources->where('template_slug', 'iron_ore')->first()->quantity);
        $this->assertEquals(1000, $resources->where('template_slug', 'gold')->first()->quantity);
    }

    public function test_get_character_items_by_storage_type(): void
    {
        $this->service->addItem($this->character, 'wooden_sword', 'item', storageType: 'inventory');
        
        $inventoryItems = $this->service->getCharacterItems($this->character, 'inventory');
        $this->assertCount(1, $inventoryItems);
        
        $equipmentItems = $this->service->getCharacterItems($this->character, 'equipment');
        $this->assertCount(0, $equipmentItems);
    }
}
