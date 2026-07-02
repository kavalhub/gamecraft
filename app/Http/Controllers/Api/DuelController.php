<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\DuelOffer;
use App\Services\DuelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DuelController extends Controller
{
    public function __construct(
        private DuelService $duelService,
    ) {}

    public function current(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $duel = $this->duelService->getPendingFor($character);

        return response()->json([
            'duel' => $duel ? $this->formatDuel($duel, $character) : null,
        ]);
    }

    public function challenge(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'opponent_uuid' => 'required|string|exists:characters,uuid',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $opponent = Character::where('uuid', $request->string('opponent_uuid')->toString())->firstOrFail();

        try {
            $duel = $this->duelService->challenge($character, $opponent);

            return response()->json([
                'success' => true,
                'duel' => $this->formatDuel($duel, $character),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function accept(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'duel_uuid' => 'required|string|exists:duel_offers,uuid',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $duel = DuelOffer::where('uuid', $request->string('duel_uuid')->toString())->firstOrFail();

        try {
            $result = $this->duelService->accept($character, $duel);

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function decline(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'duel_uuid' => 'required|string|exists:duel_offers,uuid',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $duel = DuelOffer::where('uuid', $request->string('duel_uuid')->toString())->firstOrFail();

        try {
            $duel = $this->duelService->decline($character, $duel);

            return response()->json([
                'success' => true,
                'duel' => $this->formatDuel($duel, $character),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function formatDuel(DuelOffer $duel, Character $viewer): array
    {
        $isChallenger = $duel->challenger_uuid === $viewer->uuid;
        $foe = $isChallenger ? $duel->opponent : $duel->challenger;

        return [
            'uuid' => $duel->uuid,
            'status' => $duel->status,
            'challenger_uuid' => $duel->challenger_uuid,
            'challenger_name' => $duel->challenger?->name,
            'opponent_uuid' => $duel->opponent_uuid,
            'opponent_name' => $duel->opponent?->name,
            'is_challenger' => $isChallenger,
            'foe_uuid' => $foe?->uuid,
            'foe_name' => $foe?->name,
            'correlation_uuid' => $duel->correlation_uuid,
        ];
    }
}
