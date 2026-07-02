<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\EncounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EncounterController extends Controller
{
    public function __construct(
        private EncounterService $encounterService,
    ) {}

    public function catalog(string $characterUuid): JsonResponse
    {
        Character::where('uuid', $characterUuid)->firstOrFail();

        return response()->json([
            'encounters' => $this->encounterService->listEncounters(),
            'timing' => [
                'combat_log_line_ms' => (int) config('game.combat_log_line_ms', 800),
                'combat_claim_grace_ms' => (int) config('game.combat_claim_grace_ms', 60_000),
            ],
        ]);
    }

    public function resolve(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'encounter_slug' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $result = $this->encounterService->resolve($character, $request->string('encounter_slug')->toString());

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function claim(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'correlation_uuid' => 'required|uuid',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $result = $this->encounterService->claim(
                $character,
                $request->string('correlation_uuid')->toString()
            );

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
