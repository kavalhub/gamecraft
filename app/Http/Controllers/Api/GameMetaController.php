<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class GameMetaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'avatars' => config('game.avatars', []),
            'guild_emblems' => config('game.guild_emblems', []),
        ]);
    }
}
