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
        $resultTemplate = ItemTemplate::factory();
        
        return [
            'slug' => fake()->unique()->slug(2),
            'result_template_id' => $resultTemplate,
            'result_quantity' => fake()->numberBetween(1, 10),
        ];
    }
}
