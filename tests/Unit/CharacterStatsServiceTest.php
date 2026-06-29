<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\User;
use App\Services\CharacterStatsService;
use App\Services\InventoryService;
use App\Services\StorageProvisioningService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private Character $player;
    private CharacterStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'test@example.com')->first();
        $this->player = $user->characters()->where('character_type', 'player')->first();
        $this->service = app(CharacterStatsService::class);
    }

    public function test_ensure_for_creates_default_stats(): void
    {
        $profile = $this->service->ensureFor($this->player);

        $this->assertSame(1, $profile['level']);
        $this->assertSame(10, $profile['base']['strength']);
        $this->assertSame(50, $profile['base']['health']);
        $this->assertSame(10, $profile['total']['strength']);
        $this->assertSame(50, $profile['total']['health']);
    }

    public function test_equipment_item_stats_add_to_total(): void
    {
        $inventory = app(InventoryService::class);

        $inventory->addResource($this->player, 'wood', 10);

        $blueprint = app(\App\Services\CraftingService::class)->createBlueprint(
            $this->player,
            'craft_wooden_sword'
        );

        $item = app(\App\Services\CraftingService::class)->craftItem(
            $this->player,
            'craft_wooden_sword',
            $blueprint->uuid
        );

        $equipment = $this->player->storages()->where('storage_type', 'equipment')->first();
        $weaponSlot = $equipment->slots()->where('slot_type', 'equipment_weapon')->first();
        $item->update(['slot_uuid' => $weaponSlot->uuid]);

        $profile = $this->service->ensureFor($this->player);

        $this->assertGreaterThan(0, $profile['total']['damage'] ?? 0);
    }
}
