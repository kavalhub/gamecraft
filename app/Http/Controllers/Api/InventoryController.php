<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    public function index(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        $resources = $this->inventoryService->getCharacterResources($character)->whereNull('buffer_slot_uuid');
        $items = $this->inventoryService->getCharacterItems($character)->whereNull('buffer_slot_uuid');

        return response()->json([
            'character_uuid' => $character->uuid,
            'character_name' => $character->name,
            'resources' => $resources->map(fn($r) => [
                'uuid' => $r->uuid,
                'template_slug' => $r->template_slug,
                'name' => $r->template->name,
                'icon' => $r->template->icon,
                'description' => $r->template->description,
                'quantity' => $r->quantity,
                'max_stack' => $r->max_stack ?? $r->template->max_stack,
                'slot_uuid' => $r->slot_uuid,
            ]),
            'items' => $items->map(fn($i) => [
                'uuid' => $i->uuid,
                'template_slug' => $i->template_slug,
                'name' => $i->custom_name ?? $i->template->name,
                'icon' => $i->template->icon,
                'description' => $i->template->description,
                'stage' => $i->stage,
                'recipe_slug' => $i->recipe_slug,
                'custom_name' => $i->custom_name,
                'stats' => $i->stats,
                'materials_used' => $i->materials_used,
                'slot_uuid' => $i->slot_uuid,
            ]),
        ]);
    }
}
