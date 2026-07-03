<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Character;
use App\Models\Resources;
use App\Models\User;
use App\Services\CharacterSettingsDefaultsService;
use App\Services\EventStore;
use App\Services\CharacterStatsService;
use App\Services\CurrencyService;
use App\Services\StorageProvisioningService;
use App\Services\WorldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private StorageProvisioningService $storageProvisioning,
        private CurrencyService $currencyService,
        private WorldService $worldService,
        private CharacterSettingsDefaultsService $settingsDefaults,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $user = User::create([
                    'uuid' => Str::uuid()->toString(),
                    'name' => $request->username,
                    'email' => $request->username . '@game.local',
                    'password' => Hash::make($request->password),
                ]);

                $character = Character::create([
                    'uuid' => Str::uuid()->toString(),
                    'user_uuid' => $user->uuid,
                    'character_type' => 'player',
                    'name' => $request->username,
                    'avatar' => $request->input('avatar', 'warrior'),
                    'active' => true,
                ]);

                $this->storageProvisioning->provisionDefaults($character);
                $this->currencyService->grantStartingGold($character);
                app(CharacterStatsService::class)->ensureFor($character);
                $this->worldService->ensureSpawn($character);
                $this->settingsDefaults->applyForCharacter($character);

                $correlationId = Str::uuid()->toString();
                $eventStore = app(EventStore::class);

                $eventStore->record(
                    'user.registered',
                    'user',
                    $user->uuid,
                    [
                        'username' => $user->name,
                        'starting_gold' => 1000,
                    ],
                    $user->uuid,
                    $correlationId
                );

                $eventStore->record(
                    'character.created',
                    'character',
                    $character->uuid,
                    [
                        'user_uuid' => $user->uuid,
                        'name' => $character->name,
                    ],
                    $user->uuid,
                    $correlationId
                );

                $token = $user->createToken('game')->plainTextToken;

                return response()->json([
                    'message' => 'User registered successfully',
                    'user_id' => $user->id,
                    'user_uuid' => $user->uuid,
                    'username' => $user->name,
                    'character_uuid' => $character->uuid,
                    'token' => $token,
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('name', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Неверное имя или пароль'], 401);
        }

        $user->tokens()->delete();
        $token = $user->createToken('game')->plainTextToken;

        $characters = $user->characters()->where('character_type', 'player')->get();

        return response()->json([
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'username' => $user->name,
            'token' => $token,
            'characters' => $characters->map(fn ($c) => [
                'uuid' => $c->uuid,
                'name' => $c->name,
                'avatar' => $c->avatar,
                'avatar_icon' => $c->avatarIcon(),
            ]),
        ]);
    }
}
