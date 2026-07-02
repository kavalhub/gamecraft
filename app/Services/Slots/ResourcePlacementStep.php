<?php

declare(strict_types=1);

namespace App\Services\Slots;

final class ResourcePlacementStep
{
    public function __construct(
        public readonly string $targetSlotUuid,
        public readonly int $quantity,
        public readonly ?string $mergeIntoResourceUuid = null,
    ) {}
}
