<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\ItemInstance;
use App\Models\ItemTemplate;
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

    public function recordItemEvent(
        string $eventType,
        int $userId,
        int $templateId,
        ?int $instanceId = null,
        array $extraPayload = [],
        ?string $correlationId = null
    ): GameEvent {
        $template = ItemTemplate::find($templateId);

        $payload = array_merge([
            'template_id' => $templateId,
            'template_name' => $template?->name ?? 'Неизвестно',
            'template_type' => $template?->type ?? 'material',
            'template_icon' => $template?->icon ?? '📦',
            'description' => $template?->description ?? '',
        ], $extraPayload);

        if ($instanceId) {
            $instance = ItemInstance::find($instanceId);
            $payload['instance_id'] = $instanceId;
            $payload['stats'] = $instance?->stats ?? [];
        }

        return $this->record(
            $eventType,
            'user',
            $userId,
            $payload,
            $userId,
            $correlationId
        );
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
