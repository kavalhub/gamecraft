<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Item;
use App\Models\Slot;
use App\Models\User;
use App\Services\AuctionService;
use App\Services\InventoryService;
use App\Services\WorldStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorldStorageService $worldStorage;
    private InventoryService $inventoryService;
    private AuctionService $auctionService;
    private Character $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->worldStorage = app(WorldStorageService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->auctionService = app(AuctionService::class);

        $user = User::where('email', 'test@example.com')->first();
        $this->buyer = $user->characters()->where('character_type', 'player')->first();
    }

    public function test_drop_and_claim_reuses_same_item_uuid(): void
    {
        $item = $this->inventoryService->addItem(
            $this->buyer,
            'quest_first_wooden_sword',
            'quest_item',
            null,
            'quest_item_stub'
        );

        $this->worldStorage->dropFromInventory($this->buyer, $item->uuid);

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $otherCharacter = Character::create([
            'user_uuid' => $otherUser->uuid,
            'character_type' => 'player',
            'name' => 'Other',
            'active' => true,
        ]);
        app(\App\Services\StorageProvisioningService::class)->provisionDefaults($otherCharacter);

        $claimed = $this->worldStorage->claimItem($otherCharacter, 'quest_first_wooden_sword', 'quest_item');

        $this->assertNotNull($claimed);
        $this->assertEquals($item->uuid, $claimed->uuid);
    }

    public function test_auction_claims_from_world_before_creating_new(): void
    {
        $item = $this->inventoryService->addItem(
            $this->buyer,
            'recipe_wooden_sword',
            'blueprint',
            null,
            'craft_wooden_sword'
        );
        $this->worldStorage->dropFromInventory($this->buyer, $item->uuid);

        $lot = \App\Models\AuctionLot::where('is_infinite', true)
            ->where('template_slug', 'recipe_wooden_sword')
            ->first();

        $result = $this->auctionService->buyLot($this->buyer, $lot->uuid, 1);

        $this->assertEquals($item->uuid, $result['result']->uuid);
    }
}
