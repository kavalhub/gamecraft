<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\FriendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function __construct(
        private FriendService $friendService,
    ) {}

    public function index(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        return response()->json($this->friendService->listFor($character));
    }

    public function request(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'target_uuid' => 'required|string|exists:characters,uuid',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $target = Character::where('uuid', $request->string('target_uuid')->toString())->firstOrFail();

        try {
            $friendship = $this->friendService->request($character, $target);

            return response()->json([
                'success' => true,
                'friendship_uuid' => $friendship->uuid,
                'status' => $friendship->status,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function accept(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'friendship_uuid' => 'required|string|exists:friendships,uuid',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $friendship = $this->friendService->accept(
                $character,
                $request->string('friendship_uuid')->toString()
            );

            return response()->json([
                'success' => true,
                'friendship_uuid' => $friendship->uuid,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function remove(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'target_uuid' => 'required|string|exists:characters,uuid',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $this->friendService->remove($character, $request->string('target_uuid')->toString());

            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
