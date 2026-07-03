<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\Character;

final class UnrestrictedTransferPolicy implements StorageTransferPolicy
{
    public function assertAllowed(Character $actor, array $from, array $to): void
    {
    }
}
