<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\GameEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EncounterService
{
    public function __construct(
        private EncounterCatalog $catalog,
        private CorpseLootService $corpseLoot,
        private CharacterStatsService $characterStatsService,
        private CombatSimulator $combatSimulator,
        private ExperienceService $experienceService,
        private EventStore $eventStore,
        private QuestService $questService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listEncounters(): array
    {
        return array_map(function (array $encounter) {
            return [
                'slug' => $encounter['slug'],
                'name' => $encounter['name'],
                'description' => $encounter['description'] ?? null,
                'stats' => $encounter['stats'] ?? [],
            ];
        }, $this->catalog->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(Character $character, string $encounterSlug): array
    {
        $encounter = $this->catalog->get($encounterSlug);

        return DB::transaction(function () use ($character, $encounter, $encounterSlug) {
            $this->corpseLoot->clearExpiredLootForPlayer($character);

            if ($this->corpseLoot->hasUnclaimedLoot($character)) {
                throw new \RuntimeException('Сначала заберите добычу с прошлого боя');
            }

            $stats = $this->characterStatsService->ensureFor($character);
            $simulation = $this->combatSimulator->resolveVsNpc(
                $character->name,
                $stats,
                $encounter,
            );

            $lineMs = (int) config('game.combat_log_line_ms', 500);
            $graceMs = (int) config('game.combat_claim_grace_ms', 60_000);
            $battleDurationMs = count($simulation['combat_log']) * $lineMs;
            $claimExpiresAt = now()->addMilliseconds($battleDurationMs + $graceMs);
            $correlationUuid = Str::uuid()->toString();

            $loot = [];
            $experienceReward = 0;
            $corpseUuid = null;

            if ($simulation['outcome'] === 'won') {
                $loot = $this->rollLoot($encounter);
                $experienceReward = (int) ($encounter['experience'] ?? 0);

                if ($loot !== []) {
                    $corpseResult = $this->corpseLoot->createCorpseWithLoot(
                        $character,
                        $encounterSlug,
                        (string) ($encounter['name'] ?? $encounterSlug),
                        $loot,
                        $claimExpiresAt,
                    );
                    $corpseUuid = $corpseResult['corpse_uuid'];
                }
            }

            $payload = [
                'encounter_slug' => $encounterSlug,
                'encounter_name' => $encounter['name'] ?? $encounterSlug,
                'outcome' => $simulation['outcome'],
                'combat_log' => $simulation['combat_log'],
                'combat_ui' => $simulation['combat_ui'],
                'player_hp_remaining' => $simulation['player_hp_remaining'],
                'loot' => $loot,
                'experience_reward' => $experienceReward,
                'corpse_uuid' => $corpseUuid,
                'battle_duration_ms' => $battleDurationMs,
                'combat_log_line_ms' => $lineMs,
                'claim_grace_ms' => $graceMs,
                'claim_expires_at' => $claimExpiresAt->toIso8601String(),
                'correlation_uuid' => $correlationUuid,
            ];

            $eventType = $simulation['outcome'] === 'won' ? 'encounter.resolved' : 'encounter.lost';

            $this->eventStore->record(
                $eventType,
                'encounter',
                $encounterSlug,
                $payload,
                $character->uuid,
                $correlationUuid
            );

            if ($simulation['outcome'] === 'lost') {
                $this->questService->handleGameEvent($character, 'encounter.lost', [
                    'encounter_slug' => $encounterSlug,
                ]);
            }

            return [
                'success' => true,
                'outcome' => $simulation['outcome'],
                'encounter_slug' => $encounterSlug,
                'encounter_name' => $encounter['name'] ?? $encounterSlug,
                'combat_log' => $simulation['combat_log'],
                'combat_ui' => $simulation['combat_ui'],
                'player_hp_remaining' => $simulation['player_hp_remaining'],
                'loot' => $loot,
                'experience_reward' => $experienceReward,
                'corpse_uuid' => $corpseUuid,
                'battle_duration_ms' => $battleDurationMs,
                'combat_log_line_ms' => $lineMs,
                'claim_grace_ms' => $graceMs,
                'claim_expires_at' => $claimExpiresAt->toIso8601String(),
                'correlation_uuid' => $correlationUuid,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function claim(Character $character, string $correlationUuid): array
    {
        return DB::transaction(function () use ($character, $correlationUuid) {
            [$resolved, $corpse] = $this->resolveWonEncounter($character, $correlationUuid);

            $expiresAt = Carbon::parse($resolved->payload['claim_expires_at'] ?? '');
            if ($expiresAt->isPast()) {
                if ($corpse) {
                    $this->corpseLoot->clearCorpse($corpse);
                }
                throw new \RuntimeException('Время на получение добычи истекло');
            }

            $moved = 0;
            if ($corpse) {
                $moved = $this->corpseLoot->claimCorpseToInventory($character, $corpse);
            }

            $experienceReward = (int) ($resolved->payload['experience_reward'] ?? 0);
            $encounterSlug = (string) ($resolved->payload['encounter_slug'] ?? '');

            if ($experienceReward > 0) {
                $this->experienceService->credit($character, $experienceReward, 'encounter', [
                    'encounter_slug' => $encounterSlug,
                    'correlation_uuid' => $correlationUuid,
                ]);
            }

            $this->characterStatsService->syncLevelFromExperience($character);

            $claimPayload = [
                'encounter_slug' => $encounterSlug,
                'correlation_uuid' => $correlationUuid,
                'corpse_uuid' => $corpse?->uuid,
                'items_moved' => $moved,
                'experience_reward' => $experienceReward,
            ];

            $this->eventStore->record(
                'encounter.claimed',
                'encounter',
                $encounterSlug,
                $claimPayload,
                $character->uuid,
                $correlationUuid
            );

            $this->eventStore->record(
                'encounter.won',
                'encounter',
                $encounterSlug,
                $claimPayload,
                $character->uuid,
                $correlationUuid
            );

            $this->questService->handleGameEvent($character, 'encounter.won', [
                'encounter_slug' => $encounterSlug,
            ]);

            return [
                'success' => true,
                'items_moved' => $moved,
                'experience_reward' => $experienceReward,
                'encounter_slug' => $encounterSlug,
                'corpse_uuid' => $corpse?->uuid,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function refuse(Character $character, string $correlationUuid): array
    {
        return DB::transaction(function () use ($character, $correlationUuid) {
            [$resolved, $corpse] = $this->resolveWonEncounter($character, $correlationUuid);

            if (!$corpse) {
                throw new \RuntimeException('Нет добычи для отказа');
            }

            foreach ($this->corpseLoot->getLootSlots($corpse) as $slot) {
                if ($slot->character_uuid !== null && $slot->character_uuid !== $character->uuid) {
                    throw new \RuntimeException('Нельзя отказаться от чужой добычи');
                }
            }

            $this->corpseLoot->refuseLoot($corpse);

            $encounterSlug = (string) ($resolved->payload['encounter_slug'] ?? '');

            $this->eventStore->record(
                'encounter.refused',
                'encounter',
                $encounterSlug,
                [
                    'encounter_slug' => $encounterSlug,
                    'correlation_uuid' => $correlationUuid,
                    'corpse_uuid' => $corpse->uuid,
                ],
                $character->uuid,
                $correlationUuid
            );

            return [
                'success' => true,
                'corpse_uuid' => $corpse->uuid,
                'encounter_slug' => $encounterSlug,
            ];
        });
    }

    /**
     * @return array{0: GameEvent, 1: ?Character}
     */
    private function resolveWonEncounter(Character $character, string $correlationUuid): array
    {
        $resolved = GameEvent::where('event_type', 'encounter.resolved')
            ->where('actor_uuid', $character->uuid)
            ->where('payload->correlation_uuid', $correlationUuid)
            ->orderByDesc('occurred_at')
            ->first();

        if (!$resolved) {
            throw new \RuntimeException('Бой не найден');
        }

        if (($resolved->payload['outcome'] ?? null) !== 'won') {
            throw new \RuntimeException('Добычу можно забрать только после победы');
        }

        $alreadyClaimed = GameEvent::where('event_type', 'encounter.claimed')
            ->where('actor_uuid', $character->uuid)
            ->where('payload->correlation_uuid', $correlationUuid)
            ->exists();

        if ($alreadyClaimed) {
            throw new \RuntimeException('Добыча уже получена');
        }

        $corpseUuid = $resolved->payload['corpse_uuid'] ?? null;
        $corpse = $corpseUuid
            ? Character::where('uuid', $corpseUuid)->where('character_type', 'corpse')->first()
            : null;

        return [$resolved, $corpse];
    }

    /**
     * @param  array<string, mixed>  $encounter
     * @return array<string, int>
     */
    private function rollLoot(array $encounter): array
    {
        $lootTable = $encounter['loot'] ?? [];
        usort($lootTable, fn ($a, $b) => ($a['priority'] ?? 0) <=> ($b['priority'] ?? 0));

        $outputs = [];
        foreach ($lootTable as $entry) {
            $chance = (int) ($entry['chance'] ?? 100);
            if ($chance < 100 && random_int(1, 100) > $chance) {
                continue;
            }

            $templateSlug = (string) ($entry['template_slug'] ?? '');
            if ($templateSlug === '') {
                continue;
            }

            $min = max(1, (int) ($entry['min'] ?? 1));
            $max = max($min, (int) ($entry['max'] ?? $min));
            $quantity = $min === $max ? $min : random_int($min, $max);
            $outputs[$templateSlug] = ($outputs[$templateSlug] ?? 0) + $quantity;
        }

        return $outputs;
    }
}
