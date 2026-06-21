<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuctionLot;
use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use App\Models\User;
use Tests\TestCase;

class AuctionApiTest extends TestCase
{
    public function test_get_active_lots_api(): void
    {
        $seller = User::factory()->create(['name' => 'Продавец']);
        $template = ItemTemplate::factory()->material()->create(['name' => 'Дерево']);

        AuctionLot::factory()->create([
            'seller_id' => $seller->id,
            'template_id' => $template->id,
            'status' => 'active',
            'price' => 100,
            'quantity' => 5,
        ]);

        $response = $this->getJson('/api/auction');

        $response->assertOk()
            ->assertJsonStructure([
                'lots' => [
                    '*' => [
                        'id',
                        'seller_id',
                        'seller_name',
                        'template_id',
                        'template_name',
                        'template_type',
                        'quantity',
                        'price',
                    ],
                ],
            ])
            ->assertJson([
                'lots' => [
                    [
                        'template_name' => 'Дерево',
                        'price' => 100,
                        'quantity' => 5,
                    ],
                ],
            ]);
    }

    public function test_get_my_lots_api(): void
    {
        $seller = User::factory()->create();
        $template = ItemTemplate::factory()->material()->create(['name' => 'Дерево']);

        AuctionLot::factory()->create([
            'seller_id' => $seller->id,
            'template_id' => $template->id,
            'status' => 'active',
            'price' => 100,
        ]);

        $response = $this->getJson("/api/auction/my?user_id={$seller->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'lots' => [
                    '*' => ['id', 'status', 'template_name', 'price'],
                ],
            ]);
    }

    public function test_get_my_lots_api_requires_user_id(): void
    {
        $response = $this->getJson('/api/auction/my');

        $response->assertStatus(400)
            ->assertJson(['error' => 'user_id required']);
    }

    public function test_create_lot_api(): void
    {
        $seller = User::factory()->create(['gold' => 1000]);
        $template = ItemTemplate::factory()->material()->create();

        ItemInstance::factory()->create([
            'owner_id' => $seller->id,
            'template_id' => $template->id,
            'quantity' => 10,
        ]);

        $response = $this->postJson('/api/auction', [
            'user_id' => $seller->id,
            'template_id' => $template->id,
            'price' => 100,
            'quantity' => 5,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'lot_id']);
    }

    public function test_buy_lot_api(): void
    {
        $seller = User::factory()->create(['gold' => 1000]);
        $buyer = User::factory()->create(['gold' => 1000]);
        $template = ItemTemplate::factory()->material()->create([
            'is_stackable' => true,
            'max_stack' => 200,
        ]);

        ItemInstance::factory()->create([
            'owner_id' => $seller->id,
            'template_id' => $template->id,
            'quantity' => 5,
        ]);

        $lot = AuctionLot::factory()->create([
            'seller_id' => $seller->id,
            'template_id' => $template->id,
            'quantity' => 5,
            'price' => 500,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/auction/{$lot->id}/buy", [
            'user_id' => $buyer->id,
        ]);

        $response->assertOk();
    }

    public function test_cancel_lot_api(): void
    {
        $seller = User::factory()->create();
        $template = ItemTemplate::factory()->material()->create();

        ItemInstance::factory()->create([
            'owner_id' => $seller->id,
            'template_id' => $template->id,
            'quantity' => 5,
        ]);

        $lot = AuctionLot::factory()->create([
            'seller_id' => $seller->id,
            'template_id' => $template->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/auction/{$lot->id}/cancel", [
            'user_id' => $seller->id,
        ]);

        $response->assertOk();
    }
}
