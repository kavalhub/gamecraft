<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ItemTemplate;
use App\Models\Recipe;
use App\Models\RecipeComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeComponentFactory extends Factory
{
    protected $model = RecipeComponent::class;

    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'template_id' => ItemTemplate::factory()->material(),
            'quantity' => 1,
        ];
    }
}
