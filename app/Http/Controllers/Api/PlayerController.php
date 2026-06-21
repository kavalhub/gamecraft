<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    /**
     * Heartbeat — клиент пингует раз в 10 секунд
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $userId = $request->input('user_id');
        if (!$userId) {
            return response()->json(['error' => 'user_id required'], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->update(['last_seen_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * Список онлайн-игроков (кроме себя)
     */
    public function online(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        if (!$userId) {
            return response()->json(['error' => 'user_id required'], 400);
        }

        // Онлайн = last_seen_at в последние 30 секунд
        $onlinePlayers = User::where('id', '!=', $userId)
            ->where('last_seen_at', '>=', now()->subSeconds(30))
            ->select('id', 'name', 'gold', 'last_seen_at')
            ->orderBy('name')
            ->get()
            ->map(fn(User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'gold' => $u->gold,
                'online_for' => now()->diffInSeconds($u->last_seen_at),
            ])
            ->toArray();

        return response()->json([
            'players' => $onlinePlayers,
        ]);
    }
}
