<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Character;
use App\Models\User;
use App\Services\EventQueryService;
use App\Services\EventStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private Character $player1;
    private Character $player2;
    private EventQueryService $service;
    private EventStore $eventStore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $user1 = User::where('email', 'test@example.com')->first();
        $this->player1 = $user1->characters()->where('character_type', 'player')->first();

        $user2 = User::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Player 2',
            'email' => 'player2@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->player2 = Character::create([
            'uuid' => Str::uuid()->toString(),
            'user_uuid' => $user2->uuid,
            'character_type' => 'player',
            'name' => 'Дуэлянт',
            'active' => true,
        ]);

        $this->service = app(EventQueryService::class);
        $this->eventStore = app(EventStore::class);
    }

    public function test_duel_resolved_events_are_not_shared_between_participants(): void
    {
        $duelUuid = Str::uuid()->toString();
        $correlationUuid = Str::uuid()->toString();
        $afterId = (int) \App\Models\GameEvent::query()->max('id');

        $this->eventStore->record(
            'duel.resolved',
            'duel',
            $duelUuid,
            [
                'mode' => 'duel',
                'viewer_uuid' => $this->player1->uuid,
                'outcome' => 'won',
            ],
            $this->player1->uuid,
            $correlationUuid,
        );

        $this->eventStore->record(
            'duel.resolved',
            'duel',
            $duelUuid,
            [
                'mode' => 'duel',
                'viewer_uuid' => $this->player2->uuid,
                'outcome' => 'lost',
            ],
            $this->player2->uuid,
            $correlationUuid,
        );

        $player1Events = $this->service->getEventsAfter($this->player1->uuid, $afterId)
            ->where('event_type', 'duel.resolved');
        $player2Events = $this->service->getEventsAfter($this->player2->uuid, $afterId)
            ->where('event_type', 'duel.resolved');

        $this->assertCount(1, $player1Events);
        $this->assertCount(1, $player2Events);
        $this->assertSame('won', $player1Events->first()->payload['outcome']);
        $this->assertSame('lost', $player2Events->first()->payload['outcome']);
    }
}
