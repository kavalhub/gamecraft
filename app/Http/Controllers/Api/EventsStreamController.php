<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\EventQueryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventsStreamController extends Controller
{
    public function __construct(
        private EventQueryService $eventQueryService
    ) {}

    public function stream(Request $request): StreamedResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');
        $lastEventId = (int) $request->query('last_event_id', 0);

        return response()->stream(function () use ($character, $lastEventId) {
            echo "retry: 2000\n";
            echo "data: " . json_encode(['type' => 'connected']) . "\n\n";
            flush();

            $currentLastId = $lastEventId;
            $maxExecutionTime = 3600;
            $startTime = time();

            while (time() - $startTime < $maxExecutionTime) {
                $events = $this->eventQueryService->getEventsAfter($character->uuid, $currentLastId);

                foreach ($events as $event) {
                    $data = [
                        'id' => $event->id,
                        'type' => $event->event_type,
                        'occurred_at' => $event->occurred_at->format('H:i:s'),
                        'payload' => $event->payload,
                    ];

                    echo "id: {$event->id}\n";
                    echo "data: " . json_encode($data) . "\n\n";
                    flush();

                    $currentLastId = $event->id;
                }

                if (function_exists('Swoole\Coroutine\System::sleep')) {
                    \Swoole\Coroutine\System::sleep(1);
                } else {
                    sleep(1);
                }

                if (connection_aborted()) {
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
