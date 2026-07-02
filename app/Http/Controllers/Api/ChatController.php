<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\ChatService;
use App\Services\GuildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chatService,
        private GuildService $guildService,
    ) {}

    public function messages(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'channel' => 'nullable|string|in:general,guild',
            'limit' => 'nullable|integer|min:1|max:100',
            'after_id' => 'nullable|integer',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $channel = $request->input('channel', 'general');
        $limit = min((int) $request->input('limit', 50), 100);
        $afterId = $request->input('after_id') ? (int) $request->input('after_id') : null;

        $guildUuid = null;
        if ($channel === 'guild') {
            $guild = $this->guildService->getGuildForPlayer($character);
            if (!$guild) {
                return response()->json(['messages' => []]);
            }
            $guildUuid = $guild->uuid;
        }

        $messages = $this->chatService->getRecent($channel, $limit, $afterId, $guildUuid);

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->chatService->formatMessage($m))->all(),
        ]);
    }

    public function send(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string|in:general,guild',
            'message' => 'required|string|max:500',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $channel = $request->string('channel')->toString();
        $messageText = $request->string('message')->toString();

        try {
            $message = $channel === 'guild'
                ? $this->chatService->sendGuild($character, $messageText)
                : $this->chatService->sendGeneral($character, $messageText);

            return response()->json([
                'success' => true,
                'message' => $this->chatService->formatMessage($message),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
