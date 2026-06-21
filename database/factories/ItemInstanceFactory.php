<?php

namespace Database\Factories;

use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemInstanceFactory extends Factory
{
    protected $model = ItemInstance::class;

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
