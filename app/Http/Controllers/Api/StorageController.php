<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\StorageLayoutService;
use App\Services\StorageMoveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageController extends Controller
{
    public function __construct(
        private StorageLayoutService $layoutService,
        private StorageMoveService $moveService
    ) {}

    public function show(Request $request, string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $include = $request->query('include', 'inventory');
        $includeList = array_filter(array_map('trim', explode(',', $include)));

        $layout = $this->layoutService->getCharacterLayout($character, $includeList);

        return response()->json($layout);
    }

    public function move(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'from_slot_uuid' => 'required|string',
            'to_slot_uuid' => 'required|string',
            'quantity' => 'nullable|integer|min:1',
        ]);

        /** @var Character $character */
        $character = $request->attributes->get('character');

        if ($character->uuid !== $characterUuid) {
            return response()->json(['error' => 'Несоответствие персонажа'], 403);
        }

        try {
            $result = $this->moveService->move(
                $character,
                $request->from_slot_uuid,
                $request->to_slot_uuid,
                $request->quantity ? (int) $request->quantity : null
            );

            $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'trade', 'equipment', 'stats']);

            return response()->json([
                'success' => true,
                'move' => $result,
                'layout' => $layout,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
