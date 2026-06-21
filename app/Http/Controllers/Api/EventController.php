<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameEvent;
use App\Services\EventStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private EventStore $eventStore
    ) {}

    public function userHistory(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['error' => 'user_id required'], 400);
        }

        $events = $this->eventStore->getHistory('user', (int)$userId);

        return response()->json([
            'events' => $events->map(fn($e) => [
                'id' => $e->id,
                'type' => $e->event_type,
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'payload' => $e->payload,
                'correlation_id' => $e->correlation_id,
            ]),
        ]);
    }

    public function operationDetails(string $correlationId): JsonResponse
    {
        $events = $this->eventStore->getByCorrelation($correlationId);

        return response()->json([
            'correlation_id' => $correlationId,
            'events' => $events,
        ]);
    }

    public function latest(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        $afterId = $request->query('after_id', 0);
        $limit = min((int)$request->query('limit', 50), 100);

        if (!$userId) {
            return response()->json(['error' => 'user_id required'], 400);
        }

        // Получаем события, где пользователь участвует (как actor или в payload)
        $query = GameEvent::where(function ($q) use ($userId) {
            // События пользователя
            $q->where(function ($sub) use ($userId) {
                $sub->where('aggregate_type', 'user')
                    ->where('aggregate_id', $userId);
            })
                // ИЛИ события обмена, где пользователь участвует
                ->orWhere(function ($sub) use ($userId) {
                    $sub->where('aggregate_type', 'trade')
                        ->where('actor_id', $userId);
                });
        });

        if ($afterId) {
            $query->where('id', '>', (int)$afterId);
        }

        $events = $query->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();

        return response()->json([
            'events' => $events->map(fn($e) => [
                'id' => $e->id,
                'type' => $e->event_type,
                'occurred_at' => $e->occurred_at->format('H:i:s'),
                'payload' => $e->payload,
                'correlation_id' => $e->correlation_id,
            ]),
        ]);
    }
}
