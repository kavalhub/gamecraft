<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\DuelOffer;
use App\Models\GameEvent;
use App\Models\User;
use App\Services\StorageProvisioningService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuelApiTest extends TestCase
{
    use RefreshDatabase;

    private Character $player1;
    private Character $player2;
    private string $token1;
    private string $token2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGameDatabase();

        $user1 = User::where('email', 'test@example.com')->first();
        $this->player1 = $user1->characters()->where('character_type', 'player')->first();
        $this->token1 = $user1->createToken('game')->plainTextToken;

        $user2 = User::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Player 2',
            'email' => 'player2@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->player2 = Character::create([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_uuid' => $user2->uuid,
            'character_type' => 'player',
            'name' => 'Дуэлянт',
            'active' => true,
        ]);
        app(StorageProvisioningService::class)->provisionDefaults($this->player2);
        $this->token2 = $user2->createToken('game')->plainTextToken;
    }

    public function test_challenge_creates_pending_duel(): void
    {
        $response = $this->withToken($this->token1)
            ->postJson("/api/duel/{$this->player1->uuid}/challenge", [
                'opponent_uuid' => $this->player2->uuid,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('duel.status', 'pending')
            ->assertJsonPath('duel.is_challenger', true);

        $this->assertDatabaseHas('duel_offers', [
            'challenger_uuid' => $this->player1->uuid,
            'opponent_uuid' => $this->player2->uuid,
            'status' => 'pending',
        ]);
    }

    public function test_accept_resolves_duel_with_combat_log(): void
    {
        $challenge = $this->withToken($this->token1)
            ->postJson("/api/duel/{$this->player1->uuid}/challenge", [
                'opponent_uuid' => $this->player2->uuid,
            ]);

        $duelUuid = $challenge->json('duel.uuid');

        $accept = $this->withToken($this->token2)
            ->postJson("/api/duel/{$this->player2->uuid}/accept", [
                'duel_uuid' => $duelUuid,
            ]);

        $accept->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('mode', 'duel')
            ->assertJsonStructure(['combat_log', 'combat_ui', 'outcome', 'correlation_uuid']);

        $this->assertDatabaseHas('duel_offers', [
            'uuid' => $duelUuid,
            'status' => 'resolved',
        ]);

        $this->assertTrue(
            GameEvent::where('event_type', 'duel.resolved')
                ->where('actor_uuid', $this->player1->uuid)
                ->exists()
        );
        $this->assertTrue(
            GameEvent::where('event_type', 'duel.resolved')
                ->where('actor_uuid', $this->player2->uuid)
                ->exists()
        );

        $challengerEvent = GameEvent::where('event_type', 'duel.resolved')
            ->where('actor_uuid', $this->player1->uuid)
            ->firstOrFail();
        $opponentEvent = GameEvent::where('event_type', 'duel.resolved')
            ->where('actor_uuid', $this->player2->uuid)
            ->firstOrFail();

        $this->assertSame($this->player1->uuid, $challengerEvent->payload['viewer_uuid']);
        $this->assertSame($this->player2->uuid, $opponentEvent->payload['viewer_uuid']);
        $this->assertNotSame($challengerEvent->payload['outcome'], $opponentEvent->payload['outcome']);

        $player1Events = app(\App\Services\EventQueryService::class)->getEventsAfter($this->player1->uuid, 0);
        $player2Events = app(\App\Services\EventQueryService::class)->getEventsAfter($this->player2->uuid, 0);

        $this->assertCount(1, $player1Events->where('event_type', 'duel.resolved'));
        $this->assertCount(1, $player2Events->where('event_type', 'duel.resolved'));
    }

    public function test_decline_cancels_duel(): void
    {
        $challenge = $this->withToken($this->token1)
            ->postJson("/api/duel/{$this->player1->uuid}/challenge", [
                'opponent_uuid' => $this->player2->uuid,
            ]);

        $duelUuid = $challenge->json('duel.uuid');

        $decline = $this->withToken($this->token2)
            ->postJson("/api/duel/{$this->player2->uuid}/decline", [
                'duel_uuid' => $duelUuid,
            ]);

        $decline->assertOk()->assertJsonPath('duel.status', 'cancelled');

        $this->assertSame('cancelled', DuelOffer::where('uuid', $duelUuid)->value('status'));
    }
}
