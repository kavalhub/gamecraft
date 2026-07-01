<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    private Character $player;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'test@example.com')->first();
        $this->player = $user->characters()->where('character_type', 'player')->first();
        $this->token = $user->createToken('game')->plainTextToken;
    }

    public function test_send_and_list_general_chat_messages(): void
    {
        $send = $this->withToken($this->token)
            ->postJson("/api/chat/{$this->player->uuid}/send", [
                'channel' => 'general',
                'message' => 'Привет, мир!',
            ]);

        $send->assertOk()->assertJsonPath('message.body', 'Привет, мир!');

        $list = $this->withToken($this->token)
            ->getJson("/api/chat/{$this->player->uuid}/messages?channel=general&limit=10");

        $list->assertOk();
        $this->assertCount(1, $list->json('messages'));
        $this->assertSame('Привет, мир!', $list->json('messages.0.body'));
    }
}
