<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\TradeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeResourceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_resource_returns_correct_quantity_and_metadata_in_current_trade(): void
    {
        $this->seedGameDatabase();

        $user1 = User::where('email', 'test@example.com')->first();
        $player1 = $user1->characters()->where('character_type', 'player')->first();

        $register = $this->postJson('/api/register', [
            'username' => 'trader2',
            'password' => 'secret',
        ]);
        $player2Uuid = $register->json('character_uuid');
        $player2Token = $register->json('token');

        $user1Token = $user1->createToken('game')->plainTextToken;

        app(InventoryService::class)->addResource($player1, 'wood', 10);

        $trade = app(TradeService::class)->createTrade(
            $player1,
            Character::where('uuid', $player2Uuid)->firstOrFail()
        );

        $this->withToken($user1Token)
            ->postJson("/api/trade/{$player1->uuid}/add-resource", [
                'trade_uuid' => $trade->uuid,
                'template_slug' => 'wood',
                'quantity' => 5,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $response = $this->withToken($user1Token)
            ->getJson("/api/trade/{$player1->uuid}/current");

        $response->assertOk();

        $items = collect($response->json('trade.items'));
        $woodItems = $items->where('template_slug', 'wood');

        $this->assertEquals(5, $woodItems->sum('quantity'));
        $this->assertEquals('🪵', $woodItems->first()['icon']);
        $this->assertEquals('Дерево', $woodItems->first()['name']);
    }
}
