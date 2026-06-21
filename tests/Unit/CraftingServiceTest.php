<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use App\Models\Recipe;
use App\Models\RecipeComponent;
use App\Models\User;
use App\Services\CraftingService;
use Tests\TestCase;

class CraftingServiceTest extends TestCase
{
    private CraftingService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CraftingService::class);
        $this->user = User::factory()->create(['gold' => 1000]);
    }

    public function test_get_recipes_returns_all_recipes(): void
    {
        $resultTemplate = ItemTemplate::factory()->equipment()->create(['name' => 'Деревянный меч']);
        $wood = ItemTemplate::factory()->material()->create(['name' => 'Дерево']);

        $recipe = Recipe::factory()->create([
            'result_template_id' => $resultTemplate->id,
            'result_quantity' => 1,
        ]);

        RecipeComponent::factory()->create([
            'recipe_id' => $recipe->id,
            'template_id' => $wood->id,
            'quantity' => 5,
        ]);

        $recipes = $this->service->getRecipes();

        // Находим наш рецепт по ID
        $foundRecipe = null;
        foreach ($recipes as $r) {
            if ($r['recipe_id'] === $recipe->id) {
                $foundRecipe = $r;
                break;
            }
        }

        $this->assertNotNull($foundRecipe, 'Recipe not found in response');
        $this->assertEquals('Деревянный меч', $foundRecipe['result']['template_name']);
        $this->assertCount(1, $foundRecipe['components']);
        $this->assertEquals('Дерево', $foundRecipe['components'][0]['template_name']);
        $this->assertEquals(5, $foundRecipe['components'][0]['quantity']);
    }

    public function test_craft_creates_item(): void
    {
        $resultTemplate = ItemTemplate::factory()->equipment()->create(['name' => 'Меч']);
        $wood = ItemTemplate::factory()->material()->create(['name' => 'Дерево']);

        ItemInstance::factory()->create([
            'owner_id' => $this->user->id,
            'template_id' => $wood->id,
            'quantity' => 10,
        ]);

        $recipe = Recipe::factory()->create([
            'result_template_id' => $resultTemplate->id,
            'result_quantity' => 1,
        ]);

        RecipeComponent::factory()->create([
            'recipe_id' => $recipe->id,
            'template_id' => $wood->id,
            'quantity' => 5,
        ]);

        $result = $this->service->craft($this->user->id, $recipe->id, 1);

        $this->assertEquals($resultTemplate->id, $result->template_id);
        $this->assertEquals($this->user->id, $result->owner_id);

        $remainingWood = ItemInstance::where('owner_id', $this->user->id)
            ->where('template_id', $wood->id)
            ->sum('quantity');
        $this->assertEquals(5, $remainingWood);

        $this->assertDatabaseHas('item_instances', [
            'owner_id' => $this->user->id,
            'template_id' => $resultTemplate->id,
        ]);
    }

    public function test_craft_fails_if_not_enough_materials(): void
    {
        $resultTemplate = ItemTemplate::factory()->equipment()->create();
        $wood = ItemTemplate::factory()->material()->create();

        ItemInstance::factory()->create([
            'owner_id' => $this->user->id,
            'template_id' => $wood->id,
            'quantity' => 3,
        ]);

        $recipe = Recipe::factory()->create([
            'result_template_id' => $resultTemplate->id,
            'result_quantity' => 1,
        ]);

        RecipeComponent::factory()->create([
            'recipe_id' => $recipe->id,
            'template_id' => $wood->id,
            'quantity' => 5,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно');
        $this->service->craft($this->user->id, $recipe->id, 1);
    }

    public function test_craft_multiple_quantity(): void
    {
        $resultTemplate = ItemTemplate::factory()->equipment()->create();
        $wood = ItemTemplate::factory()->material()->create(['max_stack' => 200]);

        ItemInstance::factory()->create([
            'owner_id' => $this->user->id,
            'template_id' => $wood->id,
            'quantity' => 100,
        ]);

        $recipe = Recipe::factory()->create([
            'result_template_id' => $resultTemplate->id,
            'result_quantity' => 1,
        ]);

        RecipeComponent::factory()->create([
            'recipe_id' => $recipe->id,
            'template_id' => $wood->id,
            'quantity' => 5,
        ]);

        $this->service->craft($this->user->id, $recipe->id, 3);

        $remainingWood = ItemInstance::where('owner_id', $this->user->id)
            ->where('template_id', $wood->id)
            ->sum('quantity');
        $this->assertEquals(85, $remainingWood);

        $swords = ItemInstance::where('owner_id', $this->user->id)
            ->where('template_id', $resultTemplate->id)
            ->sum('quantity');
        $this->assertEquals(3, $swords);
    }

    public function test_disassemble_returns_materials(): void
    {
        $wood = ItemTemplate::factory()->material()->create(['name' => 'Дерево']);

        $swordTemplate = ItemTemplate::factory()->equipment()->create([
            'name' => 'Меч',
            'disassemble_data' => [$wood->id => 2],
        ]);

        $sword = ItemInstance::factory()->create([
            'owner_id' => $this->user->id,
            'template_id' => $swordTemplate->id,
            'quantity' => 1,
        ]);

        $materials = $this->service->disassemble($this->user->id, $sword->id);

        $this->assertCount(1, $materials);
        $this->assertEquals(2, $materials[0]['quantity']);
        $this->assertEquals($wood->id, $materials[0]['template_id']);

        $this->assertDatabaseMissing('item_instances', ['id' => $sword->id]);

        $this->assertDatabaseHas('item_instances', [
            'owner_id' => $this->user->id,
            'template_id' => $wood->id,
            'quantity' => 2,
        ]);
    }

    public function test_disassemble_fails_for_non_disassembleable(): void
    {
        $template = ItemTemplate::factory()->equipment()->create([
            'disassemble_data' => null,
        ]);

        $item = ItemInstance::factory()->create([
            'owner_id' => $this->user->id,
            'template_id' => $template->id,
            'quantity' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('нельзя разобрать');
        $this->service->disassemble($this->user->id, $item->id);
    }
}
