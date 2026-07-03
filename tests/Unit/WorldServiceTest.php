<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\User;
use App\Services\WorldService;
use App\Services\ZoneCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldServiceTest extends TestCase
{
    use RefreshDatabase;

    private Character $player;
    private WorldService $worldService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGameDatabase();

        $user = User::where('email', 'test@example.com')->first();
        $this->player = $user->characters()->where('character_type', 'player')->first();
        $this->worldService = app(WorldService::class);
    }

    public function test_ensure_spawn_places_player_in_craft_city(): void
    {
        $state = $this->worldService->ensureSpawn($this->player);

        $this->assertSame('craft_city', $state->zone_slug);
        $this->assertSame(0.0, $state->x);
        $this->assertSame(0.0, $state->z);
    }

    public function test_move_updates_position_in_bounds(): void
    {
        $this->worldService->ensureSpawn($this->player);

        $result = $this->worldService->move($this->player, 5.0, 0.0, 5.0, 90.0);

        $this->assertSame(5.0, $result['state']['x']);
        $this->assertSame(5.0, $result['state']['z']);
        $this->assertSame(90.0, $result['state']['rotation_y']);
    }

    public function test_move_rejects_out_of_bounds(): void
    {
        $this->worldService->ensureSpawn($this->player);

        $this->expectException(\RuntimeException::class);
        $this->worldService->move($this->player, 999.0, 0.0, 0.0);
    }

    public function test_move_rejects_blocked_tile(): void
    {
        $catalog = app(\App\Services\ZoneTileCatalog::class);
        $catalog->save('craft_city', [
            'cells' => [
                '1,0' => ['walkable' => false],
            ],
        ]);

        try {
            $this->worldService->ensureSpawn($this->player);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Сюда нельзя пройти');
            $this->worldService->move($this->player, 1.0, 0.0, 0.0);
        } finally {
            $path = base_path('content/zone_tiles/craft_city.json');
            if (\Illuminate\Support\Facades\File::exists($path)) {
                \Illuminate\Support\Facades\File::delete($path);
            }
            $catalog->forget('craft_city');
        }
    }

    public function test_nearby_lists_characters_in_same_zone(): void
    {
        $this->worldService->ensureSpawn($this->player);
        $this->worldService->move($this->player, 0.0, 0.0, 0.0);

        $otherUser = User::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'other_bot',
            'email' => 'other_bot@game.local',
            'password' => bcrypt('secret'),
        ]);
        $other = Character::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_uuid' => $otherUser->uuid,
            'character_type' => 'player',
            'name' => 'other_bot',
            'avatar' => 'mage',
            'active' => true,
        ]);

        $this->worldService->ensureSpawn($other);
        $this->worldService->move($other, 3.0, 0.0, 4.0);

        $nearby = $this->worldService->nearby($this->player, 30.0);

        $this->assertCount(1, $nearby);
        $this->assertSame($other->uuid, $nearby[0]['character_uuid']);
    }

    public function test_enter_zone_moves_to_new_zone(): void
    {
        $this->worldService->ensureSpawn($this->player);

        $result = $this->worldService->enterZone($this->player, 'forest_edge', 'default');

        $this->assertSame('forest_edge', $result['state']['zone_slug']);
    }

    public function test_interact_opens_window_when_in_range(): void
    {
        $this->worldService->ensureSpawn($this->player);
        $this->worldService->move($this->player, 10.0, 0.0, -5.0);

        $result = $this->worldService->interact($this->player, 'auction_npc');

        $this->assertTrue($result['success']);
        $this->assertSame('open_window', $result['action']);
        $this->assertSame('auction', $result['window']);
    }

    public function test_interact_rejects_when_too_far(): void
    {
        $this->worldService->ensureSpawn($this->player);

        $this->expectException(\RuntimeException::class);
        $this->worldService->interact($this->player, 'auction_npc');
    }

    public function test_portal_transition_on_move(): void
    {
        $this->worldService->ensureSpawn($this->player);

        $result = null;
        foreach ([12.0, 24.0, 36.0, 48.0] as $z) {
            $result = $this->worldService->move($this->player, 0.0, 0.0, $z);
        }

        $this->assertNotNull($result);
        $this->assertSame('forest_edge', $result['state']['zone_slug']);
        $this->assertNotNull($result['portal_used']);
        $this->assertSame('gate_north', $result['portal_used']['id']);
    }

    public function test_step_moves_player(): void
    {
        $this->worldService->ensureSpawn($this->player);

        $result = $this->worldService->step($this->player, 'north');

        $this->assertGreaterThan(0, $result['state']['z']);
    }

    public function test_get_context_includes_nearby_interactables(): void
    {
        $this->worldService->ensureSpawn($this->player);
        $this->worldService->move($this->player, 10.0, 0.0, -5.0);

        $context = $this->worldService->getContext($this->player);

        $this->assertNotEmpty($context['nearby_interactables']);
        $this->assertSame('auction_npc', $context['nearby_interactables'][0]['id']);
    }

    public function test_zone_catalog_loads_craft_city(): void
    {
        $zone = app(ZoneCatalog::class)->get('craft_city');

        $this->assertSame('Крафт-Сити', $zone['name']);
        $this->assertNotEmpty($zone['interactables']);
    }
}
