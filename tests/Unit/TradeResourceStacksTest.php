<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Resources;
use App\Models\TradeItem;
use App\Models\User;
use App\Services\CraftingService;
use App\Services\InventoryService;
use App\Services\StorageProvisioningService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeResourceStacksTest extends TestCase
{
    use RefreshDatabase;

    private TradeService $tradeService;
    private InventoryService $inventoryService;
    private Character $player1;
    private Character $player2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->tradeService = app(TradeService::class);
        $this->inventoryService = app(InventoryService::class);

        $user1 = User::where('email', 'test@example.com')->first();
        $this->player1 = $user1->characters()->where('character_type', 'player')->first();

        $user2 = User::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Player 2',
            'email' => 'player2@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->player2 = Character::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_uuid' => $user2->uuid,
            'character_type' => 'player',
            'name' => 'Player 2 Character',
            'active' => true,
        ]);

        app(StorageProvisioningService::class)->provisionDefaults($this->player2);
        app(\App\Services\CurrencyService::class)->credit($this->player2, 1000, 'test', []);
    }

    public function test_add_resource_creates_correct_stack_count(): void
    {
        $this->inventoryService->addResource($this->player1, 'wood', 120);
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);

        $this->tradeService->addResourceToTrade($this->player1, $trade, 'wood', 120);

        $tradeItems = TradeItem::where('trade_uuid', $trade->uuid)
            ->where('character_uuid', $this->player1->uuid)
            ->where('template_slug', 'wood')
            ->get();

        $this->assertCount(6, $tradeItems);
        $this->assertEquals(120, $tradeItems->sum('quantity'));
    }

    public function test_add_resource_reduces_sender_total(): void
    {
        $this->inventoryService->addResource($this->player1, 'wood', 100);
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);

        $this->tradeService->addResourceToTrade($this->player1, $trade, 'wood', 60);

        $this->assertEquals(40, $this->inventoryService->getResourceQuantity($this->player1, 'wood'));
    }

    public function test_execute_trade_transfers_full_quantity(): void
    {
        $this->inventoryService->addResource($this->player1, 'wood', 120);
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);
        $this->tradeService->addResourceToTrade($this->player1, $trade, 'wood', 120);

        $trade = $this->tradeService->confirmTrade($this->player1, $trade);
        $trade = $this->tradeService->confirmTrade($this->player2, $trade);

        $this->assertEquals('completed', $trade->status);
        $this->assertEquals(120, $this->inventoryService->getResourceQuantity($this->player2, 'wood'));
    }

    public function test_add_resource_fails_when_trade_window_full(): void
    {
        $this->inventoryService->addResource($this->player1, 'wood', 200);
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);

        $this->tradeService->addResourceToTrade($this->player1, $trade, 'wood', 120);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Нет свободных слотов в обмене');
        $this->tradeService->addResourceToTrade($this->player1, $trade, 'wood', 20);
    }

    public function test_cancel_trade_restores_all_stacks(): void
    {
        $this->inventoryService->addResource($this->player1, 'wood', 120);
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);
        $this->tradeService->addResourceToTrade($this->player1, $trade, 'wood', 120);

        $this->assertEquals(0, $this->inventoryService->getResourceQuantity($this->player1, 'wood'));

        $this->tradeService->cancelTrade($this->player1, $trade);

        $this->assertEquals(120, $this->inventoryService->getResourceQuantity($this->player1, 'wood'));
    }

    public function test_add_resource_incrementally_adds_more_stacks(): void
    {
        $this->inventoryService->addResource($this->player1, 'wood', 100);
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);

        $this->tradeService->addResourceToTrade($this->player1, $trade, 'wood', 60);
        $this->tradeService->addResourceToTrade($this->player1, $trade, 'wood', 20);

        $tradeItems = TradeItem::where('trade_uuid', $trade->uuid)
            ->where('character_uuid', $this->player1->uuid)
            ->where('template_slug', 'wood')
            ->get();

        $this->assertCount(4, $tradeItems);
        $this->assertEquals(80, $tradeItems->sum('quantity'));
        $this->assertEquals(20, $this->inventoryService->getResourceQuantity($this->player1, 'wood'));
    }

    public function test_small_quantity_single_stack(): void
    {
        $this->inventoryService->addResource($this->player1, 'wood', 10);
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);

        $tradeItem = $this->tradeService->addResourceToTrade($this->player1, $trade, 'wood', 5);

        $this->assertEquals(5, $tradeItem->quantity);
        $this->assertCount(1, TradeItem::where('trade_uuid', $trade->uuid)->whereNotNull('resource_uuid')->get());
    }
}
