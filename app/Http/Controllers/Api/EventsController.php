<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\GameEvent;
use App\Services\EventQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EventsController extends Controller
{
    public function __construct(
        private EventQueryService $eventQueryService
    ) {}

    public function latest(Request $request): JsonResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');

        $request->validate([
            'after_id' => 'nullable|integer',
            'limit' => 'nullable|integer|max:100',
            'visibility' => 'nullable|string|in:public',
        ]);

        if ($request->input('visibility') === 'public') {
            $limit = min((int) $request->input('limit', 20), 100);
            $events = $this->eventQueryService->getJournalEvents($character->uuid, $limit);

            return response()->json([
                'events' => $this->mapEvents($events),
            ]);
        }

        $limit = (int) $request->input('limit', 50);
        $afterId = $request->input('after_id') ? (int) $request->input('after_id') : null;

        $events = $this->eventQueryService->getLatestEvents($character->uuid, $afterId, $limit);

        return response()->json([
            'events' => $this->mapEvents($events),
        ]);
    }

    /**
     * @param  Collection<int, GameEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function mapEvents(Collection $events): array
    {
        $actorUuids = $events->pluck('actor_uuid')->filter()->unique()->values();
        $actorNames = Character::query()
            ->whereIn('uuid', $actorUuids)
            ->pluck('name', 'uuid');

        return $events->map(function (GameEvent $e) use ($actorNames) {
            $actorUuid = $e->actor_uuid;

            return [
                'id' => $e->id,
                'type' => $e->event_type,
                'occurred_at' => $e->occurred_at->format('H:i'),
                'payload' => $e->payload,
                'actor_name' => $actorUuid ? ($actorNames[$actorUuid] ?? null) : null,
            ];
        })->all();
    }
}
