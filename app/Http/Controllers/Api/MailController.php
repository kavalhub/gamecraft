<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\MailService;
use App\Services\StorageLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailController extends Controller
{
    public function __construct(
        private MailService $mailService,
        private StorageLayoutService $layoutService,
    ) {}

    public function inbox(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        return response()->json([
            'messages' => $this->mailService->getInbox($character),
            'unread_count' => $this->mailService->getUnreadCount($character),
        ]);
    }

    public function show(string $characterUuid, string $messageUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $message = $this->mailService->getMessageForRecipient($character, $messageUuid);

        $include = ['post_inbox'];
        $layout = $this->layoutService->getCharacterLayout(
            $character,
            $include,
            null,
            $messageUuid,
        );

        return response()->json([
            'message' => $this->mailService->getInbox($character)
                ->firstWhere('uuid', $message->uuid),
            'layout' => $layout,
        ]);
    }

    public function composeLayout(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $this->mailService->ensureOutboxStorage($character);

        $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'post_outbox']);

        return response()->json(['layout' => $layout]);
    }

    public function searchRecipients(Request $request, string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['characters' => []]);
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        $characters = Character::query()
            ->where('character_type', 'player')
            ->where('uuid', '!=', $character->uuid)
            ->where('name', 'like', '%'.$escaped.'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['uuid', 'name']);

        return response()->json([
            'characters' => $characters->map(fn (Character $c) => [
                'uuid' => $c->uuid,
                'name' => $c->name,
            ])->values(),
        ]);
    }

    public function send(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'recipient_uuid' => 'nullable|string',
            'recipient_name' => 'nullable|string|max:64',
            'subject' => 'required|string|max:120',
            'body' => 'nullable|string|max:2000',
        ]);

        if (!$request->filled('recipient_uuid') && !$request->filled('recipient_name')) {
            return response()->json(['error' => 'Укажите получателя'], 400);
        }

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        $recipient = null;
        if ($request->filled('recipient_uuid')) {
            $recipient = Character::where('uuid', $request->recipient_uuid)->first();
        } else {
            $recipient = Character::query()
                ->where('name', $request->recipient_name)
                ->where('character_type', 'player')
                ->first();
        }

        if (!$recipient) {
            return response()->json(['error' => 'Получатель не найден'], 404);
        }

        if ($character->uuid === $recipient->uuid) {
            return response()->json(['error' => 'Нельзя отправить письмо самому себе'], 400);
        }

        try {
            $message = $this->mailService->sendMail(
                $character,
                $recipient,
                $request->subject,
                $request->body ?? '',
            );

            $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'post_outbox']);

            return response()->json([
                'success' => true,
                'message_uuid' => $message->uuid,
                'layout' => $layout,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function markRead(string $characterUuid, string $messageUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $message = $this->mailService->markRead($character, $messageUuid);

            return response()->json([
                'success' => true,
                'status' => $message->status,
                'unread_count' => $this->mailService->getUnreadCount($character),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function claimAll(string $characterUuid, string $messageUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $message = $this->mailService->claimAll($character, $messageUuid);
            $layout = $this->layoutService->getCharacterLayout($character, ['inventory', 'post_inbox'], null, $messageUuid);

            return response()->json([
                'success' => true,
                'status' => $message->status,
                'layout' => $layout,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy(string $characterUuid, string $messageUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $this->mailService->deleteMessage($character, $messageUuid);

            return response()->json([
                'success' => true,
                'unread_count' => $this->mailService->getUnreadCount($character),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
