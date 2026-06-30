<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\QuestService;
use App\Services\StorageLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestController extends Controller
{
    public function __construct(
        private QuestService $questService,
        private StorageLayoutService $layoutService,
    ) {}

    public function index(Request $request, string $characterUuid): JsonResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');

        if ($character->uuid !== $characterUuid) {
            return response()->json(['error' => 'Несоответствие персонажа'], 403);
        }

        return response()->json($this->questService->listForCharacter($character));
    }

    public function show(Request $request, string $characterUuid, string $questSlug): JsonResponse
    {
        /** @var Character $character */
        $character = $request->attributes->get('character');

        if ($character->uuid !== $characterUuid) {
            return response()->json(['error' => 'Несоответствие персонажа'], 403);
        }

        try {
            $session = $this->questService->getSession($character, $questSlug);
            $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'quest', 'stats']);

            return response()->json(array_merge($session, [
                'layout' => $layout,
            ]));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function accept(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate(['quest_slug' => 'required|string']);

        /** @var Character $character */
        $character = $request->attributes->get('character');

        if ($character->uuid !== $characterUuid) {
            return response()->json(['error' => 'Несоответствие персонажа'], 403);
        }

        try {
            $characterQuest = $this->questService->accept($character, $request->quest_slug);

            return response()->json([
                'success' => true,
                'character_quest' => $characterQuest,
                'quests' => $this->questService->listForCharacter($character),
                'layout' => $this->layoutService->getCharacterLayout($character, ['inventory', 'quest', 'stats']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function turnIn(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate(['quest_slug' => 'required|string']);

        /** @var Character $character */
        $character = $request->attributes->get('character');

        if ($character->uuid !== $characterUuid) {
            return response()->json(['error' => 'Несоответствие персонажа'], 403);
        }

        try {
            $characterQuest = $this->questService->turnIn($character, $request->quest_slug);

            return response()->json([
                'success' => true,
                'character_quest' => $characterQuest,
                'quests' => $this->questService->listForCharacter($character),
                'layout' => $this->layoutService->getCharacterLayout($character, ['inventory', 'quest', 'stats']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
