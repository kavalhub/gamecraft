<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Item;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SpecialSlotService;
use App\Services\StorageProvisioningService;
use App\Services\TradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TradeServiceFullInventoryExchangeTest extends TestCase
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
            'uuid' => Str::uuid()->toString(),
            'name' => 'Player 2',
            'email' => 'player2-full-inv@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->player2 = Character::create([
            'uuid' => Str::uuid()->toString(),
            'user_uuid' => $user2->uuid,
            'character_type' => 'player',
            'name' => 'Player 2 Character',
            'active' => true,
        ]);

        app(StorageProvisioningService::class)->provisionDefaults($this->player2);
        app(\App\Services\CurrencyService::class)->grantStartingGold($this->player2);
    }

    public function test_asymmetric_full_inventory_trade_must_not_complete(): void
    {
        $p1Sword = $this->fillPlayer1FullInventory();
        $p2IronSword = $this->fillPlayer2FullInventory();

        $trade = $this->tradeService->createTrade($this->player1, $this->player2);

        $this->tradeService->addItemToTrade($this->player1, $trade, $p1Sword->uuid);
        $this->tradeService->addItemToTrade($this->player2, $trade, $p2IronSword->uuid);
        $this->tradeService->addResourceToTrade($this->player2, $trade, 'wood', 20);

        $this->tradeService->confirmTrade($this->player1, $trade);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Недостаточно места в инвентаре для завершения обмена');
        $this->tradeService->confirmTrade($this->player2, $trade);

        $trade->refresh();
        $this->assertEquals('pending', $trade->status);
        $this->assertTrue($trade->initiator_accepted);
        $this->assertFalse($trade->partner_accepted);

        $p1Sword->refresh();
        $p2IronSword->refresh();
        $this->assertEquals('trade', $this->slotStorageType($p1Sword->slot_uuid));
        $this->assertEquals('trade', $this->slotStorageType($p2IronSword->slot_uuid));
    }

    private function fillPlayer1FullInventory(): Item
    {
        $slots = $this->gridSlots($this->player1);
        $this->assertCount(36, $slots);

        Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $slots[0]->uuid,
            'recipe_slug' => 'wood',
            'template_slug' => 'wood',
            'slot_type' => 'wood',
            'max_stack' => 20,
            'quantity' => 15,
        ]);

        for ($i = 1; $i <= 34; $i++) {
            Item::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $slots[$i]->uuid,
                'recipe_slug' => 'craft_wooden_sword',
                'template_slug' => 'recipe_wooden_sword',
                'stage' => 'blueprint',
                'slot_type' => 'blueprint',
                'durability' => 100,
            ]);
        }

        return Item::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $slots[35]->uuid,
            'recipe_slug' => 'craft_wooden_sword',
            'template_slug' => 'wooden_sword',
            'stage' => 'item',
            'slot_type' => 'equipment_weapon',
            'durability' => 100,
        ]);
    }

    private function fillPlayer2FullInventory(): Item
    {
        $slots = $this->gridSlots($this->player2);
        $this->assertCount(36, $slots);

        Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $slots[0]->uuid,
            'recipe_slug' => 'iron_ore',
            'template_slug' => 'iron_ore',
            'slot_type' => 'ore',
            'max_stack' => 20,
            'quantity' => 20,
        ]);

        for ($i = 1; $i <= 33; $i++) {
            Resources::create([
                'uuid' => Str::uuid()->toString(),
                'slot_uuid' => $slots[$i]->uuid,
                'recipe_slug' => 'wood',
                'template_slug' => 'wood',
                'slot_type' => 'wood',
                'max_stack' => 20,
                'quantity' => 20,
            ]);
        }

        Item::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $slots[34]->uuid,
            'recipe_slug' => 'craft_iron_sword',
            'template_slug' => 'iron_sword',
            'stage' => 'item',
            'slot_type' => 'equipment_weapon',
            'durability' => 100,
        ]);

        return Item::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $slots[35]->uuid,
            'recipe_slug' => 'craft_iron_sword',
            'template_slug' => 'iron_sword',
            'stage' => 'item',
            'slot_type' => 'equipment_weapon',
            'durability' => 100,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Slot>
     */
    private function gridSlots(Character $character)
    {
        $inventory = $character->storages()->where('storage_type', 'inventory')->firstOrFail();

        return app(SpecialSlotService::class)->getGridSlots($inventory)->values();
    }

    private function slotStorageType(string $slotUuid): ?string
    {
        $slot = Slot::where('uuid', $slotUuid)->first();
        if (!$slot) {
            return null;
        }

        return \App\Models\Storage::where('uuid', $slot->storage_uuid)->value('storage_type');
    }
}
