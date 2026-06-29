<?php

declare(strict_types=1);

namespace App\Services;

class ResourceStackingService
{
    /**
     * @return int[]
     */
    public function split(int $quantity, ?int $maxStack): array
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Количество должно быть больше 0');
        }

        if ($maxStack === null || $maxStack < 1) {
            return [$quantity];
        }

        $chunks = [];
        $remaining = $quantity;

        while ($remaining > 0) {
            $chunk = min($remaining, $maxStack);
            $chunks[] = $chunk;
            $remaining -= $chunk;
        }

        return $chunks;
    }

    public function slotCount(int $quantity, ?int $maxStack): int
    {
        return count($this->split($quantity, $maxStack));
    }
}
