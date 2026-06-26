<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Resource;
use App\Models\Slot;
use App\Models\Storage;
use App\Models\User;
use App\Services\EventStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|min:3|max:50|unique:users,name',
            'password' => 'required|string|min:4',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $user = User::create([
                    'uuid' => Str::uuid()->toString(),
                    'name' => $request->username,
                    'email' => $request->username . '@game.local',
                    'password' => Hash::make($request->password),
                ]);

                // Создаём персонажа
                $character = Character::create([
                    'uuid' => Str::uuid()->toString(),
                    'user_uuid' => $user->uuid,
                    'character_type' => 'player',
                    'name' => $request->username,
                    'active' => true,
                ]);

                // Создаём хранилища
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

                // Создаём слоты
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

                // Начисляем 100 золота
                $goldSlot = $inventory->slots()->first();
                Resource::create([
                    'uuid' => Str::uuid()->toString(),
                    'slot_uuid' => $goldSlot->uuid,
                    'recipe_slug' => 'gold',
                    'template_slug' => 'gold',
                    'slot_type' => 'gold',
                    'max_stack' => null,
                    'quantity' => 100,
                ]);

                $correlationId = Str::uuid()->toString();

                app(EventStore::class)->record(
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

                app(EventStore::class)->record(
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

                return response()->json([
                    'message' => 'User registered successfully',
                    'user_id' => $user->id,
                    'user_uuid' => $user->uuid,
                    'username' => $user->name,
                    'character_uuid' => $character->uuid,
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
        ]);

        $user = User::where('name', $request->username)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $characters = $user->characters()->where('character_type', 'player')->get();

        return response()->json([
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'username' => $user->name,
            'characters' => $characters->map(fn($c) => [
                'uuid' => $c->uuid,
                'name' => $c->name,
            ]),
        ]);
    }
}
