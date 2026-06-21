<?php

namespace Database\Factories;

use App\Models\ItemTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemTemplateFactory extends Factory
{
    protected $model = ItemTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'type' => 'material',
            'icon' => '📦',
            'is_stackable' => true,
            'max_stack' => 999,
            'description' => fake()->sentence(),
        ];
    }

    public function material(): static
    {
        return $this->state(['type' => 'material', 'is_stackable' => true, 'max_stack' => 200]);
    }

    public function equipment(): static
    {
        return $this->state(['type' => 'equipment', 'is_stackable' => false, 'max_stack' => 1]);
    }

    public function recipe(): static
    {
        return $this->state(['type' => 'recipe', 'is_stackable' => false, 'max_stack' => 1]);
    }
}
