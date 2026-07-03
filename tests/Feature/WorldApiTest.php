<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorldApiTest extends TestCase
{
    use RefreshDatabase;

    private Character $player;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGameDatabase();

        $user = User::where('email', 'test@example.com')->first();
        $this->player = $user->characters()->where('character_type', 'player')->first();
        $this->token = $user->createToken('game')->plainTextToken;
    }

    public function test_get_world_state_spawns_player(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/world/{$this->player->uuid}");

        $response->assertOk()
            ->assertJsonPath('state.zone_slug', 'craft_city')
            ->assertJsonPath('state.x', 0)
            ->assertJsonPath('state.z', 0);
    }

    public function test_post_move_updates_position(): void
    {
        $this->withToken($this->token)
            ->postJson("/api/world/{$this->player->uuid}/move", [
                'x' => 2,
                'y' => 0,
                'z' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('state.x', 2)
            ->assertJsonPath('state.z', 3);
    }

    public function test_context_and_interact(): void
    {
        $world = app(\App\Services\WorldService::class);
        $world->ensureSpawn($this->player);
        $world->move($this->player, 10, 0, -5);

        $this->withToken($this->token)
            ->getJson("/api/world/{$this->player->uuid}/context")
            ->assertOk()
            ->assertJsonFragment(['id' => 'auction_npc']);

        $this->withToken($this->token)
            ->postJson("/api/world/{$this->player->uuid}/interact", [
                'target_id' => 'auction_npc',
            ])
            ->assertOk()
            ->assertJsonPath('window', 'auction');
    }

    public function test_step_and_portal_roundtrip(): void
    {
        $world = app(\App\Services\WorldService::class);
        $world->ensureSpawn($this->player);

        foreach ([12, 24, 36, 48] as $z) {
            $this->withToken($this->token)
                ->postJson("/api/world/{$this->player->uuid}/move", [
                    'x' => 0,
                    'y' => 0,
                    'z' => $z,
                ])
                ->assertOk();
        }

        $this->withToken($this->token)
            ->getJson("/api/world/{$this->player->uuid}")
            ->assertOk()
            ->assertJsonPath('state.zone_slug', 'forest_edge');

        foreach ([-27, -15, -3, 9, 21, 33, 38] as $z) {
            $this->withToken($this->token)
                ->postJson("/api/world/{$this->player->uuid}/move", [
                    'x' => 0,
                    'y' => 0,
                    'z' => $z,
                ])
                ->assertOk();
        }

        $this->withToken($this->token)
            ->getJson("/api/world/{$this->player->uuid}")
            ->assertOk()
            ->assertJsonPath('state.zone_slug', 'craft_city');
    }

    public function test_move_and_nearby(): void
    {
        $otherUser = User::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'walker',
            'email' => 'walker@game.local',
            'password' => bcrypt('secret'),
        ]);
        $other = Character::create([
            'uuid' => Str::uuid()->toString(),
            'user_uuid' => $otherUser->uuid,
            'character_type' => 'player',
            'name' => 'walker',
            'avatar' => 'ranger',
            'active' => true,
        ]);

        $world = app(\App\Services\WorldService::class);
        $world->ensureSpawn($this->player);
        $world->move($this->player, 0, 0, 0);
        $world->ensureSpawn($other);
        $world->move($other, 2, 0, 2);

        $this->withToken($this->token)
            ->getJson("/api/world/{$this->player->uuid}/nearby?radius=30")
            ->assertOk()
            ->assertJsonFragment(['character_uuid' => $other->uuid]);
    }

    public function test_zones_catalog_is_available(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/world/zones')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'craft_city']);
    }

    public function test_new_character_gets_default_window_positions(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/register', [
                'username' => 'layout_test_' . Str::random(4),
                'password' => 'password123',
            ]);

        $response->assertCreated();
        $characterUuid = $response->json('character_uuid');

        $settings = $this->withToken($response->json('token'))
            ->getJson("/api/settings/{$characterUuid}")
            ->assertOk()
            ->json('settings.window_positions');

        $this->assertSame(672, $settings['journal']['top']);
        $this->assertSame(12, $settings['journal']['left']);
        $this->assertSame(1285, $settings['inventory']['left']);
    }
}
