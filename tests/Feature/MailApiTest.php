<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_endpoint_returns_messages(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $user = User::where('email', 'test@example.com')->first();
        $character = $user->characters()->where('character_type', 'player')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/mail/' . $character->uuid . '/inbox');

        $response->assertOk()
            ->assertJsonStructure(['messages', 'unread_count']);
    }

    public function test_send_mail_via_api(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $senderUser = User::where('email', 'test@example.com')->first();
        $sender = $senderUser->characters()->where('character_type', 'player')->first();

        $recipientUser = User::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'R',
            'email' => 'r@example.com',
            'password' => bcrypt('password'),
        ]);
        $recipient = Character::create([
            'uuid' => Str::uuid()->toString(),
            'user_uuid' => $recipientUser->uuid,
            'character_type' => 'player',
            'name' => 'R Char',
            'active' => true,
        ]);

        Sanctum::actingAs($senderUser);

        $response = $this->postJson('/api/mail/' . $sender->uuid . '/send', [
            'recipient_uuid' => $recipient->uuid,
            'subject' => 'Привет',
            'body' => 'Текст',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_search_recipients_by_name(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $senderUser = User::where('email', 'test@example.com')->first();
        $sender = $senderUser->characters()->where('character_type', 'player')->first();

        Character::create([
            'uuid' => Str::uuid()->toString(),
            'user_uuid' => $senderUser->uuid,
            'character_type' => 'player',
            'name' => 'Offline Buddy',
            'active' => true,
        ]);

        Sanctum::actingAs($senderUser);

        $response = $this->getJson('/api/mail/' . $sender->uuid . '/recipients?q=offline');

        $response->assertOk()
            ->assertJsonPath('characters.0.name', 'Offline Buddy');
    }
}
