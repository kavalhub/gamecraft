<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\User;
use App\Services\CraftingService;
use App\Services\InventoryService;
use App\Services\StorageProvisioningService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WorkbenchHelper;
use Tests\TestCase;

class TradeServiceTest extends TestCase
{
    use RefreshDatabase;

    private TradeService $tradeService;
    private InventoryService $inventoryService;
    private CraftingService $craftingService;
    private Character $player1;
    private Character $player2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->tradeService = app(TradeService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->craftingService = app(CraftingService::class);

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

        // Создаём хранилища и стартовое золото для player2
        app(StorageProvisioningService::class)->provisionDefaults($this->player2);
        app(\App\Services\CurrencyService::class)->grantStartingGold($this->player2);
    }

    public function test_create_trade(): void
    {
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);

        $this->assertNotNull($trade);
        $this->assertEquals('pending', $trade->status);
        $this->assertEquals($this->player1->uuid, $trade->initiator_uuid);
        $this->assertEquals($this->player2->uuid, $trade->partner_uuid);
    }

    public function test_cannot_trade_with_self(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Нельзя обмениваться с самим собой');
        $this->tradeService->createTrade($this->player1, $this->player1);
    }

    public function test_add_item_to_trade(): void
    {
        $this->inventoryService->addResource($this->player1, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player1);

        $trade = $this->tradeService->createTrade($this->player1, $this->player2);
        $tradeItem = $this->tradeService->addItemToTrade($this->player1, $trade, $item->uuid);

        $this->assertNotNull($tradeItem);
        $this->assertEquals($item->uuid, $tradeItem->item_uuid);
        $this->assertEquals($this->player1->uuid, $tradeItem->character_uuid);

        $item->refresh();
        $player1Inventory = $this->player1->storages()->where('storage_type', 'inventory')->first();
        $this->assertEquals($player1Inventory->uuid, $item->slot->storage_uuid);
        $this->assertNotNull($item->temporary_slot_uuid);
        $this->assertDatabaseHas('temporary_slots', ['uuid' => $item->temporary_slot_uuid]);
    }

    public function test_add_resource_to_trade(): void
    {
        $this->inventoryService->addResource($this->player1, 'wood', 10);

        $trade = $this->tradeService->createTrade($this->player1, $this->player2);
        $tradeItem = $this->tradeService->addResourceToTrade($this->player1, $trade, 'wood', 5);

        $this->assertNotNull($tradeItem);
        $this->assertEquals(5, $tradeItem->quantity);
        $this->assertEquals($this->player1->uuid, $tradeItem->character_uuid);
    }

    public function test_confirm_trade(): void
    {
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);
        $trade = $this->tradeService->confirmTrade($this->player1, $trade);

        $this->assertTrue($trade->initiator_accepted);
        $this->assertFalse($trade->partner_accepted);
    }

    public function test_full_trade_execution(): void
    {
        // Player 1 даёт предмет
        $this->inventoryService->addResource($this->player1, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player1);

        // Player 2 имеет 1000 золота (уже добавлено в setUp)

        // Создаём обмен
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);
        $this->tradeService->addItemToTrade($this->player1, $trade, $item->uuid);
        $this->tradeService->addResourceToTrade($this->player2, $trade, 'gold', 100);

        // Подтверждаем
        $trade = $this->tradeService->confirmTrade($this->player1, $trade);
        $trade = $this->tradeService->confirmTrade($this->player2, $trade);

        // Проверяем результат
        $this->assertEquals('completed', $trade->status);

        $item->refresh();
        $player2Inventory = $this->player2->storages()->where('storage_type', 'inventory')->first();
        $this->assertEquals($player2Inventory->uuid, $item->slot->storage_uuid);

        $player1Gold = $this->inventoryService->getResourceQuantity($this->player1, 'gold');
        $this->assertEquals(1100, $player1Gold); // 1000 + 100

        $player2Gold = $this->inventoryService->getResourceQuantity($this->player2, 'gold');
        $this->assertEquals(900, $player2Gold); // 1000 - 100
    }

    public function test_cancel_trade(): void
    {
        $this->inventoryService->addResource($this->player1, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player1);

        $trade = $this->tradeService->createTrade($this->player1, $this->player2);
        $this->tradeService->addItemToTrade($this->player1, $trade, $item->uuid);

        $trade = $this->tradeService->cancelTrade($this->player1, $trade);

        $this->assertEquals('cancelled', $trade->status);

        $item->refresh();
        $player1Inventory = $this->player1->storages()->where('storage_type', 'inventory')->first();
        $this->assertEquals($player1Inventory->uuid, $item->slot->storage_uuid);
    }

    public function test_get_character_trades(): void
    {
        // Создаём первый обмен и отменяем его
        $trade1 = $this->tradeService->createTrade($this->player1, $this->player2);
        $this->tradeService->cancelTrade($this->player1, $trade1);

        // Создаём второй обмен
        $trade2 = $this->tradeService->createTrade($this->player2, $this->player1);

        $trades = $this->tradeService->getCharacterTrades($this->player1);

        $this->assertGreaterThanOrEqual(2, $trades->count());
    }
}
