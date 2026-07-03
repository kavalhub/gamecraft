<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\GameEvent;
use App\Models\Resources;
use App\Models\Storage;
use App\Models\User;
use App\Services\CorpseLootService;
use App\Services\EncounterService;
use App\Services\InventoryService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncounterApiTest extends TestCase
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

        $this->clearPlayerCorpseLoot();
    }

    public function test_catalog_lists_encounters(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/encounter/{$this->player->uuid}/catalog");

        $response->assertOk()
            ->assertJsonStructure([
                'encounters',
                'timing' => ['combat_log_line_ms', 'combat_claim_grace_ms'],
            ]);

        $slugs = collect($response->json('encounters'))->pluck('slug');
        $this->assertTrue($slugs->contains('forest_wolf'));
        $this->assertTrue($slugs->contains('cave_rat'));
    }

    public function test_resolve_win_spawns_corpse_with_loot(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/encounter/{$this->player->uuid}/resolve", [
                'encounter_slug' => 'forest_wolf',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        if ($response->json('outcome') !== 'won') {
            $this->markTestSkipped('Random loss in combat simulation');
        }

        $correlationUuid = $response->json('correlation_uuid');
        $corpseUuid = $response->json('corpse_uuid');
        $this->assertNotEmpty($correlationUuid);
        $this->assertNotEmpty($corpseUuid);
        $this->assertNotEmpty($response->json('combat_log'));
        $this->assertGreaterThan(0, $response->json('battle_duration_ms'));

        $corpse = Character::where('uuid', $corpseUuid)->where('character_type', 'corpse')->first();
        $this->assertNotNull($corpse);

        $corpseLoot = app(CorpseLootService::class);
        $hasLoot = false;
        foreach ($corpseLoot->getLootSlots($corpse) as $slot) {
            if (Resources::where('slot_uuid', $slot->uuid)->whereNull('buffer_slot_uuid')->exists()) {
                $hasLoot = true;
                $this->assertSame($this->player->uuid, $slot->fresh()->character_uuid);
                $this->assertNotNull($slot->fresh()->timestamps_end);
            }
        }
        $this->assertTrue($hasLoot || ($response->json('loot') ?? []) === []);

        $storageResponse = $this->withToken($this->token)
            ->getJson("/api/storage/{$this->player->uuid}?include=corpse,inventory&corpse_uuid={$corpseUuid}");

        $storageResponse->assertOk()
            ->assertJsonPath('corpse_uuid', $corpseUuid);
        $corpseStorage = collect($storageResponse->json('storages'))
            ->firstWhere('storage_type', 'corpse');
        $this->assertNotNull($corpseStorage);
        $this->assertNotEmpty($corpseStorage['claim_expires_at']);
    }

    public function test_claim_moves_loot_and_grants_experience(): void
    {
        $encounterService = app(EncounterService::class);
        $inventoryService = app(InventoryService::class);

        $resolved = $this->resolveWinningEncounter($encounterService, 'forest_wolf');

        if (!$resolved) {
            $this->markTestSkipped('Could not get a winning combat outcome');
        }

        $xpBefore = $inventoryService->getResourceQuantity($this->player, 'experience');

        $claimResponse = $this->withToken($this->token)
            ->postJson("/api/encounter/{$this->player->uuid}/claim", [
                'correlation_uuid' => $resolved['correlation_uuid'],
            ]);

        $claimResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('experience_reward', 12);

        $xpAfter = $inventoryService->getResourceQuantity($this->player, 'experience');
        $this->assertSame($xpBefore + 12, $xpAfter);

        $this->assertTrue(
            GameEvent::where('event_type', 'encounter.won')
                ->where('actor_uuid', $this->player->uuid)
                ->exists()
        );

        $corpse = Character::where('uuid', $resolved['corpse_uuid'])->first();
        $this->assertNotNull($corpse);
        foreach (app(CorpseLootService::class)->getLootSlots($corpse) as $slot) {
            $this->assertFalse(
                Resources::where('slot_uuid', $slot->uuid)->whereNull('buffer_slot_uuid')->exists()
            );
        }
    }

    public function test_claim_rejected_after_expiry(): void
    {
        $encounterService = app(EncounterService::class);
        $resolved = $this->resolveWinningEncounter($encounterService, 'forest_wolf');

        if (!$resolved) {
            $this->markTestSkipped('Could not get a winning combat outcome');
        }

        Carbon::setTestNow(Carbon::parse($resolved['claim_expires_at'])->addSecond());

        $response = $this->withToken($this->token)
            ->postJson("/api/encounter/{$this->player->uuid}/claim", [
                'correlation_uuid' => $resolved['correlation_uuid'],
            ]);

        Carbon::setTestNow();

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_resolve_blocked_while_unclaimed_loot_exists(): void
    {
        app(CorpseLootService::class)->createCorpseWithLoot(
            $this->player,
            'forest_wolf',
            'Лесной волк',
            ['wood' => 1],
            Carbon::now()->addHour(),
        );

        $response = $this->withToken($this->token)
            ->postJson("/api/encounter/{$this->player->uuid}/resolve", [
                'encounter_slug' => 'cave_rat',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Сначала заберите добычу с прошлого боя');
    }

    public function test_refuse_makes_corpse_loot_public(): void
    {
        $encounterService = app(EncounterService::class);
        $resolved = $this->resolveWinningEncounter($encounterService, 'forest_wolf');

        if (!$resolved || empty($resolved['corpse_uuid'])) {
            $this->markTestSkipped('Could not get a winning combat outcome with loot');
        }

        $refuseResponse = $this->withToken($this->token)
            ->postJson("/api/encounter/{$this->player->uuid}/refuse", [
                'correlation_uuid' => $resolved['correlation_uuid'],
            ]);

        $refuseResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('corpse_uuid', $resolved['corpse_uuid']);

        $corpse = Character::where('uuid', $resolved['corpse_uuid'])->firstOrFail();
        foreach (app(CorpseLootService::class)->getLootSlots($corpse) as $slot) {
            if (Resources::where('slot_uuid', $slot->uuid)->exists()) {
                $this->assertNull($slot->fresh()->character_uuid);
            }
        }

        $this->assertTrue(
            GameEvent::where('event_type', 'encounter.refused')
                ->where('actor_uuid', $this->player->uuid)
                ->exists()
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveWinningEncounter(EncounterService $encounterService, string $slug): ?array
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            try {
                $resolved = $encounterService->resolve($this->player, $slug);
                if ($resolved['outcome'] === 'won') {
                    return $resolved;
                }
            } catch (\RuntimeException) {
                $this->clearPlayerCorpseLoot();
            }
        }

        return null;
    }

    private function clearPlayerCorpseLoot(): void
    {
        $corpseLoot = app(CorpseLootService::class);
        foreach ($corpseLoot->getLootSlotsForClaimer($this->player) as $slot) {
            $storage = Storage::where('uuid', $slot->storage_uuid)->first();
            if (!$storage) {
                continue;
            }
            $corpse = Character::where('uuid', $storage->characters_uuid)->first();
            if ($corpse) {
                $corpseLoot->clearCorpse($corpse);
            }
        }
    }
}
