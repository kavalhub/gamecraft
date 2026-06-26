<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use Illuminate\Support\Str;

class EventStore
{
    public function record(
        string $eventType,
        string $aggregateType,
        string $aggregateUuid,
        array $payload,
        ?string $actorUuid = null,
        ?string $correlationUuid = null,
        ?string $causationUuid = null,
        array $metadata = []
    ): GameEvent {
        return GameEvent::create([
            'uuid' => Str::uuid()->toString(),
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_uuid' => $aggregateUuid,
            'actor_uuid' => $actorUuid,
            'occurred_at' => now(),
            'payload' => $payload,
            'metadata' => array_merge($metadata, [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]),
            'correlation_uuid' => $correlationUuid ?? Str::uuid()->toString(),
            'causation_uuid' => $causationUuid,
            'version' => $this->getNextVersion($aggregateType, $aggregateUuid),
        ]);
    }

    private function getNextVersion(string $aggregateType, string $aggregateUuid): int
    {
        $lastEvent = GameEvent::where('aggregate_type', $aggregateType)
            ->where('aggregate_uuid', $aggregateUuid)
            ->orderBy('version', 'desc')
            ->first();

        return $lastEvent ? $lastEvent->version + 1 : 1;
    }

    public function recordResourceEvent(
        string $eventType,
        string $resourceUuid,
        array $payload,
        ?string $actorUuid = null,
        ?string $correlationUuid = null
    ): GameEvent {
        return $this->record(
            $eventType,
            'resource',
            $resourceUuid,
            $payload,
            $actorUuid,
            $correlationUuid
        );
    }

    public function recordItemEvent(
        string $eventType,
        string $itemUuid,
        array $payload,
        ?string $actorUuid = null,
        ?string $correlationUuid = null
    ): GameEvent {
        return $this->record(
            $eventType,
            'item',
            $itemUuid,
            $payload,
            $actorUuid,
            $correlationUuid
        );
    }

    public function recordCharacterEvent(
        string $eventType,
        string $characterUuid,
        array $payload,
        ?string $actorUuid = null,
        ?string $correlationUuid = null
    ): GameEvent {
        return $this->record(
            $eventType,
            'character',
            $characterUuid,
            $payload,
            $actorUuid,
            $correlationUuid
        );
    }
}
