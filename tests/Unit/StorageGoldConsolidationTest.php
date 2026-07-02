<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Resources;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\User;
use App\Services\StorageMoveService;
use App\Services\StorageProvisioningService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorageGoldConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidates_duplicate_gold_in_same_slot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'test@example.com')->first();
        $character = $user->characters()->where('character_type', 'player')->first();
        $inventory = $character->storages()->where('storage_type', 'inventory')->first();
        $goldSlot = app(\App\Services\SpecialSlotService::class)->getGoldSlot($inventory)
            ?? $inventory->slots()->where('slot_type', 'gold')->first()
            ?? $inventory->slots()->first();

        Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $goldSlot->uuid,
            'recipe_slug' => 'gold',
            'template_slug' => 'gold',
            'slot_type' => 'gold',
            'max_stack' => null,
            'quantity' => 1000,
        ]);

        $provisioning = app(StorageProvisioningService::class);
        $provisioning->consolidateInventoryResources($character);

        $this->assertEquals(1, Resources::where('slot_uuid', $goldSlot->uuid)->where('template_slug', 'gold')->count());
        $this->assertEquals(2000, $provisioning->getInventoryGoldQuantity($character));
    }

    public function test_relocates_gold_from_grid_to_special_slot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'test@example.com')->first();
        $character = $user->characters()->where('character_type', 'player')->first();
        $inventory = $character->storages()->where('storage_type', 'inventory')->first();
        $special = app(\App\Services\SpecialSlotService::class);
        $goldSlot = $special->getGoldSlot($inventory);
        $gridSlot = $special->getGridSlots($inventory)->first();

        Resources::create([
            'uuid' => Str::uuid()->toString(),
            'slot_uuid' => $gridSlot->uuid,
            'recipe_slug' => 'gold',
            'template_slug' => 'gold',
            'slot_type' => 'gold',
            'max_stack' => null,
            'quantity' => 500,
        ]);

        app(StorageProvisioningService::class)->consolidateInventoryResources($character);

        $this->assertEquals(0, Resources::where('slot_uuid', $gridSlot->uuid)->where('template_slug', 'gold')->count());
        $this->assertEquals(1500, app(StorageProvisioningService::class)->getInventoryGoldQuantity($character));
    }

    public function test_grant_starting_gold_does_not_duplicate(): void
    {
        $user = User::create([
            'name' => 'GoldUser',
            'email' => 'golduser@example.com',
            'password' => bcrypt('password'),
        ]);
        $character = Character::create([
            'user_uuid' => $user->uuid,
            'character_type' => 'player',
            'name' => 'GoldUser',
            'active' => true,
        ]);

        $provisioning = app(StorageProvisioningService::class);
        $currency = app(\App\Services\CurrencyService::class);
        $provisioning->provisionDefaults($character);
        $currency->grantStartingGold($character);
        $currency->grantStartingGold($character);

        $this->assertEquals(1000, $provisioning->getInventoryGoldQuantity($character));
        $this->assertEquals(
            1,
            Resources::where('template_slug', 'gold')
                ->whereIn('slot_uuid', $character->storages()->where('storage_type', 'inventory')->first()->slots()->pluck('uuid'))
                ->count()
        );
    }

    public function test_grant_starting_gold_does_not_restore_after_spending(): void
    {
        $user = User::create([
            'name' => 'SpentGold',
            'email' => 'spentgold@example.com',
            'password' => bcrypt('password'),
        ]);
        $character = Character::create([
            'user_uuid' => $user->uuid,
            'character_type' => 'player',
            'name' => 'SpentGold',
            'active' => true,
        ]);

        $provisioning = app(StorageProvisioningService::class);
        $currency = app(\App\Services\CurrencyService::class);
        $provisioning->provisionDefaults($character);
        $currency->grantStartingGold($character);
        $character->refresh();

        $this->assertEquals(1000, $provisioning->getInventoryGoldQuantity($character));

        $currency->debit($character, 800, 'test', ['reason' => 'spend']);

        $currency->grantStartingGold($character);
        $character->refresh();

        $this->assertEquals(200, $provisioning->getInventoryGoldQuantity($character));
    }

    public function test_cannot_drag_gold_from_special_slot_to_grid(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'test@example.com')->first();
        $character = $user->characters()->where('character_type', 'player')->first();
        $inventory = $character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $special = app(\App\Services\SpecialSlotService::class);
        $goldSlot = $special->getGoldSlot($inventory);
        $gridSlot = $special->getGridSlots($inventory)->first();

        $moveService = app(StorageMoveService::class);

        $result = $moveService->move($character, $goldSlot->uuid, $gridSlot->uuid);

        $this->assertTrue($result['noop'] ?? false);
    }

    public function test_cannot_duplicate_gold_by_moving_from_special_slot_to_same_slot_via_grid(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'test@example.com')->first();
        $character = $user->characters()->where('character_type', 'player')->first();
        $inventory = $character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $special = app(\App\Services\SpecialSlotService::class);
        $goldSlot = $special->getGoldSlot($inventory);
        $gridSlot = $special->getGridSlots($inventory)->first();

        $before = app(StorageProvisioningService::class)->getInventoryGoldQuantity($character);

        $moveService = app(StorageMoveService::class);

        $result = $moveService->move($character, $goldSlot->uuid, $gridSlot->uuid);

        $this->assertTrue($result['noop'] ?? false);

        $after = app(StorageProvisioningService::class)->getInventoryGoldQuantity($character);
        $this->assertEquals($before, $after);
    }
}
