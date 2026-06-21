<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ItemTemplate;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word() . ' recipe',
            'result_template_id' => ItemTemplate::factory()->equipment(),
            'result_quantity' => 1,
        ];
    }
}
