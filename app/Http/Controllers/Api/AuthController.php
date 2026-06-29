<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Character;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\User;
use App\Services\EventStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
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
                    'active' => true,
                ]);

                $inventory = Storage::create([
                    'uuid' => Str::uuid()->toString(),
                    'characters_uuid' => $character->uuid,
                    'storage_type' => 'inventory',
                    'name' => 'Инвентарь',
                    'active' => true,
                ]);

                $equipment = Storage::create([
                    'uuid' => Str::uuid()->toString(),
                    'characters_uuid' => $character->uuid,
                    'storage_type' => 'equipment',
                    'name' => 'Экипировка',
                    'active' => true,
                ]);

                $bank = Storage::create([
                    'uuid' => Str::uuid()->toString(),
                    'characters_uuid' => $character->uuid,
                    'storage_type' => 'bank',
                    'name' => 'Банк',
                    'active' => true,
                ]);

                for ($i = 0; $i < 50; $i++) {
                    Slot::create([
                        'uuid' => Str::uuid()->toString(),
                        'storage_uuid' => $inventory->uuid,
                        'slot_type' => null,
                    ]);
                }

                $slotTypes = ['equipment_head', 'equipment_chest', 'equipment_legs', 'equipment_weapon', 'equipment_offhand', 'equipment_ring', 'equipment_ring', 'equipment_amulet'];
                foreach ($slotTypes as $slotType) {
                    Slot::create([
                        'uuid' => Str::uuid()->toString(),
                        'storage_uuid' => $equipment->uuid,
                        'slot_type' => $slotType,
                    ]);
                }

                for ($i = 0; $i < 100; $i++) {
                    Slot::create([
                        'uuid' => Str::uuid()->toString(),
                        'storage_uuid' => $bank->uuid,
                        'slot_type' => null,
                    ]);
                }

                $goldSlot = $inventory->slots()->first();
                Resource::create([
                    'uuid' => Str::uuid()->toString(),
                    'slot_uuid' => $goldSlot->uuid,
                    'recipe_slug' => 'gold',
                    'template_slug' => 'gold',
                    'slot_type' => 'gold',
                    'max_stack' => null,
                    'quantity' => 1000,
                ]);

                $correlationId = Str::uuid()->toString();
                $eventStore = app(EventStore::class);

                $eventStore->record(
                    'user.registered',
                    'user',
                    $user->uuid,
                    [
                        'username' => $user->name,
                        'starting_gold' => 100,
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
            ]),
        ]);
    }
}
