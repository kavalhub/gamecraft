<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\Character;
use App\Models\Storage;
use Illuminate\Support\Facades\DB;

final class PlayerStorageAccess
{
    /** @var list<string> */
    public const OWNED_REGULAR_TYPES = ['inventory', 'equipment', 'bank', 'post_outbox', 'trade'];

    /** @var list<string> */
    public const TRADEABLE_SOURCE_TYPES = ['inventory', 'bank', 'equipment', 'post_outbox'];

    public function isOwnedRegularStorage(Character $actor, Storage $storage): bool
    {
        if ($storage->characters_uuid === $actor->uuid) {
            return in_array($storage->storage_type, self::OWNED_REGULAR_TYPES, true);
        }

        if ($storage->storage_type === 'guild_bank') {
            return $this->isGuildBankMember($actor, $storage->characters_uuid);
        }

        return false;
    }

    public function isTradeableSourceStorage(Character $actor, Storage $storage): bool
    {
        return $storage->characters_uuid === $actor->uuid
            && in_array($storage->storage_type, self::TRADEABLE_SOURCE_TYPES, true);
    }

    public function isTradeReturnDestination(Character $actor, Storage $storage): bool
    {
        return $this->isTradeableSourceStorage($actor, $storage);
    }

    private function isGuildBankMember(Character $actor, string $guildUuid): bool
    {
        return DB::table('guilds_members')
            ->where('head_uuid', $guildUuid)
            ->where('member_uuid', $actor->uuid)
            ->where('active', true)
            ->exists();
    }
}
