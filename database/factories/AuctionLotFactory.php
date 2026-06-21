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
            'template_id' => ItemTemplate::factory()->material(),
            'quantity' => 1,
            'price' => 100,
            'commission_percent' => 5,
            'status' => 'active',
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function sold(): static
    {
        return $this->state([
            'status' => 'sold',
            'buyer_id' => User::factory(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
