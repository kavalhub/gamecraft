<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\CraftStationService;
use App\Services\DisassembleStationService;
use App\Services\QuestStorageService;
use App\Services\StorageLayoutService;
use App\Services\StorageMoveService;
use App\Services\StorageQuickMoveService;
use App\Services\WorldStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageController extends Controller
{
    public function __construct(
        private StorageLayoutService $layoutService,
        private StorageMoveService $moveService,
        private StorageQuickMoveService $quickMoveService,
        private CraftStationService $craftStationService,
        private DisassembleStationService $disassembleStationService,
        private QuestStorageService $questStorageService,
        private WorldStorageService $worldStorageService,
    ) {}

    public function show(Request $request, string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $include = $request->query('include', 'inventory');
        $includeList = array_filter(array_map('trim', explode(',', $include)));

        $corpseUuid = $request->query('corpse_uuid');
        $corpseUuid = is_string($corpseUuid) && $corpseUuid !== '' ? $corpseUuid : null;

        $layout = $this->layoutService->getCharacterLayout($character, $includeList, $corpseUuid);

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

            $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'trade', 'equipment', 'craft', 'disassemble', 'quest', 'stats']);

            return response()->json([
                'success' => true,
                'move' => $result,
                'layout' => $layout,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function quickMove(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'from_slot_uuid' => 'required|string',
            'intent' => 'required|string|in:equip,inventory,craft,disassemble,station_return',
            'station_mode' => 'nullable|string|in:center,material',
            'quantity' => 'nullable|integer|min:1',
        ]);

        /** @var Character $character */
        $character = $request->attributes->get('character');

        if ($character->uuid !== $characterUuid) {
            return response()->json(['error' => 'Несоответствие персонажа'], 403);
        }

        try {
            $result = $this->quickMoveService->quickMove(
                $character,
                $request->from_slot_uuid,
                $request->intent,
                $request->station_mode,
                $request->quantity ? (int) $request->quantity : null,
            );

            $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'trade', 'equipment', 'craft', 'disassemble', 'quest', 'stats']);

            return response()->json([
                'success' => true,
                'move' => $result,
                'layout' => $layout,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function clearCraftStation(Request $request, string $characterUuid): JsonResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');

        if ($character->uuid !== $characterUuid) {
            return response()->json(['error' => 'Несоответствие персонажа'], 403);
        }

        $cleared = $this->craftStationService->clearOverlays($character);
        $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'craft', 'stats']);

        return response()->json([
            'success' => true,
            'cleared' => $cleared,
            'layout' => $layout,
        ]);
    }

    public function clearDisassembleStation(Request $request, string $characterUuid): JsonResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');

        if ($character->uuid !== $characterUuid) {
            return response()->json(['error' => 'Несоответствие персонажа'], 403);
        }

        $cleared = $this->disassembleStationService->clearOverlays($character);
        $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'disassemble', 'stats']);

        return response()->json([
            'success' => true,
            'cleared' => $cleared,
            'layout' => $layout,
        ]);
    }

    public function clearQuest(Request $request, string $characterUuid): JsonResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');

        if ($character->uuid !== $characterUuid) {
            return response()->json(['error' => 'Несоответствие персонажа'], 403);
        }

        $cleared = $this->questStorageService->clearOverlays($character);
        $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'quest', 'stats']);

        return response()->json([
            'success' => true,
            'cleared' => $cleared,
            'layout' => $layout,
        ]);
    }

    public function dropToWorld(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate(['item_uuid' => 'required|string']);

        /** @var Character $character */
        $character = $request->attributes->get('character');

        if ($character->uuid !== $characterUuid) {
            return response()->json(['error' => 'Несоответствие персонажа'], 403);
        }

        try {
            $item = $this->worldStorageService->dropFromInventory($character, $request->item_uuid);
            $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'quest', 'stats']);

            return response()->json([
                'success' => true,
                'item' => $item,
                'layout' => $layout,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
