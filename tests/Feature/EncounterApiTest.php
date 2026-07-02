<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\GameEvent;
use App\Models\Resources;
use App\Models\User;
use App\Services\EncounterLootStationService;
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
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'test@example.com')->first();
        $this->player = $user->characters()->where('character_type', 'player')->first();
        $this->token = $user->createToken('game')->plainTextToken;

        app(EncounterLootStationService::class)->clearAllLoot($this->player);
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

    public function test_resolve_win_deposits_loot_in_temporary_slots(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/encounter/{$this->player->uuid}/resolve", [
                'encounter_slug' => 'cave_rat',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        if ($response->json('outcome') !== 'won') {
            $this->markTestSkipped('Random loss in combat simulation');
        }

        $correlationUuid = $response->json('correlation_uuid');
        $this->assertNotEmpty($correlationUuid);
        $this->assertNotEmpty($response->json('combat_log'));
        $this->assertGreaterThan(0, $response->json('battle_duration_ms'));

        $lootStation = app(EncounterLootStationService::class);
        $hasLoot = false;
        foreach ($lootStation->getTemporarySlots($this->player) as $slot) {
            if (Resources::where('buffer_slot_uuid', $slot->uuid)->exists()) {
                $hasLoot = true;
                $this->assertNotNull($slot->fresh()->timestamps_end);
            }
        }
        $this->assertTrue($hasLoot || ($response->json('loot') ?? []) === []);

        $storageResponse = $this->withToken($this->token)
            ->getJson("/api/storage/{$this->player->uuid}?include=encounter_loot,inventory");

        $storageResponse->assertOk();
        $lootStorage = collect($storageResponse->json('storages'))
            ->firstWhere('storage_type', 'encounter_loot');
        $this->assertNotNull($lootStorage);
        $this->assertNotEmpty($lootStorage['claim_expires_at']);
    }

    public function test_claim_moves_loot_and_grants_experience(): void
    {
        $encounterService = app(EncounterService::class);
        $inventoryService = app(InventoryService::class);

        $resolved = null;
        for ($attempt = 0; $attempt < 30; $attempt++) {
            try {
                $resolved = $encounterService->resolve($this->player, 'cave_rat');
                if ($resolved['outcome'] === 'won') {
                    break;
                }
            } catch (\RuntimeException) {
                app(EncounterLootStationService::class)->clearAllLoot($this->player);
            }
        }

        if (!$resolved || $resolved['outcome'] !== 'won') {
            $this->markTestSkipped('Could not get a winning combat outcome');
        }

        $xpBefore = $inventoryService->getResourceQuantity($this->player, 'experience');

        $claimResponse = $this->withToken($this->token)
            ->postJson("/api/encounter/{$this->player->uuid}/claim", [
                'correlation_uuid' => $resolved['correlation_uuid'],
            ]);

        $claimResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('experience_reward', 6);

        $xpAfter = $inventoryService->getResourceQuantity($this->player, 'experience');
        $this->assertSame($xpBefore + 6, $xpAfter);

        $this->assertTrue(
            GameEvent::where('event_type', 'encounter.won')
                ->where('actor_uuid', $this->player->uuid)
                ->exists()
        );

        foreach (app(EncounterLootStationService::class)->getTemporarySlots($this->player) as $slot) {
            $this->assertFalse(
                Resources::where('buffer_slot_uuid', $slot->uuid)->exists()
            );
        }
    }

    public function test_claim_rejected_after_expiry(): void
    {
        $encounterService = app(EncounterService::class);
        $resolved = null;

        for ($attempt = 0; $attempt < 30; $attempt++) {
            try {
                $resolved = $encounterService->resolve($this->player, 'cave_rat');
                if ($resolved['outcome'] === 'won') {
                    break;
                }
            } catch (\RuntimeException) {
                app(EncounterLootStationService::class)->clearAllLoot($this->player);
            }
        }

        if (!$resolved || $resolved['outcome'] !== 'won') {
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
        $lootStation = app(EncounterLootStationService::class);
        $lootStation->depositLoot($this->player, ['wood' => 1], Carbon::now()->addHour());

        $response = $this->withToken($this->token)
            ->postJson("/api/encounter/{$this->player->uuid}/resolve", [
                'encounter_slug' => 'cave_rat',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Сначала заберите добычу с прошлого боя');
    }
}
