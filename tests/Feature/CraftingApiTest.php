<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use App\Models\Recipe;
use App\Models\RecipeComponent;
use App\Models\User;
use Tests\TestCase;

class CraftingApiTest extends TestCase
{
    public function test_get_recipes_returns_list(): void
    {
        $resultTemplate = ItemTemplate::factory()->equipment()->create(['name' => 'Меч']);
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

        $response = $this->getJson('/api/recipes');

        $response->assertOk()
            ->assertJsonStructure([
                'recipes' => [
                    '*' => [
                        'recipe_id',
                        'result_template_id',
                        'result_quantity',
                        'result' => ['template_id', 'template_name', 'template_type'],
                        'components' => [
                            '*' => ['template_id', 'template_name', 'quantity'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_craft_api(): void
    {
        $user = User::factory()->create(['gold' => 1000]);
        $resultTemplate = ItemTemplate::factory()->equipment()->create(['name' => 'Меч']);
        $wood = ItemTemplate::factory()->material()->create(['name' => 'Дерево']);

        ItemInstance::factory()->create([
            'owner_id' => $user->id,
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

        $response = $this->postJson('/api/craft', [
            'recipe_id' => $recipe->id,
            'user_id' => $user->id,
            'quantity' => 1,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'item' => ['id', 'template_id', 'name', 'type', 'quantity'],
            ])
            ->assertJson([
                'item' => [
                    'name' => 'Меч',
                    'quantity' => 1,
                ],
            ]);
    }

    public function test_craft_api_fails_without_materials(): void
    {
        $user = User::factory()->create();
        $resultTemplate = ItemTemplate::factory()->equipment()->create();
        $wood = ItemTemplate::factory()->material()->create();

        $recipe = Recipe::factory()->create([
            'result_template_id' => $resultTemplate->id,
            'result_quantity' => 1,
        ]);

        RecipeComponent::factory()->create([
            'recipe_id' => $recipe->id,
            'template_id' => $wood->id,
            'quantity' => 5,
        ]);

        $response = $this->postJson('/api/craft', [
            'recipe_id' => $recipe->id,
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    public function test_disassemble_api(): void
    {
        $user = User::factory()->create();
        $wood = ItemTemplate::factory()->material()->create([
            'slug' => 'wood',
            'name' => 'Дерево'
        ]);
        
        $swordTemplate = ItemTemplate::factory()->equipment()->create([
            'name' => 'Меч',
            'disassemble_data' => ['wood' => 2], // Теперь slug!
        ]);

        $sword = ItemInstance::factory()->create([
            'owner_id' => $user->id,
            'template_id' => $swordTemplate->id,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/disassemble', [
            'instance_id' => $sword->id,
            'user_id' => $user->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'materials']);
    }
}
