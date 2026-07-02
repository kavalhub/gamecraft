<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\DuelOffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuelService
{
    public function __construct(
        private CombatSimulator $combatSimulator,
        private CharacterStatsService $characterStatsService,
        private CorpseLootService $corpseLoot,
        private EventStore $eventStore,
    ) {}

    public function getPendingFor(Character $character): ?DuelOffer
    {
        return DuelOffer::where('status', 'pending')
            ->where(function ($q) use ($character) {
                $q->where('challenger_uuid', $character->uuid)
                    ->orWhere('opponent_uuid', $character->uuid);
            })
            ->with(['challenger', 'opponent'])
            ->orderByDesc('id')
            ->first();
    }

    public function challenge(Character $challenger, Character $opponent): DuelOffer
    {
        if ($challenger->uuid === $opponent->uuid) {
            throw new \RuntimeException('Нельзя вызвать на дуэль самого себя');
        }

        if ($challenger->character_type !== 'player' || $opponent->character_type !== 'player') {
            throw new \RuntimeException('Дуэль доступна только между игроками');
        }

        $this->assertReadyForCombat($challenger);
        $this->assertReadyForCombat($opponent);

        if ($this->getPendingFor($challenger)) {
            throw new \RuntimeException('У вас уже есть активный вызов на дуэль');
        }

        if ($this->getPendingFor($opponent)) {
            throw new \RuntimeException('Этот игрок уже участвует в другой дуэли');
        }

        return DB::transaction(function () use ($challenger, $opponent) {
            $duel = DuelOffer::create([
                'challenger_uuid' => $challenger->uuid,
                'opponent_uuid' => $opponent->uuid,
                'status' => 'pending',
            ]);

            $this->eventStore->record(
                'duel.challenged',
                'duel',
                $duel->uuid,
                [
                    'duel_uuid' => $duel->uuid,
                    'challenger_uuid' => $challenger->uuid,
                    'challenger_name' => $challenger->name,
                    'opponent_uuid' => $opponent->uuid,
                    'opponent_name' => $opponent->name,
                ],
                $challenger->uuid,
            );

            $this->eventStore->record(
                'duel.challenged',
                'duel',
                $duel->uuid,
                [
                    'duel_uuid' => $duel->uuid,
                    'challenger_uuid' => $challenger->uuid,
                    'challenger_name' => $challenger->name,
                    'opponent_uuid' => $opponent->uuid,
                    'opponent_name' => $opponent->name,
                ],
                $opponent->uuid,
            );

            return $duel->load(['challenger', 'opponent']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function accept(Character $actor, DuelOffer $duel): array
    {
        if ($duel->status !== 'pending') {
            throw new \RuntimeException('Вызов на дуэль уже обработан');
        }

        if ($duel->opponent_uuid !== $actor->uuid) {
            throw new \RuntimeException('Принять дуэль может только оппонент');
        }

        return $this->resolveDuel($duel);
    }

    public function decline(Character $actor, DuelOffer $duel): DuelOffer
    {
        if ($duel->status !== 'pending') {
            throw new \RuntimeException('Вызов на дуэль уже обработан');
        }

        if (!in_array($actor->uuid, [$duel->challenger_uuid, $duel->opponent_uuid], true)) {
            throw new \RuntimeException('Вы не участник этой дуэли');
        }

        return DB::transaction(function () use ($actor, $duel) {
            $duel->update(['status' => 'cancelled']);

            $payload = [
                'duel_uuid' => $duel->uuid,
                'cancelled_by' => $actor->uuid,
            ];

            $this->eventStore->record('duel.cancelled', 'duel', $duel->uuid, $payload, $duel->challenger_uuid);
            $this->eventStore->record('duel.cancelled', 'duel', $duel->uuid, $payload, $duel->opponent_uuid);

            return $duel->fresh(['challenger', 'opponent']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDuel(DuelOffer $duel): array
    {
        return DB::transaction(function () use ($duel) {
            $duel = DuelOffer::where('uuid', $duel->uuid)->lockForUpdate()->firstOrFail();

            if ($duel->status !== 'pending') {
                throw new \RuntimeException('Вызов на дуэль уже обработан');
            }

            $challenger = Character::where('uuid', $duel->challenger_uuid)->firstOrFail();
            $opponent = Character::where('uuid', $duel->opponent_uuid)->firstOrFail();

            $this->assertReadyForCombat($challenger);
            $this->assertReadyForCombat($opponent);

            $challengerStats = $this->characterStatsService->ensureFor($challenger)['total'] ?? [];
            $opponentStats = $this->characterStatsService->ensureFor($opponent)['total'] ?? [];

            $simulation = $this->combatSimulator->resolve(
                $challenger->name,
                $challengerStats,
                $opponent->name,
                $opponentStats,
            );

            $lineMs = (int) config('game.combat_log_line_ms', 500);
            $battleDurationMs = count($simulation['combat_log']) * $lineMs;
            $correlationUuid = Str::uuid()->toString();

            $challengerView = $this->combatSimulator->personalizeForLeft(
                $challenger->name,
                $opponent->name,
                $simulation,
            );
            $opponentView = $this->combatSimulator->personalizeForRight(
                $challenger->name,
                $opponent->name,
                $simulation,
            );

            $winnerUuid = match ($simulation['winner']) {
                'left' => $challenger->uuid,
                'right' => $opponent->uuid,
                default => null,
            };

            $duel->update([
                'status' => 'resolved',
                'correlation_uuid' => $correlationUuid,
            ]);

            $basePayload = [
                'duel_uuid' => $duel->uuid,
                'correlation_uuid' => $correlationUuid,
                'challenger_uuid' => $challenger->uuid,
                'opponent_uuid' => $opponent->uuid,
                'winner_uuid' => $winnerUuid,
                'battle_duration_ms' => $battleDurationMs,
                'combat_log_line_ms' => $lineMs,
            ];

            $this->recordResolvedForActor($challenger, $opponent, $challengerView, $basePayload);
            $this->recordResolvedForActor($opponent, $challenger, $opponentView, $basePayload);

            return $this->formatBattleResponse(
                $opponent,
                $challenger,
                $opponentView,
                $basePayload,
                $duel,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $view
     * @param  array<string, mixed>  $basePayload
     */
    private function recordResolvedForActor(
        Character $actor,
        Character $foe,
        array $view,
        array $basePayload,
    ): void {
        $payload = array_merge($basePayload, [
            'viewer_uuid' => $actor->uuid,
            'outcome' => $view['outcome'],
            'combat_log' => $view['combat_log'],
            'combat_ui' => $view['combat_ui'],
            'player_hp_remaining' => $view['player_hp_remaining'],
            'foe_uuid' => $foe->uuid,
            'foe_name' => $foe->name,
            'mode' => 'duel',
        ]);

        $this->eventStore->record(
            'duel.resolved',
            'duel',
            $basePayload['duel_uuid'],
            $payload,
            $actor->uuid,
            $basePayload['correlation_uuid'],
        );

        $eventType = $view['outcome'] === 'won' ? 'duel.won' : 'duel.lost';
        $this->eventStore->record(
            $eventType,
            'duel',
            $basePayload['duel_uuid'],
            $payload,
            $actor->uuid,
            $basePayload['correlation_uuid'],
        );
    }

    /**
     * @param  array<string, mixed>  $view
     * @param  array<string, mixed>  $basePayload
     * @return array<string, mixed>
     */
    public function formatBattleResponse(
        Character $actor,
        Character $foe,
        array $view,
        array $basePayload,
        DuelOffer $duel,
    ): array {
        return [
            'success' => true,
            'mode' => 'duel',
            'viewer_uuid' => $actor->uuid,
            'outcome' => $view['outcome'],
            'duel_uuid' => $duel->uuid,
            'correlation_uuid' => $basePayload['correlation_uuid'],
            'opponent_uuid' => $foe->uuid,
            'opponent_name' => $foe->name,
            'encounter_slug' => null,
            'encounter_name' => $foe->name,
            'combat_log' => $view['combat_log'],
            'combat_ui' => $view['combat_ui'],
            'player_hp_remaining' => $view['player_hp_remaining'],
            'loot' => [],
            'experience_reward' => 0,
            'corpse_uuid' => null,
            'battle_duration_ms' => $basePayload['battle_duration_ms'],
            'combat_log_line_ms' => $basePayload['combat_log_line_ms'],
            'claim_grace_ms' => 0,
            'claim_expires_at' => null,
            'winner_uuid' => $basePayload['winner_uuid'],
        ];
    }

    private function assertReadyForCombat(Character $character): void
    {
        $this->corpseLoot->clearExpiredLootForPlayer($character);

        if ($this->corpseLoot->hasUnclaimedLoot($character)) {
            throw new \RuntimeException('Сначала заберите добычу с прошлого боя');
        }
    }
}
