<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\PlayPanelService;
use Illuminate\Http\JsonResponse;

class PlayPanelController extends Controller
{
    public function __construct(
        private PlayPanelService $playPanelService
    ) {}

    public function show(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        return response()->json([
            'success' => true,
            'panel' => $this->playPanelService->getPanelData($character),
        ]);
    }
}
