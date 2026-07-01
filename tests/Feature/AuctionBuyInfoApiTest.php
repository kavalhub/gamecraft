<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuctionLot;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionBuyInfoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_buy_info_returns_purchase_limits(): void
    {
        $register = $this->postJson('/api/register', [
            'username' => 'buyerhero',
            'password' => 'secret',
        ]);

        $token = $register->json('token');
        $characterUuid = $register->json('character_uuid');
        $lot = AuctionLot::where('is_infinite', true)->where('template_slug', 'wood')->first();

        $this->assertNotNull($lot);

        $response = $this->withToken($token)->getJson(
            "/api/auction/{$characterUuid}/lot/{$lot->uuid}/buy-info"
        );

        $response->assertOk()
            ->assertJsonStructure([
                'max_purchasable',
                'max_by_gold',
                'max_by_inventory',
                'gold_available',
                'template_name',
                'price',
            ]);

        $this->assertGreaterThan(0, $response->json('max_purchasable'));
    }

    public function test_quest_item_lot_hidden_when_already_in_inventory(): void
    {
        $register = $this->postJson('/api/register', [
            'username' => 'questbuyer',
            'password' => 'secret',
        ]);

        $token = $register->json('token');
        $characterUuid = $register->json('character_uuid');
        $character = \App\Models\Character::where('uuid', $characterUuid)->firstOrFail();

        app(\App\Services\InventoryService::class)->addItem(
            $character,
            'quest_first_wooden_sword',
            'quest_item',
            null,
            'quest_item_stub'
        );

        $response = $this->withToken($token)->getJson(
            "/api/auction/lots?character_uuid={$characterUuid}"
        );

        $response->assertOk();
        $slugs = array_column($response->json('lots'), 'template_slug');
        $this->assertNotContains('quest_first_wooden_sword', $slugs);
    }
}
