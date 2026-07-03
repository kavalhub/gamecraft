<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterWorldState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QaAccountCleanupService
{
    /**
     * @return list<string> deleted account labels
     */
    public function cleanup(bool $includeTestUser = true): array
    {
        $deleted = [];

        DB::transaction(function () use ($includeTestUser, &$deleted) {
            foreach ($this->qaUsers($includeTestUser) as $user) {
                $label = $user->name . ' (' . $user->email . ')';
                $this->deleteUser($user);
                $deleted[] = $label;
            }

            foreach ($this->qaGuilds() as $guild) {
                $this->deleteGuild($guild);
                $deleted[] = 'guild: ' . $guild->name;
            }
        });

        return $deleted;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function qaUsers(bool $includeTestUser)
    {
        return User::query()
            ->where(function ($query) use ($includeTestUser) {
                $query->where('name', 'like', 'bot_a_%')
                    ->orWhere('name', 'like', 'bot_b_%');

                if ($includeTestUser) {
                    $query->orWhere('email', 'test@example.com');
                }
            })
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Character>
     */
    private function qaGuilds()
    {
        return Character::query()
            ->where('character_type', 'guild')
            ->where('name', 'like', 'QA Guild %')
            ->get();
    }

    private function deleteUser(User $user): void
    {
        foreach ($user->characters as $character) {
            $this->deletePlayerCharacter($character);
        }

        $user->tokens()->delete();
        $user->delete();
    }

    private function deletePlayerCharacter(Character $character): void
    {
        CharacterWorldState::where('character_uuid', $character->uuid)->delete();
        DB::table('character_heartbeats')->where('character_uuid', $character->uuid)->delete();
        DB::table('guilds_members')->where('member_uuid', $character->uuid)->delete();
        $character->delete();
    }

    private function deleteGuild(Character $guild): void
    {
        DB::table('guilds_members')->where('head_uuid', $guild->uuid)->delete();
        DB::table('guild_invites')->where('guild_uuid', $guild->uuid)->delete();

        foreach ($guild->storages as $storage) {
            $storage->delete();
        }

        $guild->delete();
    }
}
