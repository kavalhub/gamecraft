<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameEvent;
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
                    'name' => $request->username,
                    'email' => $request->username . '@game.local',
                    'password' => Hash::make($request->password),
                    'gold' => 100,
                ]);

                $correlationId = Str::uuid()->toString();

                app(EventStore::class)->record(
                    GameEvent::USER_REGISTERED,
                    'user',
                    $user->id,
                    [
                        'username' => $user->name,
                        'starting_gold' => 100,
                    ],
                    $user->id,
                    $correlationId
                );

                app(EventStore::class)->record(
                    GameEvent::USER_GOLD_CHANGED,
                    'user',
                    $user->id,
                    [
                        'delta' => 100,
                        'new_balance' => 100,
                        'reason' => 'starting_bonus',
                    ],
                    $user->id,
                    $correlationId
                );

                return response()->json([
                    'message' => 'User registered successfully',
                    'user_id' => $user->id,
                    'username' => $user->name,
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

        return response()->json([
            'user_id' => $user->id,
            'username' => $user->name,
        ]);
    }
}
