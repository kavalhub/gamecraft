<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventQueryService;
use Illuminate\Console\Command;
use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;
use Swoole\Http\Request;
use Swoole\Table;
use Swoole\Timer;

class WebSocketServe extends Command
{
    protected $signature = 'websocket:serve {--port=8001}';
    protected $description = 'Start WebSocket server for real-time events';

    private Table $clients;

    public function handle(): int
    {
        $port = (int) $this->option('port');

        $this->clients = new Table(1024);
        $this->clients->column('character_uuid', Table::TYPE_STRING, 64);
        $this->clients->column('last_event_id', Table::TYPE_INT, 8);
        $this->clients->create();

        $server = new Server('0.0.0.0', $port);
        $clients = $this->clients;
        $self = $this;

        $server->set([
            'worker_num' => 1,
            'daemonize' => false,
        ]);

        $server->on('workerStart', function (Server $server, int $workerId) use ($clients, $self) {
            if ($workerId === 0) {
                $self->info("Timer started in worker {$workerId}");
                
                Timer::tick(1000, function () use ($server, $clients, $self) {
                    foreach ($clients as $fd => $client) {
                        $characterUuid = $client['character_uuid'];
                        $lastEventId = (int) $client['last_event_id'];

                        if (!$characterUuid) continue;

                        $events = app(EventQueryService::class)->getEventsAfter($characterUuid, $lastEventId);

                        if ($events->isEmpty()) continue;

                        $maxId = $lastEventId;
                        foreach ($events as $event) {
                            $payload = [
                                'id' => $event->id,
                                'type' => $event->event_type,
                                'occurred_at' => $event->occurred_at->format('H:i:s'),
                                'payload' => $event->payload,
                            ];

                            if ($server->isEstablished((int) $fd)) {
                                $server->push((int) $fd, json_encode($payload));
                            }

                            if ($event->id > $maxId) {
                                $maxId = $event->id;
                            }
                        }

                        $clients->set((string) $fd, [
                            'character_uuid' => $characterUuid,
                            'last_event_id' => $maxId,
                        ]);
                    }
                });
            }
        });

        $server->on('open', function (Server $server, Request $request) use ($clients, $self) {
            $characterUuid = $request->get['character_uuid'] ?? '';
            $lastEventId = (int) ($request->get['last_event_id'] ?? 0);

            $clients->set((string) $request->fd, [
                'character_uuid' => $characterUuid,
                'last_event_id' => $lastEventId,
            ]);

            $self->info("Client {$request->fd} connected: {$characterUuid}");

            $server->push($request->fd, json_encode([
                'type' => 'connected',
                'fd' => $request->fd,
            ]));
        });

        $server->on('message', function (Server $server, Frame $frame) use ($clients) {
            $data = json_decode($frame->data, true);
            if (!$data) return;

            if (($data['type'] ?? '') === 'subscribe') {
                $clients->set((string) $frame->fd, [
                    'character_uuid' => $data['character_uuid'] ?? '',
                    'last_event_id' => (int) ($data['last_event_id'] ?? 0),
                ]);
            }
        });

        $server->on('close', function (Server $server, int $fd) use ($clients, $self) {
            $clients->del((string) $fd);
            $self->info("Client {$fd} disconnected");
        });

        $this->info("WebSocket server started on port {$port}");
        $server->start();

        return 0;
    }
}
