<?php

declare(strict_types=1);

namespace App\Services\Slots;

use App\Models\Character;
use App\Models\ItemTemplate;
use App\Models\Resources;

interface SlotScope
{
    public function findPartialStack(string $templateSlug, ?int $maxStack): ?Resources;

    public function findEmptyCell(): mixed;

    public function createResource(
        Character $character,
        ItemTemplate $template,
        string $templateSlug,
        int $quantity,
        mixed $cell
    ): Resources;

    public function exhaustedMessage(): string;
}
