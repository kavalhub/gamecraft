<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Item;
use App\Models\TradeItem;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\Storage\RestrictedInventoryHubPolicy;
use App\Services\Storage\StorageTransferPolicy;
use App\Services\StorageMoveService;
use App\Services\StorageProvisioningService;
use App\Services\TradeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\WorkbenchHelper;
use Tests\TestCase;

class StorageTransferPolicyTest extends TestCase
{
    use RefreshDatabase;

    private StorageMoveService $moveService;
    private InventoryService $inventoryService;
    private StorageProvisioningService $provisioningService;
    private Character $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGameDatabase();

        $this->moveService = app(StorageMoveService::class);
        $this->inventoryService = app(InventoryService::class);
        $this->provisioningService = app(StorageProvisioningService::class);

        $user = User::where('email', 'test@example.com')->first();
        $this->player = $user->characters()->where('character_type', 'player')->first();
    }

    public function test_unrestricted_policy_allows_bank_to_equipment_move(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

        $bank = $this->player->storages()->where('storage_type', 'bank')->firstOrFail();
        $bankSlot = $bank->slots()->whereNull('slot_type')->orderBy('id')->firstOrFail();
        $item->update(['slot_uuid' => $bankSlot->uuid]);

        $equipment = $this->player->storages()->where('storage_type', 'equipment')->firstOrFail();
        $weaponSlot = $equipment->slots()->where('slot_type', 'equipment_weapon')->firstOrFail();

        $this->moveService->move($this->player, $bankSlot->uuid, $weaponSlot->uuid);

        $item->refresh();
        $this->assertEquals($weaponSlot->uuid, $item->slot_uuid);
    }

    public function test_restricted_policy_blocks_bank_to_equipment_move(): void
    {
        $this->app->instance(StorageTransferPolicy::class, new RestrictedInventoryHubPolicy());
        $moveService = app(StorageMoveService::class);

        $this->inventoryService->addResource($this->player, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

        $bank = $this->player->storages()->where('storage_type', 'bank')->firstOrFail();
        $bankSlot = $bank->slots()->whereNull('slot_type')->orderBy('id')->firstOrFail();
        $item->update(['slot_uuid' => $bankSlot->uuid]);

        $equipment = $this->player->storages()->where('storage_type', 'equipment')->firstOrFail();
        $weaponSlot = $equipment->slots()->where('slot_type', 'equipment_weapon')->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Личный банк доступен только для инвентаря');
        $moveService->move($this->player, $bankSlot->uuid, $weaponSlot->uuid);
    }

    public function test_bank_to_trade_move_creates_trade_item(): void
    {
        $this->inventoryService->addResource($this->player, 'wood', 10);
        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

        $bank = $this->player->storages()->where('storage_type', 'bank')->firstOrFail();
        $bankSlot = $bank->slots()->whereNull('slot_type')->orderBy('id')->firstOrFail();
        $originSlotUuid = $bankSlot->uuid;
        $item->update(['slot_uuid' => $originSlotUuid]);

        $trade = app(TradeService::class)->createTrade($this->player, $this->createSecondPlayer());
        $this->provisioningService->ensureTradeStorage($this->player);
        $tradeSlot = $this->provisioningService->findFreeTradeSlot($this->player);

        $this->moveService->move($this->player, $originSlotUuid, $tradeSlot->uuid);

        $item->refresh();
        $this->assertEquals($tradeSlot->uuid, $item->slot_uuid);
        $this->assertTrue(
            TradeItem::where('trade_uuid', $trade->uuid)
                ->where('item_uuid', $item->uuid)
                ->where('origin_slot_uuid', $originSlotUuid)
                ->exists()
        );
    }

    private function createSecondPlayer(): Character
    {
        $user2 = User::create([
            'name' => 'Other',
            'email' => 'other-' . \Illuminate\Support\Str::uuid()->toString() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $other = Character::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_uuid' => $user2->uuid,
            'character_type' => 'player',
            'name' => 'Other',
            'active' => true,
        ]);
        $this->provisioningService->provisionDefaults($other);

        return $other;
    }
}
