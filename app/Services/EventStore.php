<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use Illuminate\Support\Str;

class EventStore
{
    /**
     * Записать событие
     */
    public function record(
        string $eventType,
        string $aggregateType,
        int $aggregateId,
        array $payload,
        ?int $actorId = null,
        ?string $correlationId = null,
        ?string $causationId = null,
        ?array $metadata = null
    ): GameEvent {
        return GameEvent::create([
            'uuid' => Str::uuid()->toString(),
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'actor_id' => $actorId,
            'occurred_at' => now(),
            'payload' => $payload,
            'metadata' => array_merge($metadata ?? [], [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]),
            'correlation_id' => $correlationId ?? Str::uuid()->toString(),
            'causation_id' => $causationId,
            'version' => $this->getNextVersion($aggregateType, $aggregateId),
        ]);
    }

    /**
     * Получить историю событий для агрегата
     */
    public function getHistory(string $aggregateType, int $aggregateId): \Illuminate\Database\Eloquent\Collection
    {
        return GameEvent::where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->orderBy('occurred_at')
            ->get();
    }

    /**
     * Получить события по корреляции (вся цепочка операции)
     */
    public function getByCorrelation(string $correlationId): \Illuminate\Database\Eloquent\Collection
    {
        return GameEvent::where('correlation_id', $correlationId)
            ->orderBy('occurred_at')
            ->get();
    }

    private function getNextVersion(string $aggregateType, int $aggregateId): int
    {
        $last = GameEvent::where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->max('version');

        return ($last ?? 0) + 1;
    }
}
