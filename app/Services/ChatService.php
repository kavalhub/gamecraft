<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\ChatMessage;
use Illuminate\Support\Collection;

class ChatService
{
    public function __construct(
        private GuildService $guildService,
    ) {}

    public function sendGeneral(Character $character, string $body): ChatMessage
    {
        return $this->send($character, 'general', null, $body);
    }

    public function sendGuild(Character $character, string $body): ChatMessage
    {
        $guild = $this->guildService->getGuildForPlayer($character);
        if (!$guild) {
            throw new \RuntimeException('Вы не состоите в гильдии');
        }

        return $this->send($character, 'guild', $guild->uuid, $body);
    }

    public function send(Character $character, string $channel, ?string $guildUuid, string $body): ChatMessage
    {
        $body = trim($body);
        if ($body === '') {
            throw new \RuntimeException('Сообщение не может быть пустым');
        }
        if (mb_strlen($body) > 500) {
            throw new \RuntimeException('Сообщение слишком длинное');
        }

        return ChatMessage::create([
            'channel' => $channel,
            'guild_uuid' => $guildUuid,
            'character_uuid' => $character->uuid,
            'character_name' => $character->name,
            'body' => $body,
        ]);
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function getRecent(string $channel, int $limit = 50, ?int $afterId = null, ?string $guildUuid = null): Collection
    {
        $query = ChatMessage::query()->where('channel', $channel);

        if ($channel === 'guild' && $guildUuid) {
            $query->where('guild_uuid', $guildUuid);
        }

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
     * @return Collection<int, ChatMessage>
     */
    public function getRecentGeneral(int $limit = 50, ?int $afterId = null): Collection
    {
        return $this->getRecent('general', $limit, $afterId);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatMessage(ChatMessage $message): array
    {
        $character = Character::where('uuid', $message->character_uuid)->first();

        return [
            'id' => $message->id,
            'uuid' => $message->uuid,
            'channel' => $message->channel,
            'guild_uuid' => $message->guild_uuid,
            'character_uuid' => $message->character_uuid,
            'character_name' => $message->character_name,
            'avatar_icon' => $character?->avatarIcon() ?? '🧙',
            'body' => $message->body,
            'created_at' => $message->created_at->format('H:i'),
        ];
    }
}
