<?php

namespace Database\Factories;

use App\Models\TradeOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradeOfferFactory extends Factory
{
    protected $model = TradeOffer::class;

    public function definition(): array
    {
        return [
            'initiator_id' => User::factory(),
            'partner_id' => User::factory(),
            'initiator_gold' => 0,
            'partner_gold' => 0,
            'initiator_accepted' => false,
            'partner_accepted' => false,
            'status' => 'active',
        ];
    }
}
