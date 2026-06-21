<?php

namespace Tests\Unit;

use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use App\Models\TradeOffer;
use App\Models\User;
use App\Services\TradeService;
use Tests\TestCase;

class TradeServiceTest extends TestCase
{
    private TradeService $service;
    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TradeService::class);
        $this->user1 = User::factory()->create(['gold' => 1000, 'name' => 'Игрок1']);
        $this->user2 = User::factory()->create(['gold' => 1000, 'name' => 'Игрок2']);
    }

    public function test_create_trade(): void
    {
        $trade = $this->service->createTrade($this->user1->id, $this->user2->id);

        $this->assertDatabaseHas('trade_offers', [
            'initiator_id' => $this->user1->id,
            'partner_id' => $this->user2->id,
            'status' => 'active',
        ]);
        $this->assertEquals($this->user1->id, $trade->initiator_id);
    }

    public function test_cannot_trade_with_self(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Нельзя торговать с самим собой');
        $this->service->createTrade($this->user1->id, $this->user1->id);
    }

    public function test_cannot_create_duplicate_trade(): void
    {
        $this->service->createTrade($this->user1->id, $this->user2->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('уже есть активный обмен');
        $this->service->createTrade($this->user1->id, $this->user2->id);
    }

    public function test_add_item_to_trade_aggregates_by_template(): void
    {
        $template = ItemTemplate::factory()->material()->create(['max_stack' => 100]);

        ItemInstance::factory()->create([
            'owner_id' => $this->user1->id,
            'template_id' => $template->id,
            'quantity' => 100,
        ]);
        ItemInstance::factory()->create([
            'owner_id' => $this->user1->id,
            'template_id' => $template->id,
            'quantity' => 100,
        ]);
        ItemInstance::factory()->create([
            'owner_id' => $this->user1->id,
            'template_id' => $template->id,
            'quantity' => 50,
        ]);

        $trade = $this->service->createTrade($this->user1->id, $this->user2->id);
        $trade = $this->service->addItem($this->user1->id, $trade->id, $template->id, 150);

        $this->assertDatabaseHas('trade_items', [
            'trade_id' => $trade->id,
            'template_id' => $template->id,
            'quantity' => 150,
        ]);
    }

    public function test_add_item_fails_if_not_enough(): void
    {
        $template = ItemTemplate::factory()->material()->create();
        ItemInstance::factory()->create([
            'owner_id' => $this->user1->id,
            'template_id' => $template->id,
            'quantity' => 10,
        ]);

        $trade = $this->service->createTrade($this->user1->id, $this->user2->id);

        $this->expectException(\RuntimeException::class);
        $this->service->addItem($this->user1->id, $trade->id, $template->id, 50);
    }

    public function test_reduce_item(): void
    {
        $template = ItemTemplate::factory()->material()->create();
        ItemInstance::factory()->create([
            'owner_id' => $this->user1->id,
            'template_id' => $template->id,
            'quantity' => 100,
        ]);

        $trade = $this->service->createTrade($this->user1->id, $this->user2->id);
        $trade = $this->service->addItem($this->user1->id, $trade->id, $template->id, 50);

        $tradeItem = $trade->initiatorItems->first();
        $trade = $this->service->reduceItem($this->user1->id, $trade->id, $tradeItem->id, 20);

        $this->assertDatabaseHas('trade_items', ['id' => $tradeItem->id, 'quantity' => 30]);
    }

    public function test_add_gold(): void
    {
        $trade = $this->service->createTrade($this->user1->id, $this->user2->id);
        $trade = $this->service->addGold($this->user1->id, $trade->id, 500);

        $this->assertDatabaseHas('trade_offers', [
            'id' => $trade->id,
            'initiator_gold' => 500,
        ]);
    }

    public function test_add_gold_fails_if_not_enough(): void
    {
        $trade = $this->service->createTrade($this->user1->id, $this->user2->id);

        $this->expectException(\RuntimeException::class);
        $this->service->addGold($this->user1->id, $trade->id, 9999);
    }

    public function test_full_trade_execution_with_gold(): void
    {
        $wood = ItemTemplate::factory()->material()->create(['name' => 'Дерево']);
        $sword = ItemTemplate::factory()->equipment()->create(['name' => 'Меч']);

        ItemInstance::factory()->create([
            'owner_id' => $this->user1->id,
            'template_id' => $wood->id,
            'quantity' => 100,
        ]);
        ItemInstance::factory()->create([
            'owner_id' => $this->user2->id,
            'template_id' => $sword->id,
            'quantity' => 1,
        ]);

        $trade = $this->service->createTrade($this->user1->id, $this->user2->id);
        $this->service->addItem($this->user1->id, $trade->id, $wood->id, 50);
        $this->service->addGold($this->user1->id, $trade->id, 100);

        $this->service->addItem($this->user2->id, $trade->id, $sword->id, 1);
        $this->service->addGold($this->user2->id, $trade->id, 50);

        $this->service->accept($this->user1->id, $trade->id);
        $this->service->accept($this->user2->id, $trade->id);

        $this->assertDatabaseHas('item_instances', [
            'owner_id' => $this->user2->id,
            'template_id' => $wood->id,
            'quantity' => 50,
        ]);
        $this->assertDatabaseHas('item_instances', [
            'owner_id' => $this->user1->id,
            'template_id' => $sword->id,
            'quantity' => 1,
        ]);

        $this->assertEquals(950, User::find($this->user1->id)->gold);
        $this->assertEquals(1050, User::find($this->user2->id)->gold);

        $this->assertDatabaseHas('trade_offers', [
            'id' => $trade->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('item_instances', [
            'owner_id' => $this->user1->id,
            'template_id' => $wood->id,
            'quantity' => 50,
        ]);
    }

    public function test_trade_execution_with_multiple_stacks(): void
    {
        $template = ItemTemplate::factory()->material()->create(['max_stack' => 100, 'name' => 'Руда']);

        ItemInstance::factory()->create([
            'owner_id' => $this->user1->id,
            'template_id' => $template->id,
            'quantity' => 100,
        ]);
        ItemInstance::factory()->create([
            'owner_id' => $this->user1->id,
            'template_id' => $template->id,
            'quantity' => 100,
        ]);
        ItemInstance::factory()->create([
            'owner_id' => $this->user1->id,
            'template_id' => $template->id,
            'quantity' => 50,
        ]);

        $this->user2->update(['gold' => 1000]);

        $trade = $this->service->createTrade($this->user1->id, $this->user2->id);

        $this->service->addItem($this->user1->id, $trade->id, $template->id, 150);
        $this->service->addGold($this->user2->id, $trade->id, 500);

        $this->service->accept($this->user1->id, $trade->id);
        $this->service->accept($this->user2->id, $trade->id);

        $totalOre = \App\Models\ItemInstance::where('owner_id', $this->user2->id)
            ->where('template_id', $template->id)
            ->sum('quantity');
        $this->assertEquals(150, $totalOre);

        $remainingOre = \App\Models\ItemInstance::where('owner_id', $this->user1->id)
            ->where('template_id', $template->id)
            ->sum('quantity');
        $this->assertEquals(100, $remainingOre);
    }

    public function test_cancel_trade(): void
    {
        $trade = $this->service->createTrade($this->user1->id, $this->user2->id);
        $this->service->cancel($this->user1->id, $trade->id);

        $this->assertDatabaseHas('trade_offers', [
            'id' => $trade->id,
            'status' => 'cancelled',
        ]);
    }
}
