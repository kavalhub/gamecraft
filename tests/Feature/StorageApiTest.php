<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use App\Services\CraftingService;
use App\Services\InventoryService;
use App\Services\StorageProvisioningService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WorkbenchHelper;
use Tests\TestCase;

class StorageApiTest extends TestCase
{
    use RefreshDatabase;

    private Character $player;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'test@example.com')->first();
        $this->player = $user->characters()->where('character_type', 'player')->first();
        $this->token = $user->createToken('game')->plainTextToken;
    }

    public function test_get_storage_returns_36_inventory_slots(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/storage/{$this->player->uuid}?include=inventory");

        $response->assertOk()
            ->assertJsonPath('character_uuid', $this->player->uuid);

        $storages = $response->json('storages');
        $inventory = collect($storages)->firstWhere('storage_type', 'inventory');

        $this->assertNotNull($inventory);
        $this->assertEquals(36, count($inventory['grid_slots'] ?? $inventory['slots']));
        $this->assertEquals(4, $inventory['cols']);
        $goldInGrid = collect($inventory['grid_slots'] ?? $inventory['slots'])
            ->contains(fn ($s) => ($s['resource']['template_slug'] ?? null) === 'gold');
        $this->assertFalse($goldInGrid);
        $response->assertJsonPath('gold', 1000);
    }

    public function test_storage_shows_locked_item_in_source_slot(): void
    {
        $inventoryService = app(InventoryService::class);
        $provisioning = app(StorageProvisioningService::class);
        $inventoryService->addResource($this->player, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

        $provisioning->ensureTradeStorage($this->player);
        $tempSlot = $provisioning->findFreeTradeTemporarySlot($this->player);
        $item->update(['temporary_slot_uuid' => $tempSlot->uuid]);

        $response = $this->withToken($this->token)
            ->getJson("/api/storage/{$this->player->uuid}?include=inventory");

        $response->assertOk();
        $inventory = collect($response->json('storages'))->firstWhere('storage_type', 'inventory');
        $occupiedSlot = collect($inventory['grid_slots'] ?? $inventory['slots'])
            ->first(fn ($s) => ($s['item']['uuid'] ?? null) === $item->uuid);

        $this->assertNotNull($occupiedSlot);
        $this->assertTrue($occupiedSlot['item']['locked']);
    }

    public function test_craft_overlay_shows_unlocked_item(): void
    {
        $blueprint = app(CraftingService::class)->createBlueprint($this->player, 'craft_wooden_sword');
        WorkbenchHelper::placeOnBlueprintSlot($this->player, $blueprint);

        $inventoryResponse = $this->withToken($this->token)
            ->getJson("/api/storage/{$this->player->uuid}?include=inventory,craft");

        $inventoryResponse->assertOk();
        $inventory = collect($inventoryResponse->json('storages'))->firstWhere('storage_type', 'inventory');
        $invSlot = collect($inventory['grid_slots'] ?? $inventory['slots'])
            ->first(fn ($s) => ($s['item']['uuid'] ?? null) === $blueprint->uuid);
        $this->assertNotNull($invSlot);
        $this->assertTrue($invSlot['item']['locked']);

        $craft = collect($inventoryResponse->json('storages'))->firstWhere('storage_type', 'craft');
        $centerSlot = collect($craft['slots'] ?? [])
            ->first(fn ($s) => ($s['slot_type'] ?? null) === 'craft_center');
        $this->assertNotNull($centerSlot);
        $this->assertFalse($centerSlot['item']['locked']);
    }

    public function test_move_item_between_slots_via_api(): void
    {
        app(InventoryService::class)->addResource($this->player, 'wood', 10);

        $layout = $this->withToken($this->token)
            ->getJson("/api/storage/{$this->player->uuid}?include=inventory")
            ->json();

        $inventory = collect($layout['storages'])->firstWhere('storage_type', 'inventory');
        $woodSlot = collect($inventory['slots'])->first(fn ($s) => ($s['resource']['template_slug'] ?? null) === 'wood');
        $emptySlot = collect($inventory['slots'])->first(fn ($s) => !$s['item'] && !$s['resource']);

        $this->assertNotNull($woodSlot);
        $this->assertNotNull($emptySlot);

        $response = $this->withToken($this->token)
            ->postJson("/api/storage/{$this->player->uuid}/move", [
                'from_slot_uuid' => $woodSlot['uuid'],
                'to_slot_uuid' => $emptySlot['uuid'],
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $updated = collect($response->json('layout.storages'))
            ->firstWhere('storage_type', 'inventory');

        $target = collect($updated['slots'])->firstWhere('uuid', $emptySlot['uuid']);
        $this->assertEquals('wood', $target['resource']['template_slug'] ?? null);
    }

    public function test_trade_storage_granted_on_include(): void
    {
        $user2 = User::create([
            'name' => 'Trader',
            'email' => 'trader@example.com',
            'password' => bcrypt('password'),
        ]);
        $partner = Character::create([
            'user_uuid' => $user2->uuid,
            'character_type' => 'player',
            'name' => 'Trader',
            'active' => true,
        ]);
        app(StorageProvisioningService::class)->provisionDefaults($partner);

        app(\App\Services\TradeService::class)->createTrade($this->player, $partner);

        $response = $this->withToken($this->token)
            ->getJson("/api/storage/{$this->player->uuid}?include=inventory,trade");

        $response->assertOk();
        $this->assertCount(20, $response->json('my_trade_slots.slots'));
        $this->assertEquals(4, $response->json('my_trade_slots.cols'));
    }
}
