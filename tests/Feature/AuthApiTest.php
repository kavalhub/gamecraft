<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_register_returns_token_and_character(): void
    {
        $response = $this->postJson('/api/register', [
            'username' => 'newhero',
            'password' => 'secret',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'character_uuid', 'username']);

        $this->assertDatabaseHas('users', ['name' => 'newhero']);
    }

    public function test_login_requires_valid_password(): void
    {
        $this->postJson('/api/register', [
            'username' => 'hero1',
            'password' => 'secret',
        ]);

        $this->postJson('/api/login', [
            'username' => 'hero1',
            'password' => 'wrong',
        ])->assertUnauthorized();

        $response = $this->postJson('/api/login', [
            'username' => 'hero1',
            'password' => 'secret',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'characters']);
    }

    public function test_protected_routes_require_token(): void
    {
        $register = $this->postJson('/api/register', [
            'username' => 'hero2',
            'password' => 'secret',
        ]);

        $characterUuid = $register->json('character_uuid');

        $this->getJson("/api/inventory/{$characterUuid}")
            ->assertUnauthorized();

        $token = $register->json('token');

        $this->withToken($token)
            ->getJson("/api/inventory/{$characterUuid}")
            ->assertOk()
            ->assertJsonPath('character_uuid', $characterUuid);
    }

    public function test_user_cannot_access_other_characters_inventory(): void
    {
        $user1 = $this->postJson('/api/register', [
            'username' => 'owner',
            'password' => 'secret',
        ]);

        $user2 = $this->postJson('/api/register', [
            'username' => 'intruder',
            'password' => 'secret',
        ]);

        $victimUuid = $user1->json('character_uuid');
        $intruderToken = $user2->json('token');

        $this->withToken($intruderToken)
            ->getJson("/api/inventory/{$victimUuid}")
            ->assertForbidden();
    }
}
