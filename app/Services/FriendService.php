<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\Friendship;

class FriendService
{
    public function listFor(Character $character): array
    {
        $friendships = Friendship::query()
            ->where(function ($q) use ($character) {
                $q->where('requester_uuid', $character->uuid)
                    ->orWhere('addressee_uuid', $character->uuid);
            })
            ->with(['requester', 'addressee'])
            ->orderByDesc('updated_at')
            ->get();

        $friends = [];
        $incoming = [];
        $outgoing = [];

        foreach ($friendships as $friendship) {
            if ($friendship->status === Friendship::STATUS_ACCEPTED) {
                $other = $friendship->requester_uuid === $character->uuid
                    ? $friendship->addressee
                    : $friendship->requester;
                if ($other) {
                    $friends[] = $this->formatCharacter($other);
                }
                continue;
            }

            if ($friendship->status !== Friendship::STATUS_PENDING) {
                continue;
            }

            if ($friendship->addressee_uuid === $character->uuid) {
                $incoming[] = [
                    'uuid' => $friendship->uuid,
                    'character' => $this->formatCharacter($friendship->requester),
                ];
            } else {
                $outgoing[] = [
                    'uuid' => $friendship->uuid,
                    'character' => $this->formatCharacter($friendship->addressee),
                ];
            }
        }

        return [
            'friends' => $friends,
            'incoming_requests' => $incoming,
            'outgoing_requests' => $outgoing,
        ];
    }

    public function request(Character $requester, Character $addressee): Friendship
    {
        if ($requester->uuid === $addressee->uuid) {
            throw new \RuntimeException('Нельзя добавить себя в друзья');
        }

        if (!$addressee->isPlayer()) {
            throw new \RuntimeException('Можно добавить только игрока');
        }

        $existing = $this->findBetween($requester->uuid, $addressee->uuid);
        if ($existing) {
            if ($existing->status === Friendship::STATUS_ACCEPTED) {
                throw new \RuntimeException('Игрок уже в списке друзей');
            }

            if ($existing->status === Friendship::STATUS_PENDING) {
                if ($existing->requester_uuid === $addressee->uuid) {
                    $existing->update(['status' => Friendship::STATUS_ACCEPTED]);

                    return $existing->fresh();
                }

                throw new \RuntimeException('Заявка уже отправлена');
            }
        }

        return Friendship::create([
            'requester_uuid' => $requester->uuid,
            'addressee_uuid' => $addressee->uuid,
            'status' => Friendship::STATUS_PENDING,
        ]);
    }

    public function accept(Character $character, string $friendshipUuid): Friendship
    {
        $friendship = Friendship::where('uuid', $friendshipUuid)->firstOrFail();

        if ($friendship->addressee_uuid !== $character->uuid) {
            throw new \RuntimeException('Нельзя принять эту заявку');
        }

        if ($friendship->status !== Friendship::STATUS_PENDING) {
            throw new \RuntimeException('Заявка уже обработана');
        }

        $friendship->update(['status' => Friendship::STATUS_ACCEPTED]);

        return $friendship->fresh();
    }

    public function remove(Character $character, string $otherUuid): void
    {
        $friendship = $this->findBetween($character->uuid, $otherUuid);
        if (!$friendship) {
            throw new \RuntimeException('Связь не найдена');
        }

        $friendship->delete();
    }

    public function areFriends(string $uuidA, string $uuidB): bool
    {
        $friendship = $this->findBetween($uuidA, $uuidB);

        return $friendship !== null && $friendship->status === Friendship::STATUS_ACCEPTED;
    }

    private function findBetween(string $uuidA, string $uuidB): ?Friendship
    {
        return Friendship::query()
            ->where(function ($q) use ($uuidA, $uuidB) {
                $q->where('requester_uuid', $uuidA)->where('addressee_uuid', $uuidB);
            })
            ->orWhere(function ($q) use ($uuidA, $uuidB) {
                $q->where('requester_uuid', $uuidB)->where('addressee_uuid', $uuidA);
            })
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCharacter(Character $character): array
    {
        return [
            'uuid' => $character->uuid,
            'name' => $character->name,
            'avatar' => $character->avatar,
            'avatar_icon' => $character->avatarIcon(),
        ];
    }
}
