<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\Character;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\TemporarySlot;

final class RestrictedInventoryHubPolicy implements StorageTransferPolicy
{
    public function assertAllowed(Character $actor, array $from, array $to): void
    {
        $fromType = $this->resolveRegularStorageType($from);
        $toType = $this->resolveRegularStorageType($to);

        if ($fromType === 'bank' || $toType === 'bank') {
            $other = $fromType === 'bank' ? $toType : $fromType;
            if ($other !== 'inventory') {
                throw new \RuntimeException('Личный банк доступен только для инвентаря');
            }
        }

        if ($fromType === 'guild_bank' || $toType === 'guild_bank') {
            $other = $fromType === 'guild_bank' ? $toType : $fromType;
            if ($other !== 'inventory') {
                throw new \RuntimeException('Банк гильдии доступен только для инвентаря');
            }
        }

        if ($fromType === 'post_outbox' || $toType === 'post_outbox') {
            $other = $fromType === 'post_outbox' ? $toType : $fromType;
            if ($other !== 'inventory' && $other !== 'post_outbox') {
                throw new \RuntimeException('Исходящая почта доступна только для инвентаря');
            }
        }
    }

    private function resolveRegularStorageType(array $cell): ?string
    {
        if ($cell['kind'] !== 'regular') {
            return null;
        }

        /** @var Slot $slot */
        $slot = $cell['cell'];

        return Storage::where('uuid', $slot->storage_uuid)->value('storage_type');
    }
}
