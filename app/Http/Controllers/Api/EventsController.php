<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\EventQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);

        $limit = (int) $request->input('limit', 50);
        $afterId = $request->input('after_id') ? (int) $request->input('after_id') : null;

        $events = $this->eventQueryService->getLatestEvents($character->uuid, $afterId, $limit);

        return response()->json([
            'events' => $events->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->event_type,
                'occurred_at' => $e->occurred_at->format('H:i:s'),
                'payload' => $e->payload,
            ]),
        ]);
    }
}
