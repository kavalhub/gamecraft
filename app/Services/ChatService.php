<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\ChatMessage;
use Illuminate\Support\Collection;

class ChatService
{
    public function sendGeneral(Character $character, string $body): ChatMessage
    {
        $body = trim($body);
        if ($body === '') {
            throw new \RuntimeException('Сообщение не может быть пустым');
        }
        if (mb_strlen($body) > 500) {
            throw new \RuntimeException('Сообщение слишком длинное');
        }

        return ChatMessage::create([
            'channel' => 'general',
            'character_uuid' => $character->uuid,
            'character_name' => $character->name,
            'body' => $body,
        ]);
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function getRecentGeneral(int $limit = 50, ?int $afterId = null): Collection
    {
        $query = ChatMessage::query()->where('channel', 'general');

        if ($afterId) {
            return $query->where('id', '>', $afterId)
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->get();
        }

        return $query->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'uuid' => $message->uuid,
            'channel' => $message->channel,
            'character_uuid' => $message->character_uuid,
            'character_name' => $message->character_name,
            'body' => $message->body,
            'created_at' => $message->created_at->format('H:i'),
        ];
    }
}
