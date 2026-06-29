<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\GameEvent;
use App\Models\TradeOffer;
use App\Models\User;
use App\Services\EventStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventsApiTest extends TestCase
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

    public function test_public_latest_returns_only_public_types_and_respects_limit(): void
    {
        $eventStore = app(EventStore::class);

        $eventStore->record(
            'user.registered',
            'user',
            Str::uuid()->toString(),
            ['username' => 'Hero'],
            $this->player->uuid,
        );

        $eventStore->record(
            'trade.created',
            'trade',
            Str::uuid()->toString(),
            ['partner_uuid' => Str::uuid()->toString()],
            $this->player->uuid,
        );

        $eventStore->record(
            'auction.listed',
            'auction_lot',
            Str::uuid()->toString(),
            ['price' => 100],
            $this->player->uuid,
        );

        for ($i = 0; $i < 3; $i++) {
            $eventStore->record(
                'presence.changed',
                'presence',
                'global',
                ['action' => 'online', 'character_name' => 'P' . $i],
                $this->player->uuid,
            );
        }

        $response = $this->withToken($this->token)
            ->getJson("/api/events/{$this->player->uuid}/latest?visibility=public&limit=20");

        $response->assertOk();
        $types = collect($response->json('events'))->pluck('type')->all();

        $this->assertGreaterThanOrEqual(3, count($types));
        $this->assertLessThanOrEqual(20, count($types));
        $this->assertNotContains('trade.created', $types);
        $this->assertContains('user.registered', $types);
        $this->assertContains('auction.listed', $types);

        foreach ($types as $type) {
            $this->assertContains($type, config('game_events.public_types'));
        }
    }

    public function test_journal_excludes_other_characters_events(): void
    {
        $eventStore = app(EventStore::class);
        $other = Character::create([
            'user_uuid' => $this->player->user_uuid,
            'character_type' => 'player',
            'name' => 'Other Character',
            'active' => true,
        ]);

        $eventStore->record(
            'item.crafted',
            'item',
            Str::uuid()->toString(),
            ['custom_name' => 'Mine'],
            $this->player->uuid,
        );

        $eventStore->record(
            'item.crafted',
            'item',
            Str::uuid()->toString(),
            ['custom_name' => 'Theirs'],
            $other->uuid,
        );

        $eventStore->record(
            'presence.changed',
            'presence',
            'global',
            ['action' => 'online', 'character_name' => 'Other Character'],
            $other->uuid,
        );

        $response = $this->withToken($this->token)
            ->getJson("/api/events/{$this->player->uuid}/latest?visibility=public&limit=20");

        $response->assertOk();
        $events = collect($response->json('events'));

        $this->assertTrue($events->contains(fn ($e) => ($e['payload']['custom_name'] ?? '') === 'Mine'));
        $this->assertFalse($events->contains(fn ($e) => ($e['payload']['custom_name'] ?? '') === 'Theirs'));
        $this->assertFalse($events->contains(fn ($e) => ($e['payload']['character_name'] ?? '') === 'Other Character'));
    }

    public function test_journal_shows_auction_side_per_character(): void
    {
        $eventStore = app(EventStore::class);
        $seller = Character::create([
            'user_uuid' => $this->player->user_uuid,
            'character_type' => 'player',
            'name' => 'Seller',
            'active' => true,
        ]);
        $lotUuid = Str::uuid()->toString();
        $payload = [
            'template_slug' => 'wood',
            'name' => 'Дерево',
            'quantity' => 10,
        ];

        $eventStore->record('auction.purchased', 'auction_lot', $lotUuid, $payload, $this->player->uuid);
        $eventStore->record('auction.sold', 'auction_lot', $lotUuid, $payload, $seller->uuid);

        $buyerResponse = $this->withToken($this->token)
            ->getJson("/api/events/{$this->player->uuid}/latest?visibility=public&limit=20");
        $buyerTypes = collect($buyerResponse->json('events'))->pluck('type')->all();

        $this->assertContains('auction.purchased', $buyerTypes);
        $this->assertNotContains('auction.sold', $buyerTypes);

        $sellerResponse = $this->withToken($this->token)
            ->getJson("/api/events/{$seller->uuid}/latest?visibility=public&limit=20");
        $sellerTypes = collect($sellerResponse->json('events'))->pluck('type')->all();

        $this->assertContains('auction.sold', $sellerTypes);
        $this->assertNotContains('auction.purchased', $sellerTypes);
    }

    public function test_journal_includes_trade_completed_for_participants(): void
    {
        $partner = Character::create([
            'user_uuid' => $this->player->user_uuid,
            'character_type' => 'player',
            'name' => 'Partner',
            'active' => true,
        ]);

        $trade = TradeOffer::create([
            'initiator_uuid' => $this->player->uuid,
            'partner_uuid' => $partner->uuid,
            'status' => 'completed',
        ]);

        GameEvent::create([
            'uuid' => Str::uuid()->toString(),
            'event_type' => 'trade.completed',
            'aggregate_type' => 'trade',
            'aggregate_uuid' => $trade->uuid,
            'actor_uuid' => null,
            'occurred_at' => now(),
            'payload' => ['items_count' => 1],
            'metadata' => [],
            'correlation_uuid' => Str::uuid()->toString(),
            'version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/events/{$this->player->uuid}/latest?visibility=public&limit=20");

        $response->assertOk();
        $types = collect($response->json('events'))->pluck('type')->all();
        $this->assertContains('trade.completed', $types);
    }

    public function test_public_latest_respects_limit(): void
    {
        $eventStore = app(EventStore::class);

        for ($i = 0; $i < 25; $i++) {
            $eventStore->record(
                'presence.changed',
                'presence',
                'global',
                ['action' => 'online', 'character_name' => 'L' . $i],
                $this->player->uuid,
            );
        }

        $response = $this->withToken($this->token)
            ->getJson("/api/events/{$this->player->uuid}/latest?visibility=public&limit=20");

        $response->assertOk();
        $this->assertCount(20, $response->json('events'));
    }

    public function test_default_latest_still_returns_character_scoped_events(): void
    {
        GameEvent::create([
            'uuid' => Str::uuid()->toString(),
            'event_type' => 'trade.created',
            'aggregate_type' => 'trade',
            'aggregate_uuid' => Str::uuid()->toString(),
            'actor_uuid' => $this->player->uuid,
            'occurred_at' => now(),
            'payload' => [],
            'metadata' => [],
            'correlation_uuid' => Str::uuid()->toString(),
            'version' => 1,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/events/{$this->player->uuid}/latest?limit=10");

        $response->assertOk();
        $types = collect($response->json('events'))->pluck('type')->all();
        $this->assertContains('trade.created', $types);
    }
}
