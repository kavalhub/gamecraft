<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\GuildInvite;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuildService
{
    public function __construct(
        private StorageProvisioningService $provisioningService,
        private EventStore $eventStore,
    ) {}

    public function getMembership(Character $player): ?object
    {
        return DB::table('guilds_members')
            ->where('member_uuid', $player->uuid)
            ->where('active', true)
            ->first();
    }

    public function getGuildForPlayer(Character $player): ?Character
    {
        $membership = $this->getMembership($player);
        if (!$membership) {
            return null;
        }

        return Character::where('uuid', $membership->head_uuid)
            ->where('character_type', 'guild')
            ->first();
    }

    public function assertMember(Character $player, string $guildUuid): void
    {
        if (!$this->isMember($player, $guildUuid)) {
            throw new \RuntimeException('Вы не состоите в этой гильдии');
        }
    }

    public function isMember(Character $player, string $guildUuid): bool
    {
        return DB::table('guilds_members')
            ->where('head_uuid', $guildUuid)
            ->where('member_uuid', $player->uuid)
            ->where('active', true)
            ->exists();
    }

    public function create(Character $leader, string $name, string $emblem): Character
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 40) {
            throw new \RuntimeException('Название гильдии должно быть от 1 до 40 символов');
        }

        $this->assertValidEmblem($emblem);

        if ($this->getMembership($leader)) {
            throw new \RuntimeException('Вы уже состоите в гильдии');
        }

        if (Character::where('character_type', 'guild')->where('name', $name)->exists()) {
            throw new \RuntimeException('Гильдия с таким названием уже существует');
        }

        return DB::transaction(function () use ($leader, $name, $emblem) {
            $guild = Character::create([
                'uuid' => Str::uuid()->toString(),
                'user_uuid' => null,
                'character_type' => 'guild',
                'name' => $name,
                'emblem' => $emblem,
                'active' => true,
            ]);

            $this->provisioningService->grantStorage($guild, 'guild_bank');

            DB::table('guilds_members')->insert([
                'head_uuid' => $guild->uuid,
                'member_uuid' => $leader->uuid,
                'role_type' => 'leader',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->eventStore->record(
                'guild.created',
                'guild',
                $guild->uuid,
                [
                    'name' => $guild->name,
                    'emblem' => $guild->emblem,
                    'leader_uuid' => $leader->uuid,
                ],
                $leader->uuid,
            );

            return $guild;
        });
    }

    public function join(Character $player, string $guildUuid): Character
    {
        if ($this->getMembership($player)) {
            throw new \RuntimeException('Вы уже состоите в гильдии');
        }

        $guild = Character::where('uuid', $guildUuid)
            ->where('character_type', 'guild')
            ->firstOrFail();

        DB::table('guilds_members')->insert([
            'head_uuid' => $guild->uuid,
            'member_uuid' => $player->uuid,
            'role_type' => 'member',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        GuildInvite::where('guild_uuid', $guild->uuid)
            ->where('target_uuid', $player->uuid)
            ->where('status', GuildInvite::STATUS_PENDING)
            ->update(['status' => GuildInvite::STATUS_ACCEPTED]);

        return $guild;
    }

    public function leave(Character $player): void
    {
        $membership = $this->getMembership($player);
        if (!$membership) {
            throw new \RuntimeException('Вы не состоите в гильдии');
        }

        $guildUuid = $membership->head_uuid;
        $isLeader = $membership->role_type === 'leader';

        DB::transaction(function () use ($player, $guildUuid, $isLeader) {
            DB::table('guilds_members')
                ->where('head_uuid', $guildUuid)
                ->where('member_uuid', $player->uuid)
                ->update(['active' => false, 'updated_at' => now()]);

            if (!$isLeader) {
                return;
            }

            $nextLeader = DB::table('guilds_members')
                ->where('head_uuid', $guildUuid)
                ->where('active', true)
                ->where('member_uuid', '!=', $player->uuid)
                ->orderBy('id')
                ->first();

            if ($nextLeader) {
                DB::table('guilds_members')
                    ->where('id', $nextLeader->id)
                    ->update(['role_type' => 'leader', 'updated_at' => now()]);

                return;
            }

            $guild = Character::where('uuid', $guildUuid)->first();
            if ($guild) {
                $guild->update(['active' => false]);
            }
        });
    }

    public function invite(Character $inviter, Character $target): GuildInvite
    {
        if ($inviter->uuid === $target->uuid) {
            throw new \RuntimeException('Нельзя пригласить себя');
        }

        if (!$target->isPlayer()) {
            throw new \RuntimeException('Можно пригласить только игрока');
        }

        $membership = $this->getMembership($inviter);
        if (!$membership) {
            throw new \RuntimeException('Вы не состоите в гильдии');
        }

        if ($this->getMembership($target)) {
            throw new \RuntimeException('Игрок уже состоит в гильдии');
        }

        $guildUuid = $membership->head_uuid;

        $existing = GuildInvite::where('guild_uuid', $guildUuid)
            ->where('target_uuid', $target->uuid)
            ->where('status', GuildInvite::STATUS_PENDING)
            ->first();

        if ($existing) {
            throw new \RuntimeException('Приглашение уже отправлено');
        }

        return GuildInvite::create([
            'guild_uuid' => $guildUuid,
            'inviter_uuid' => $inviter->uuid,
            'target_uuid' => $target->uuid,
            'status' => GuildInvite::STATUS_PENDING,
        ]);
    }

    public function declineInvite(Character $player, string $inviteUuid): void
    {
        $invite = GuildInvite::where('uuid', $inviteUuid)->firstOrFail();

        if ($invite->target_uuid !== $player->uuid) {
            throw new \RuntimeException('Нельзя отклонить это приглашение');
        }

        $invite->update(['status' => GuildInvite::STATUS_DECLINED]);
    }

    /**
     * @return Collection<int, Character>
     */
    public function listPublicGuilds(): Collection
    {
        return Character::query()
            ->where('character_type', 'guild')
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMembers(Character $guild): array
    {
        $rows = DB::table('guilds_members')
            ->where('head_uuid', $guild->uuid)
            ->where('active', true)
            ->get();

        $memberUuids = $rows->pluck('member_uuid');
        $characters = Character::whereIn('uuid', $memberUuids)->get()->keyBy('uuid');

        return $rows->map(function ($row) use ($characters) {
            $member = $characters->get($row->member_uuid);

            return [
                'uuid' => $row->member_uuid,
                'name' => $member?->name ?? 'Игрок',
                'avatar_icon' => $member?->avatarIcon() ?? '🧙',
                'role' => $row->role_type,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPendingInvitesFor(Character $player): array
    {
        return GuildInvite::query()
            ->where('target_uuid', $player->uuid)
            ->where('status', GuildInvite::STATUS_PENDING)
            ->with(['guild', 'inviter'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (GuildInvite $invite) => [
                'uuid' => $invite->uuid,
                'guild_uuid' => $invite->guild_uuid,
                'guild_name' => $invite->guild?->name,
                'guild_emblem_icon' => $invite->guild?->emblemIcon(),
                'inviter_name' => $invite->inviter?->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function formatGuildState(Character $player): ?array
    {
        $guild = $this->getGuildForPlayer($player);
        if (!$guild) {
            return null;
        }

        $membership = $this->getMembership($player);

        return [
            'uuid' => $guild->uuid,
            'name' => $guild->name,
            'emblem' => $guild->emblem,
            'emblem_icon' => $guild->emblemIcon(),
            'role' => $membership?->role_type ?? 'member',
            'members' => $this->getMembers($guild),
            'member_count' => count($this->getMembers($guild)),
        ];
    }

    public function assertValidEmblem(string $emblem): void
    {
        if (!isset(config('game.guild_emblems', [])[$emblem])) {
            throw new \RuntimeException('Недопустимая эмблема гильдии');
        }
    }

    public function assertValidAvatar(string $avatar): void
    {
        if (!isset(config('game.avatars', [])[$avatar])) {
            throw new \RuntimeException('Недопустимая аватарка');
        }
    }
}
