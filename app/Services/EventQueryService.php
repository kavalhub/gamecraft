<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\TradeOffer;
use Illuminate\Support\Collection;

class EventQueryService
{
    public function getEventsAfter(string $characterUuid, int $afterId, int $limit = 50): Collection
    {
        $tradeUuids = TradeOffer::query()
            ->where('initiator_uuid', $characterUuid)
            ->orWhere('partner_uuid', $characterUuid)
            ->pluck('uuid');

        return GameEvent::query()
            ->where('id', '>', $afterId)
            ->where(function ($query) use ($characterUuid, $tradeUuids) {
                $query->where('actor_uuid', $characterUuid);

                if ($tradeUuids->isNotEmpty()) {
                    $query->orWhere(function ($sub) use ($tradeUuids) {
                        $sub->where('aggregate_type', 'trade')
                            ->whereIn('aggregate_uuid', $tradeUuids);
                    });
                }

                $query->orWhere('aggregate_type', 'presence');
            })
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getLatestEvents(string $characterUuid, ?int $afterId, int $limit = 50): Collection
    {
        $tradeUuids = TradeOffer::query()
            ->where('initiator_uuid', $characterUuid)
            ->orWhere('partner_uuid', $characterUuid)
            ->pluck('uuid');

        $query = GameEvent::query()
            ->where(function ($q) use ($characterUuid, $tradeUuids) {
                $q->where('actor_uuid', $characterUuid);

                if ($tradeUuids->isNotEmpty()) {
                    $q->orWhere(function ($sub) use ($tradeUuids) {
                        $sub->where('aggregate_type', 'trade')
                            ->whereIn('aggregate_uuid', $tradeUuids);
                    });
                }

                $q->orWhere('aggregate_type', 'presence');
            })
            ->orderBy('id', 'desc');

        if ($afterId) {
            $query->where('id', '>', $afterId);
        } else {
            $query->limit($limit);
        }

        return $query->get()->sortBy('id')->values();
    }

    public function getPublicLatestEvents(int $limit = 20): Collection
    {
        $types = config('game_events.public_types', []);

        if ($types === []) {
            return collect();
        }

        return GameEvent::query()
            ->whereIn('event_type', $types)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();
    }

    public function getJournalEvents(string $characterUuid, int $limit = 20): Collection
    {
        $types = config('game_events.public_types', []);

        if ($types === []) {
            return collect();
        }

        $tradeUuids = TradeOffer::query()
            ->where('initiator_uuid', $characterUuid)
            ->orWhere('partner_uuid', $characterUuid)
            ->pluck('uuid');

        return GameEvent::query()
            ->whereIn('event_type', $types)
            ->where(function ($query) use ($characterUuid, $tradeUuids) {
                $query->where('actor_uuid', $characterUuid);

                if ($tradeUuids->isNotEmpty()) {
                    $query->orWhere(function ($sub) use ($tradeUuids) {
                        $sub->where('event_type', 'trade.completed')
                            ->where('aggregate_type', 'trade')
                            ->whereIn('aggregate_uuid', $tradeUuids);
                    });
                }
            })
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();
    }
}
