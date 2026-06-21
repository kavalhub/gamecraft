<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AuctionLot;
use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuctionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AuctionService $service;
    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AuctionService::class);
        $this->seller = User::factory()->create(['gold' => 1000, 'name' => 'Продавец']);
        $this->buyer = User::factory()->create(['gold' => 1000, 'name' => 'Покупатель']);
    }

    public function test_get_active_lots_returns_empty_array_when_no_lots(): void
    {
        $lots = $this->service->getActiveLots();
        $this->assertIsArray($lots);
        $this->assertEmpty($lots);
    }

    public function test_get_active_lots_returns_only_active(): void
    {
        $template = ItemTemplate::factory()->material()->create(['name' => 'Дерево']);

        AuctionLot::factory()->create([
            'seller_id' => $this->seller->id,
            'template_id' => $template->id,
            'status' => 'active',
            'price' => 100,
            'quantity' => 5,
        ]);

        AuctionLot::factory()->create([
            'seller_id' => $this->seller->id,
            'template_id' => $template->id,
            'status' => 'sold',
            'price' => 200,
        ]);

        $lots = $this->service->getActiveLots();

        $this->assertCount(1, $lots);
        $this->assertEquals(100, $lots[0]['price']);
        $this->assertEquals('Дерево', $lots[0]['template_name']);
    }

    public function test_get_active_lots_filters_by_type(): void
    {
        $wood = ItemTemplate::factory()->material()->create(['name' => 'Дерево', 'type' => 'material']);
        $sword = ItemTemplate::factory()->equipment()->create(['name' => 'Меч', 'type' => 'equipment']);

        AuctionLot::factory()->create([
            'seller_id' => $this->seller->id,
            'template_id' => $wood->id,
            'status' => 'active',
        ]);

        AuctionLot::factory()->create([
            'seller_id' => $this->seller->id,
            'template_id' => $sword->id,
            'status' => 'active',
        ]);

        $materialLots = $this->service->getActiveLots('material');
        $this->assertCount(1, $materialLots);
        $this->assertEquals('Дерево', $materialLots[0]['template_name']);

        $equipmentLots = $this->service->getActiveLots('equipment');
        $this->assertCount(1, $equipmentLots);
        $this->assertEquals('Меч', $equipmentLots[0]['template_name']);
    }

    public function test_get_active_lots_filters_by_template_id(): void
    {
        $wood = ItemTemplate::factory()->material()->create(['name' => 'Дерево']);
        $stone = ItemTemplate::factory()->material()->create(['name' => 'Камень']);

        AuctionLot::factory()->create([
            'seller_id' => $this->seller->id,
            'template_id' => $wood->id,
            'status' => 'active',
        ]);

        AuctionLot::factory()->create([
            'seller_id' => $this->seller->id,
            'template_id' => $stone->id,
            'status' => 'active',
        ]);

        $lots = $this->service->getActiveLots(null, $wood->id);
        $this->assertCount(1, $lots);
        $this->assertEquals('Дерево', $lots[0]['template_name']);
    }

    public function test_get_my_lots_returns_user_lots(): void
    {
        $template = ItemTemplate::factory()->material()->create(['name' => 'Дерево']);

        AuctionLot::factory()->create([
            'seller_id' => $this->seller->id,
            'template_id' => $template->id,
            'status' => 'active',
            'price' => 100,
        ]);

        AuctionLot::factory()->create([
            'seller_id' => $this->seller->id,
            'template_id' => $template->id,
            'status' => 'sold',
            'price' => 200,
        ]);

        // Лот другого продавца
        AuctionLot::factory()->create([
            'seller_id' => $this->buyer->id,
            'template_id' => $template->id,
            'status' => 'active',
        ]);

        $lots = $this->service->getMyLots($this->seller->id);

        $this->assertCount(2, $lots);
        $this->assertEquals(100, $lots[0]['price']);
        $this->assertEquals(200, $lots[1]['price']);
    }

    public function test_get_my_lots_returns_empty_for_user_without_lots(): void
    {
        $lots = $this->service->getMyLots($this->seller->id);
        $this->assertIsArray($lots);
        $this->assertEmpty($lots);
    }

    public function test_list_lot_creates_lot_and_removes_items(): void
    {
        $template = ItemTemplate::factory()->material()->create([
            'name' => 'Дерево',
            'is_stackable' => true,
            'max_stack' => 200,
        ]);

        ItemInstance::factory()->create([
            'owner_id' => $this->seller->id,
            'template_id' => $template->id,
            'quantity' => 10,
        ]);

        $lot = $this->service->listLot($this->seller->id, $template->id, 100, 5);

        $this->assertNotNull($lot->id);
        $this->assertEquals($this->seller->id, $lot->seller_id);
        $this->assertEquals($template->id, $lot->template_id);
        $this->assertEquals(5, $lot->quantity);
        $this->assertEquals(100, $lot->price);
        $this->assertEquals('active', $lot->status);

        // Проверка, что предметы списались
        $remaining = ItemInstance::where('owner_id', $this->seller->id)
            ->where('template_id', $template->id)
            ->sum('quantity');
        $this->assertEquals(5, $remaining);
    }

    public function test_list_lot_fails_if_not_enough_items(): void
    {
        $template = ItemTemplate::factory()->material()->create();

        ItemInstance::factory()->create([
            'owner_id' => $this->seller->id,
            'template_id' => $template->id,
            'quantity' => 3,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('В инвентаре только');
        $this->service->listLot($this->seller->id, $template->id, 100, 5);
    }

    public function test_list_lot_fails_with_zero_price(): void
    {
        $template = ItemTemplate::factory()->material()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Цена должна быть больше нуля');
        $this->service->listLot($this->seller->id, $template->id, 0);
    }

    public function test_buy_lot_transfers_gold_and_items(): void
    {
        $template = ItemTemplate::factory()->material()->create([
            'name' => 'Дерево',
            'is_stackable' => true,
            'max_stack' => 200,
        ]);

        // Продавец выставляет 5 дерева за 500 золота
        ItemInstance::factory()->create([
            'owner_id' => $this->seller->id,
            'template_id' => $template->id,
            'quantity' => 5,
        ]);

        $lot = $this->service->listLot($this->seller->id, $template->id, 500, 5);

        // Покупатель покупает
        $this->service->buyLot($this->buyer->id, $lot->id);

        // Проверяем золото (комиссия 5% = 25, продавец получает 475)
        $this->assertEquals(500, $this->buyer->fresh()->gold); // 1000 - 500
        $this->assertEquals(1475, $this->seller->fresh()->gold); // 1000 + 475

        // Проверяем предметы
        $buyerWood = ItemInstance::where('owner_id', $this->buyer->id)
            ->where('template_id', $template->id)
            ->sum('quantity');
        $this->assertEquals(5, $buyerWood);

        // Лот продан
        $this->assertEquals('sold', $lot->fresh()->status);
        $this->assertEquals($this->buyer->id, $lot->fresh()->buyer_id);
    }

    public function test_buy_lot_fails_if_not_enough_gold(): void
    {
        $template = ItemTemplate::factory()->material()->create();

        ItemInstance::factory()->create([
            'owner_id' => $this->seller->id,
            'template_id' => $template->id,
            'quantity' => 5,
        ]);

        $lot = $this->service->listLot($this->seller->id, $template->id, 9999, 5);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно золота');
        $this->service->buyLot($this->buyer->id, $lot->id);
    }

    public function test_buy_lot_fails_for_own_lot(): void
    {
        $template = ItemTemplate::factory()->material()->create();

        ItemInstance::factory()->create([
            'owner_id' => $this->seller->id,
            'template_id' => $template->id,
            'quantity' => 5,
        ]);

        $lot = $this->service->listLot($this->seller->id, $template->id, 100, 5);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Нельзя купить свой лот');
        $this->service->buyLot($this->seller->id, $lot->id);
    }

    public function test_cancel_lot_returns_items(): void
    {
        $template = ItemTemplate::factory()->material()->create([
            'name' => 'Дерево',
            'is_stackable' => true,
            'max_stack' => 200,
        ]);

        ItemInstance::factory()->create([
            'owner_id' => $this->seller->id,
            'template_id' => $template->id,
            'quantity' => 10,
        ]);

        $lot = $this->service->listLot($this->seller->id, $template->id, 100, 5);

        // После выставления осталось 5
        $this->assertEquals(5, ItemInstance::where('owner_id', $this->seller->id)
            ->where('template_id', $template->id)
            ->sum('quantity'));

        // Отменяем лот
        $this->service->cancelLot($this->seller->id, $lot->id);

        // Предметы вернулись
        $this->assertEquals(10, ItemInstance::where('owner_id', $this->seller->id)
            ->where('template_id', $template->id)
            ->sum('quantity'));

        // Лот отменён
        $this->assertEquals('cancelled', $lot->fresh()->status);
    }

    public function test_cancel_lot_fails_for_other_user(): void
    {
        $template = ItemTemplate::factory()->material()->create();

        ItemInstance::factory()->create([
            'owner_id' => $this->seller->id,
            'template_id' => $template->id,
            'quantity' => 5,
        ]);

        $lot = $this->service->listLot($this->seller->id, $template->id, 100, 5);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Вы не владелец');
        $this->service->cancelLot($this->buyer->id, $lot->id);
    }

    public function test_cancel_lot_fails_if_already_sold(): void
    {
        $template = ItemTemplate::factory()->material()->create();

        ItemInstance::factory()->create([
            'owner_id' => $this->seller->id,
            'template_id' => $template->id,
            'quantity' => 5,
        ]);

        $lot = $this->service->listLot($this->seller->id, $template->id, 100, 5);
        $this->service->buyLot($this->buyer->id, $lot->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Лот не активен');
        $this->service->cancelLot($this->seller->id, $lot->id);
    }
}
