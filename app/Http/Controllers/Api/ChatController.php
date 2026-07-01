<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chatService
    ) {}

    public function messages(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'channel' => 'nullable|string|in:general',
            'limit' => 'nullable|integer|min:1|max:100',
            'after_id' => 'nullable|integer',
        ]);

        Character::where('uuid', $characterUuid)->firstOrFail();

        $limit = min((int) $request->input('limit', 50), 100);
        $afterId = $request->input('after_id') ? (int) $request->input('after_id') : null;

        $messages = $this->chatService->getRecentGeneral($limit, $afterId);

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->chatService->formatMessage($m))->all(),
        ]);
    }

    public function send(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string|in:general',
            'message' => 'required|string|max:500',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $message = $this->chatService->sendGeneral($character, $request->input('message'));

            return response()->json([
                'success' => true,
                'message' => $this->chatService->formatMessage($message),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
