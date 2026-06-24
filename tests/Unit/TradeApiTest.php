<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\User;
use Tests\TestCase;

class TradeApiTest extends TestCase
{
    public function test_create_trade_api(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $response = $this->postJson('/api/trade', [
            'initiator_id' => $user1->id,
            'partner_id' => $user2->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['trade_id']);
    }

    public function test_add_item_api(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $template = ItemTemplate::factory()->material()->create();
        Item::factory()->create([
            'owner_id' => $user1->id,
            'template_id' => $template->id,
            'quantity' => 100,
        ]);

        $trade = $this->postJson('/api/trade', [
            'initiator_id' => $user1->id,
            'partner_id' => $user2->id,
        ])->json('trade_id');

        $response = $this->postJson("/api/trade/{$trade}/item", [
            'user_id' => $user1->id,
            'template_id' => $template->id,
            'quantity' => 50,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'trade']);
    }

    public function test_accept_trade_api(): void
    {
        $user1 = User::factory()->create(['gold' => 1000]);
        $user2 = User::factory()->create(['gold' => 1000]);
        $wood = ItemTemplate::factory()->material()->create();
        $sword = ItemTemplate::factory()->equipment()->create();

        Item::factory()->create([
            'owner_id' => $user1->id,
            'template_id' => $wood->id,
            'quantity' => 50,
        ]);
        Item::factory()->create([
            'owner_id' => $user2->id,
            'template_id' => $sword->id,
            'quantity' => 1,
        ]);

        $tradeId = $this->postJson('/api/trade', [
            'initiator_id' => $user1->id,
            'partner_id' => $user2->id,
        ])->json('trade_id');

        $this->postJson("/api/trade/{$tradeId}/item", [
            'user_id' => $user1->id,
            'template_id' => $wood->id,
            'quantity' => 50,
        ]);

        $this->postJson("/api/trade/{$tradeId}/item", [
            'user_id' => $user2->id,
            'template_id' => $sword->id,
            'quantity' => 1,
        ]);

        $this->postJson("/api/trade/{$tradeId}/accept", ['user_id' => $user1->id])->assertOk();
        $response = $this->postJson("/api/trade/{$tradeId}/accept", ['user_id' => $user2->id]);

        $response->assertOk()
            ->assertJson(['message' => 'Обмен завершён!']);

        $this->assertDatabaseHas('item_instances', [
            'owner_id' => $user2->id,
            'template_id' => $wood->id,
        ]);
        $this->assertDatabaseHas('item_instances', [
            'owner_id' => $user1->id,
            'template_id' => $sword->id,
        ]);
    }
}
