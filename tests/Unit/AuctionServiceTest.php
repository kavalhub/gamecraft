<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Resources;
use App\Models\User;
use App\Services\AuctionService;
use App\Services\CraftingService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuctionService $auctionService;
    private InventoryService $inventoryService;
    private CraftingService $craftingService;
    private Character $seller;
    private Character $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->auctionService = app(AuctionService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->craftingService = app(CraftingService::class);

        $sellerUser = User::where('email', 'test@example.com')->first();
        $this->seller = $sellerUser->characters()->where('character_type', 'player')->first();

        $buyerUser = User::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Buyer',
            'email' => 'buyer@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->buyer = Character::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_uuid' => $buyerUser->uuid,
            'character_type' => 'player',
            'name' => 'Buyer Character',
            'active' => true,
        ]);

        // Создаём хранилища для покупателя
        $inventory = \App\Models\Storage::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'characters_uuid' => $this->buyer->uuid,
            'storage_type' => 'inventory',
            'name' => 'Инвентарь',
            'active' => true,
        ]);
        for ($i = 0; $i < 50; $i++) {
            \App\Models\Slot::create([
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'storage_uuid' => $inventory->uuid,
                'slot_type' => null,
            ]);
        }

        // Даём покупателю 1000 золота
        $this->inventoryService->addResource($this->buyer, 'gold', 1000);
    }

    public function test_list_lot_lists_item_in_one_step(): void
    {
        $this->inventoryService->addResource($this->seller, 'wood', 10);
        $blueprint = $this->craftingService->createBlueprint($this->seller, 'craft_wooden_sword');
        $item = $this->craftingService->craftItem($this->seller, 'craft_wooden_sword', $blueprint->uuid);

        $lot = $this->auctionService->listLot($this->seller, $item->uuid, 150);

        $this->assertEquals('active', $lot->status);
        $this->assertEquals(150, $lot->price);

        $item->refresh();
        $this->assertNull($item->temporary_slot_uuid);
    }

    public function test_prepare_lot_creates_temporary_slot(): void
    {
        $this->inventoryService->addResource($this->seller, 'wood', 10);
        $blueprint = $this->craftingService->createBlueprint($this->seller, 'craft_wooden_sword');
        $item = $this->craftingService->craftItem($this->seller, 'craft_wooden_sword', $blueprint->uuid);

        $temporarySlot = $this->auctionService->prepareLot($this->seller, $item->uuid, 100);

        $this->assertNotNull($temporarySlot);
        $this->assertTrue($temporarySlot->active);
        $this->assertEquals($this->seller->uuid, $temporarySlot->character_uuid);

        $item->refresh();
        $this->assertEquals($temporarySlot->uuid, $item->temporary_slot_uuid);
    }

    public function test_confirm_lot_moves_item_to_auction(): void
    {
        $this->inventoryService->addResource($this->seller, 'wood', 10);
        $blueprint = $this->craftingService->createBlueprint($this->seller, 'craft_wooden_sword');
        $item = $this->craftingService->craftItem($this->seller, 'craft_wooden_sword', $blueprint->uuid);

        $temporarySlot = $this->auctionService->prepareLot($this->seller, $item->uuid, 100);
        $lot = $this->auctionService->confirmLot($this->seller, $item->uuid, 100);

        $this->assertEquals('active', $lot->status);
        $this->assertEquals(100, $lot->price);
        $this->assertEquals($this->seller->uuid, $lot->seller_uuid);

        $item->refresh();
        $this->assertNull($item->temporary_slot_uuid);
        $temporarySlot->refresh();
        $this->assertFalse($temporarySlot->active);
    }

    public function test_cancel_lot_returns_item_to_seller(): void
    {
        $this->inventoryService->addResource($this->seller, 'wood', 10);
        $blueprint = $this->craftingService->createBlueprint($this->seller, 'craft_wooden_sword');
        $item = $this->craftingService->craftItem($this->seller, 'craft_wooden_sword', $blueprint->uuid);

        $this->auctionService->prepareLot($this->seller, $item->uuid, 100);
        $lot = $this->auctionService->confirmLot($this->seller, $item->uuid, 100);
        $cancelledLot = $this->auctionService->cancelLot($this->seller, $lot->uuid);

        $this->assertEquals('cancelled', $cancelledLot->status);

        $item->refresh();
        $sellerInventory = $this->seller->storages()->where('storage_type', 'inventory')->first();
        $this->assertEquals($sellerInventory->uuid, $item->slot->storage_uuid);
    }

    public function test_buy_lot_transfers_item_and_gold(): void
    {
        $this->inventoryService->addResource($this->seller, 'wood', 10);
        $blueprint = $this->craftingService->createBlueprint($this->seller, 'craft_wooden_sword');
        $item = $this->craftingService->craftItem($this->seller, 'craft_wooden_sword', $blueprint->uuid);

        $this->auctionService->prepareLot($this->seller, $item->uuid, 100);
        $lot = $this->auctionService->confirmLot($this->seller, $item->uuid, 100);

        $sellerGoldBefore = $this->inventoryService->getResourceQuantity($this->seller, 'gold');
        $buyerGoldBefore = $this->inventoryService->getResourceQuantity($this->buyer, 'gold');

        $result = $this->auctionService->buyLot($this->buyer, $lot->uuid);

        $this->assertEquals('sold', $result['lot']->status);
        $this->assertEquals($this->buyer->uuid, $result['lot']->buyer_uuid);

        $item->refresh();
        $buyerInventory = $this->buyer->storages()->where('storage_type', 'inventory')->first();
        $this->assertEquals($buyerInventory->uuid, $item->slot->storage_uuid);

        $this->assertEquals($buyerGoldBefore - 100, $this->inventoryService->getResourceQuantity($this->buyer, 'gold'));
        $commission = (int) round(100 * 5 / 100);
        $this->assertEquals($sellerGoldBefore + (100 - $commission), $this->inventoryService->getResourceQuantity($this->seller, 'gold'));
    }

    public function test_buy_lot_fails_without_enough_gold(): void
    {
        $this->inventoryService->addResource($this->seller, 'wood', 10);
        $blueprint = $this->craftingService->createBlueprint($this->seller, 'craft_wooden_sword');
        $item = $this->craftingService->craftItem($this->seller, 'craft_wooden_sword', $blueprint->uuid);

        $this->auctionService->prepareLot($this->seller, $item->uuid, 20000);
        $lot = $this->auctionService->confirmLot($this->seller, $item->uuid, 20000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно золота');
        $this->auctionService->buyLot($this->buyer, $lot->uuid);
    }

    public function test_buy_lot_fails_for_own_lot(): void
    {
        $this->inventoryService->addResource($this->seller, 'wood', 10);
        $blueprint = $this->craftingService->createBlueprint($this->seller, 'craft_wooden_sword');
        $item = $this->craftingService->craftItem($this->seller, 'craft_wooden_sword', $blueprint->uuid);

        $this->auctionService->prepareLot($this->seller, $item->uuid, 100);
        $lot = $this->auctionService->confirmLot($this->seller, $item->uuid, 100);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Нельзя купить свой собственный лот');
        $this->auctionService->buyLot($this->seller, $lot->uuid);
    }

    public function test_get_active_lots(): void
    {
        $this->inventoryService->addResource($this->seller, 'wood', 20);
        
        $blueprint1 = $this->craftingService->createBlueprint($this->seller, 'craft_wooden_sword');
        $item1 = $this->craftingService->craftItem($this->seller, 'craft_wooden_sword', $blueprint1->uuid);
        $this->auctionService->prepareLot($this->seller, $item1->uuid, 100);
        $lot1 = $this->auctionService->confirmLot($this->seller, $item1->uuid, 100);

        $blueprint2 = $this->craftingService->createBlueprint($this->seller, 'craft_wooden_sword');
        $item2 = $this->craftingService->craftItem($this->seller, 'craft_wooden_sword', $blueprint2->uuid);
        $this->auctionService->prepareLot($this->seller, $item2->uuid, 200);
        $lot2 = $this->auctionService->confirmLot($this->seller, $item2->uuid, 200);

        $activeLots = $this->auctionService->getActiveLots();

        $this->assertGreaterThanOrEqual(2, $activeLots->count());
        $this->assertTrue($activeLots->contains('uuid', $lot1->uuid));
        $this->assertTrue($activeLots->contains('uuid', $lot2->uuid));
    }

    public function test_buy_infinite_lot_creates_new_item(): void
    {
        $infiniteLot = \App\Models\AuctionLot::where('is_infinite', true)->first();

        $buyerGoldBefore = $this->inventoryService->getResourceQuantity($this->buyer, 'gold');

        $result = $this->auctionService->buyLot($this->buyer, $infiniteLot->uuid, 1);

        $this->assertTrue($result['is_infinite']);
        $this->assertEquals($infiniteLot->template_slug, $result['result']->template_slug);
        $this->assertEquals($buyerGoldBefore - $infiniteLot->price, $this->inventoryService->getResourceQuantity($this->buyer, 'gold'));
    }

    public function test_buy_infinite_lot_bulk_stacks_resources(): void
    {
        $infiniteLot = \App\Models\AuctionLot::where('is_infinite', true)
            ->where('template_slug', 'wood')
            ->first();

        $quantity = 45;
        $buyerGoldBefore = $this->inventoryService->getResourceQuantity($this->buyer, 'gold');

        $result = $this->auctionService->buyLot($this->buyer, $infiniteLot->uuid, $quantity);

        $this->assertTrue($result['is_infinite']);
        $this->assertEquals($quantity, $result['quantity']);
        $this->assertEquals($buyerGoldBefore - ($infiniteLot->price * $quantity), $this->inventoryService->getResourceQuantity($this->buyer, 'gold'));
        $this->assertEquals($quantity, $this->inventoryService->getResourceQuantity($this->buyer, 'wood'));

        $woodStacks = Resources::where('template_slug', 'wood')
            ->whereIn('slot_uuid', function ($q) {
                $q->select('uuid')->from('slots')->whereIn('storage_uuid', function ($q2) {
                    $q2->select('uuid')->from('storages')->where('characters_uuid', $this->buyer->uuid);
                });
            })->get();

        $this->assertCount(3, $woodStacks);
        $this->assertEquals(45, $woodStacks->sum('quantity'));
    }

    public function test_get_max_purchasable_quantity_for_infinite_lot(): void
    {
        $infiniteLot = \App\Models\AuctionLot::where('is_infinite', true)
            ->where('template_slug', 'wood')
            ->first();

        $limits = $this->auctionService->getBuyLimits($this->buyer, $infiniteLot);

        $this->assertGreaterThan(0, $limits['max_purchasable']);
        $this->assertLessThanOrEqual(intdiv(1000, $infiniteLot->price), $limits['max_purchasable']);
        $this->assertEquals(1000, $limits['gold_available']);
        $this->assertEquals(intdiv(1000, $infiniteLot->price), $limits['max_by_gold']);
    }

    public function test_finite_lot_requires_exact_quantity(): void
    {
        $this->inventoryService->addResource($this->seller, 'wood', 10);
        $blueprint = $this->craftingService->createBlueprint($this->seller, 'craft_wooden_sword');
        $item = $this->craftingService->craftItem($this->seller, 'craft_wooden_sword', $blueprint->uuid);

        $this->auctionService->prepareLot($this->seller, $item->uuid, 100);
        $lot = $this->auctionService->confirmLot($this->seller, $item->uuid, 100);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Можно купить только весь лот целиком');
        $this->auctionService->buyLot($this->buyer, $lot->uuid, 2);
    }
}
