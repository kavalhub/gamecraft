<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\CharacterHeartbeat;
use App\Models\GameEvent;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private PresenceService $presenceService;

  protected function setUp(): void
    {
        parent::setUp();
        $this->presenceService = app(PresenceService::class);
    }

    public function test_first_ping_emits_presence_changed_event(): void
    {
        $character = $this->createPlayer('scout');

        $this->presenceService->markOnline($character->uuid);

        $this->assertDatabaseHas('character_heartbeats', [
            'character_uuid' => $character->uuid,
        ]);

        $event = GameEvent::where('event_type', 'presence.changed')->first();
        $this->assertNotNull($event);
        $this->assertSame('presence', $event->aggregate_type);
        $this->assertSame('global', $event->aggregate_uuid);
        $this->assertSame('online', $event->payload['action']);
        $this->assertSame($character->uuid, $event->payload['character_uuid']);
    }

    public function test_repeat_ping_within_threshold_does_not_duplicate_event(): void
    {
        $character = $this->createPlayer('ranger');

        $this->presenceService->markOnline($character->uuid);
        $this->presenceService->markOnline($character->uuid);

        $this->assertSame(1, GameEvent::where('event_type', 'presence.changed')->count());
    }

    public function test_ping_after_offline_gap_emits_new_event(): void
    {
        $character = $this->createPlayer('mage');

        CharacterHeartbeat::create([
            'character_uuid' => $character->uuid,
            'last_seen_at' => now()->subMinutes(6),
        ]);

        $this->presenceService->markOnline($character->uuid);

        $this->assertSame(1, GameEvent::where('event_type', 'presence.changed')->count());
    }

    private function createPlayer(string $name): Character
    {
        $user = User::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => $name,
            'email' => $name . '@test.local',
            'password' => bcrypt('password'),
        ]);

        return Character::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_uuid' => $user->uuid,
            'character_type' => 'player',
            'name' => $name,
            'active' => true,
        ]);
    }
}
