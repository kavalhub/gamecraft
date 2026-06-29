<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterHeartbeat;

class PresenceService
{
    public const ONLINE_THRESHOLD_MINUTES = 5;

    public function __construct(
        private EventStore $eventStore
    ) {}

    public function markOnline(string $characterUuid): void
    {
        $existing = CharacterHeartbeat::where('character_uuid', $characterUuid)->first();
        $wasOffline = !$existing
            || $existing->last_seen_at < now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES);

        CharacterHeartbeat::ping($characterUuid);

        if (!$wasOffline) {
            return;
        }

        $character = Character::where('uuid', $characterUuid)->first();
        if (!$character) {
            return;
        }

        $this->eventStore->record(
            'presence.changed',
            'presence',
            'global',
            [
                'action' => 'online',
                'character_uuid' => $characterUuid,
                'character_name' => $character->name,
            ],
            $characterUuid
        );
    }
}
