<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\Character;

interface StorageTransferPolicy
{
    /**
     * @param  array{kind: string, cell: mixed}  $from
     * @param  array{kind: string, cell: mixed}  $to
     */
    public function assertAllowed(Character $actor, array $from, array $to): void;
}
