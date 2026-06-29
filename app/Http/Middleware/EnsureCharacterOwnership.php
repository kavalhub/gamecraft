<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Character;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCharacterOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $characterUuid = $request->route('characterUuid');

        if (!$characterUuid) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Требуется авторизация'], 401);
        }

        $character = Character::where('uuid', $characterUuid)->first();

        if (!$character || $character->user_uuid !== $user->uuid) {
            return response()->json(['error' => 'Доступ запрещён'], 403);
        }

        $request->attributes->set('character', $character);

        return $next($request);
    }
}
