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
        private EncounterLootStationService $lootStation,
        private CharacterStatsService $characterStatsService,
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
            $this->lootStation->clearExpiredLoot($character);

            if ($this->lootStation->hasUnclaimedLoot($character)) {
                throw new \RuntimeException('Сначала заберите добычу с прошлого боя');
            }

            $stats = $this->characterStatsService->ensureFor($character);
            $simulation = $this->simulateCombat($character, $encounter, $stats);

            $lineMs = (int) config('game.combat_log_line_ms', 800);
            $graceMs = (int) config('game.combat_claim_grace_ms', 60_000);
            $battleDurationMs = count($simulation['combat_log']) * $lineMs;
            $claimExpiresAt = now()->addMilliseconds($battleDurationMs + $graceMs);
            $correlationUuid = Str::uuid()->toString();

            $loot = [];
            $experienceReward = 0;

            if ($simulation['outcome'] === 'won') {
                $loot = $this->rollLoot($encounter);
                $experienceReward = (int) ($encounter['experience'] ?? 0);

                if ($loot !== []) {
                    $this->lootStation->depositLoot($character, $loot, $claimExpiresAt);
                }
            }

            $payload = [
                'encounter_slug' => $encounterSlug,
                'encounter_name' => $encounter['name'] ?? $encounterSlug,
                'outcome' => $simulation['outcome'],
                'combat_log' => $simulation['combat_log'],
                'player_hp_remaining' => $simulation['player_hp_remaining'],
                'loot' => $loot,
                'experience_reward' => $experienceReward,
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
                'player_hp_remaining' => $simulation['player_hp_remaining'],
                'loot' => $loot,
                'experience_reward' => $experienceReward,
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

            $expiresAt = Carbon::parse($resolved->payload['claim_expires_at'] ?? '');
            if ($expiresAt->isPast()) {
                $this->lootStation->clearExpiredLoot($character);
                throw new \RuntimeException('Время на получение добычи истекло');
            }

            $moved = $this->lootStation->claimAllToInventory($character);
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
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $encounter
     * @param  array<string, mixed>  $stats
     * @return array{outcome: string, combat_log: list<array<string, mixed>>, player_hp_remaining: int}
     */
    private function simulateCombat(Character $character, array $encounter, array $stats): array
    {
        $enemyStats = $encounter['stats'] ?? [];
        $enemyName = (string) ($encounter['name'] ?? 'Противник');
        $playerHp = (int) ($stats['total']['health'] ?? 50);
        $enemyHp = (int) ($enemyStats['health'] ?? 10);
        $playerDefense = (int) ($stats['total']['defense'] ?? 0);
        $enemyDefense = (int) ($enemyStats['defense'] ?? 0);

        $playerAttack = $this->resolveAttackPower($stats, $enemyDefense);
        $enemyAttack = max(1, (int) ($enemyStats['damage'] ?? 1) - $playerDefense);

        $log = [];
        $round = 0;
        $maxRounds = 100;

        while ($playerHp > 0 && $enemyHp > 0 && $round < $maxRounds) {
            $round++;
            $damage = $playerAttack;
            $enemyHp = max(0, $enemyHp - $damage);
            $log[] = [
                'actor' => 'player',
                'message' => "Вы наносите {$damage} урона. У {$enemyName} осталось {$enemyHp} HP.",
            ];

            if ($enemyHp <= 0) {
                $log[] = [
                    'actor' => 'system',
                    'message' => 'Победа!',
                ];
                break;
            }

            $damage = $enemyAttack;
            $playerHp = max(0, $playerHp - $damage);
            $log[] = [
                'actor' => 'enemy',
                'message' => "{$enemyName} наносит {$damage} урона. У вас осталось {$playerHp} HP.",
            ];
        }

        if ($enemyHp > 0 && $playerHp <= 0) {
            $log[] = [
                'actor' => 'system',
                'message' => 'Поражение.',
            ];
        }

        return [
            'outcome' => $enemyHp <= 0 ? 'won' : 'lost',
            'combat_log' => $log,
            'player_hp_remaining' => max(0, $playerHp),
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function resolveAttackPower(array $stats, int $enemyDefense): int
    {
        $rawDamage = (int) ($stats['total']['damage'] ?? 0);
        if ($rawDamage < 1) {
            $rawDamage = max(1, (int) floor(((int) ($stats['total']['strength'] ?? 10)) / 2));
        }

        return max(1, $rawDamage - $enemyDefense);
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
