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

class TradeResourceQuantityTest extends TestCase
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

        app(StorageProvisioningService::class)->provisionDefaults($this->player2);
        app(\App\Services\CurrencyService::class)->credit($this->player2, 1000, 'test', []);
    }

    public function test_trade_resources_preserves_quantity(): void
    {
        // Player 1 создаёт деревянный меч (чертёж трансформируется в меч)
        $this->inventoryService->addResource($this->player1, 'wood', 10);
        $sword = WorkbenchHelper::craftWoodenSwordFromInventory($this->player1);

        $player1WoodBeforeTrade = $this->inventoryService->getResourceQuantity($this->player1, 'wood');

        // Player 2 покупает 5 дерева и чертёж
        $this->inventoryService->addResource($this->player2, 'wood', 5);
        $blueprint2 = $this->craftingService->createBlueprint($this->player2, 'craft_wooden_sword');

        $this->assertEquals(5, $this->inventoryService->getResourceQuantity($this->player2, 'wood'));
        
        $player2Blueprints = $this->inventoryService->getCharacterItems($this->player2)
            ->where('stage', 'blueprint')
            ->where('template_slug', 'recipe_wooden_sword');
        $this->assertEquals(1, $player2Blueprints->count());

        // Создаём обмен
        $trade = $this->tradeService->createTrade($this->player1, $this->player2);

        // Player 1 добавляет меч
        $this->tradeService->addItemToTrade($this->player1, $trade, $sword->uuid);

        // Player 2 добавляет 5 дерева
        $this->tradeService->addResourceToTrade($this->player2, $trade, 'wood', 5);

        // Player 2 добавляет 1 чертёж (как предмет)
        $this->tradeService->addItemToTrade($this->player2, $trade, $blueprint2->uuid);

        // Подтверждаем обмен
        $trade = $this->tradeService->confirmTrade($this->player1, $trade);
        $trade = $this->tradeService->confirmTrade($this->player2, $trade);

        $this->assertEquals('completed', $trade->status);

        // Проверяем что Player 1 получил 5 дерева
        $player1WoodAfterTrade = $this->inventoryService->getResourceQuantity($this->player1, 'wood');
        $this->assertEquals($player1WoodBeforeTrade + 5, $player1WoodAfterTrade, 'Player 1 должен получить 5 дерева');

        // Проверяем что Player 1 получил 1 чертёж (от Player 2)
        // Note: свой чертёж Player 1 использовал для крафта меча
        $player1Blueprints = $this->inventoryService->getCharacterItems($this->player1)
            ->where('stage', 'blueprint')
            ->where('template_slug', 'recipe_wooden_sword');
        $this->assertEquals(1, $player1Blueprints->count(), 'Player 1 должен получить 1 чертёж от Player 2');

        // Проверяем что Player 2 получил меч
        $player2Items = $this->inventoryService->getCharacterItems($this->player2);
        $this->assertTrue($player2Items->contains('uuid', $sword->uuid), 'Player 2 должен получить меч');
    }
}
