<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'template_id' => ItemTemplate::factory(),
            'owner_id' => User::factory(),
            'quantity' => 1,
            'durability' => 100,
            'stats' => [],
        ];
    }

    public function stack(int $quantity = 50): static
    {
        return $this->state(fn(array $attributes) => [
            'quantity' => $quantity,
            'template_id' => ItemTemplate::factory()->material(),
        ]);
    }
}
