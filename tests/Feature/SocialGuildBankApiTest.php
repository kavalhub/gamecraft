<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialGuildBankApiTest extends TestCase
{
    use RefreshDatabase;

    private string $tokenA;
    private string $tokenB;
    private Character $playerA;
    private Character $playerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $regA = $this->postJson('/api/register', [
            'username' => 'hero_a',
            'password' => 'secret',
            'avatar' => 'mage',
        ]);
        $regB = $this->postJson('/api/register', [
            'username' => 'hero_b',
            'password' => 'secret',
            'avatar' => 'rogue',
        ]);

        $this->tokenA = $regA->json('token');
        $this->tokenB = $regB->json('token');
        $this->playerA = Character::where('uuid', $regA->json('character_uuid'))->firstOrFail();
        $this->playerB = Character::where('uuid', $regB->json('character_uuid'))->firstOrFail();
    }

    public function test_register_with_avatar(): void
    {
        $this->assertSame('mage', $this->playerA->avatar);
    }

    public function test_friend_request_and_accept(): void
    {
        $this->withToken($this->tokenA)
            ->postJson("/api/friends/{$this->playerA->uuid}/request", [
                'target_uuid' => $this->playerB->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $list = $this->withToken($this->tokenB)
            ->getJson("/api/friends/{$this->playerB->uuid}")
            ->assertOk();

        $this->assertCount(1, $list->json('incoming_requests'));

        $friendshipUuid = $list->json('incoming_requests.0.uuid');

        $this->withToken($this->tokenB)
            ->postJson("/api/friends/{$this->playerB->uuid}/accept", [
                'friendship_uuid' => $friendshipUuid,
            ])
            ->assertOk();

        $friends = $this->withToken($this->tokenA)
            ->getJson("/api/friends/{$this->playerA->uuid}")
            ->assertOk();

        $this->assertCount(1, $friends->json('friends'));
    }

    public function test_guild_create_join_and_chat(): void
    {
        $create = $this->withToken($this->tokenA)
            ->postJson("/api/guild/{$this->playerA->uuid}/create", [
                'name' => 'Steel Wolves',
                'emblem' => 'wolf',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $guildUuid = $create->json('guild.uuid');
        $this->assertNotEmpty($guildUuid);

        $this->withToken($this->tokenB)
            ->postJson("/api/guild/{$this->playerB->uuid}/join", [
                'guild_uuid' => $guildUuid,
            ])
            ->assertOk();

        $this->withToken($this->tokenB)
            ->postJson("/api/chat/{$this->playerB->uuid}/send", [
                'channel' => 'guild',
                'message' => 'Привет, гильдия!',
            ])
            ->assertOk()
            ->assertJsonPath('message.body', 'Привет, гильдия!');

        $messages = $this->withToken($this->tokenA)
            ->getJson("/api/chat/{$this->playerA->uuid}/messages?channel=guild")
            ->assertOk();

        $this->assertCount(1, $messages->json('messages'));
    }

    public function test_bank_storage_has_eighty_slots(): void
    {
        $response = $this->withToken($this->tokenA)
            ->getJson("/api/storage/{$this->playerA->uuid}?include=bank")
            ->assertOk();

        $bank = collect($response->json('storages'))->firstWhere('storage_type', 'bank');
        $this->assertNotNull($bank);
        $this->assertCount(80, $bank['grid_slots']);
    }

    public function test_guild_bank_has_two_hundred_slots(): void
    {
        $guild = $this->withToken($this->tokenA)
            ->postJson("/api/guild/{$this->playerA->uuid}/create", [
                'name' => 'Bank Guild',
                'emblem' => 'shield',
            ])
            ->json('guild');

        $response = $this->withToken($this->tokenA)
            ->getJson("/api/storage/{$this->playerA->uuid}?include=guild_bank")
            ->assertOk();

        $guildBank = $response->json('guild_bank');
        $this->assertNotNull($guildBank);
        $this->assertCount(200, $guildBank['grid_slots']);
    }
}
