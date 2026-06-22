<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuctionLot;
use App\Models\ItemTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuctionLotFactory extends Factory
{
    protected $model = AuctionLot::class;

    public function definition(): array
    {
        return [
            'seller_id' => User::factory(),
            'template_id' => ItemTemplate::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'price' => fake()->numberBetween(10, 1000),
            'commission_percent' => 5,
            'status' => 'active',
            'is_infinite' => false,
        ];
    }

    public function infinite(): static
    {
        return $this->state(fn(array $attributes) => [
            'seller_id' => null,
            'is_infinite' => true,
            'commission_percent' => 0,
        ]);
    }

    public function sold(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'sold',
            'buyer_id' => User::factory(),
            'sold_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
