<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Character;
use App\Models\Resources;
use App\Services\DisassembleStationService;

final class DisassembleStationHelper
{
    public static function placeOnCenterSlot(Character $character, Resources $resource): void
    {
        app(DisassembleStationService::class)->ensureDisassembleStorage($character);
        $centerSlot = app(DisassembleStationService::class)->getCenterTemporarySlot($character);
        $resource->update(['buffer_slot_uuid' => $centerSlot->uuid]);
    }

    public static function placeResource(Character $character, string $templateSlug, int $quantity = 1): Resources
    {
        $inventory = $character->storages()->where('storage_type', 'inventory')->firstOrFail();
        $resource = Resources::whereIn('slot_uuid', $inventory->slots()->pluck('uuid'))
            ->where('template_slug', $templateSlug)
            ->firstOrFail();

        if ($resource->quantity < $quantity) {
            throw new \RuntimeException("Not enough {$templateSlug} in inventory");
        }

        self::placeOnCenterSlot($character, $resource);

        return $resource->fresh();
    }
}
