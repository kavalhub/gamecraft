<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ItemTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemTemplateFactory extends Factory
{
    protected $model = ItemTemplate::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->word(),
            'type' => fake()->randomElement(['material', 'equipment', 'consumable', 'recipe']),
            'icon' => fake()->randomElement(['🗡️', '🛡️', '🧪', '📜', '🪵', '⛏️']),
            'is_stackable' => fake()->boolean(70),
            'max_stack' => fake()->numberBetween(1, 999),
            'description' => fake()->sentence(),
        ];
    }

    public function material(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'material',
            'is_stackable' => true,
            'max_stack' => 999,
        ]);
    }

    public function equipment(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'equipment',
            'is_stackable' => false,
            'max_stack' => 1,
        ]);
    }

    public function consumable(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'consumable',
            'is_stackable' => true,
            'max_stack' => 99,
        ]);
    }

    public function recipe(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'recipe',
            'is_stackable' => false,
            'max_stack' => 1,
        ]);
    }
}
