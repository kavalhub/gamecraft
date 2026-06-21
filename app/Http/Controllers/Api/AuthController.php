<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameEvent;
use App\Models\User;
use App\Services\EventStore;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {
    }

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

                // Корреляция — все стартовые предметы связаны одним ID
                $correlationId = Str::uuid()
                    ->toString();

                // Событие регистрации
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

                // Событие получения золота
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

                // Стартовые предметы
                $starterItems = [
                    ['template_id' => 1, 'qty' => 10],
                    ['template_id' => 2, 'qty' => 5],
                ];

                foreach ($starterItems as $item) {
                    $this->inventoryService->addItem($user->id, $item['template_id'], $item['qty'], $correlationId);
                }

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

    /**
     * Вход для существующего пользователя (упрощённый, без пароля — для отладки)
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
        ]);

        $user = User::where('name', $request->username)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'user_id' => $user->id,
            'username' => $user->name,
        ]);
    }
}
