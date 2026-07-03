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
            'world' => [
                'max_speed' => config('game.world_max_speed'),
                'max_step' => config('game.world_max_step'),
                'step_size' => config('game.world_step_size'),
                'interact_radius' => config('game.world_interact_radius'),
                'portal_radius' => config('game.world_portal_radius'),
                'nearby_radius' => config('game.world_nearby_radius'),
            ],
        ]);
    }
}
