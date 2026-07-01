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
use Tests\Support\WorkbenchHelper;
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

        $item = WorkbenchHelper::craftWoodenSwordFromInventory($this->player);

        $equipment = $this->player->storages()->where('storage_type', 'equipment')->first();
        $weaponSlot = $equipment->slots()->where('slot_type', 'equipment_weapon')->first();
        $item->update(['slot_uuid' => $weaponSlot->uuid]);

        $profile = $this->service->ensureFor($this->player);

        $this->assertGreaterThan(0, $profile['total']['damage'] ?? 0);
    }

    public function test_level_from_experience_uses_thresholds(): void
    {
        $this->assertSame(1, $this->service->levelFromExperience(0));
        $this->assertSame(1, $this->service->levelFromExperience(9));
        $this->assertSame(2, $this->service->levelFromExperience(10));
        $this->assertSame(2, $this->service->levelFromExperience(49));
        $this->assertSame(3, $this->service->levelFromExperience(50));
        $this->assertSame(4, $this->service->levelFromExperience(150));
    }

    public function test_experience_progress_for_profile(): void
    {
        app(\App\Services\ExperienceService::class)->credit($this->player, 25, 'test', []);
        $profile = $this->service->ensureFor($this->player);

        $this->assertSame(2, $profile['level']);
        $this->assertSame(25, $profile['experience']);
        $this->assertSame(10, $profile['experience_progress']['level_min']);
        $this->assertSame(50, $profile['experience_progress']['level_max']);
    }
}
