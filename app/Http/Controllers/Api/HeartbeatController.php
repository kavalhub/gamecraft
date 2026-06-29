<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CharacterHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function ping(Request $request, string $characterUuid): JsonResponse
    {
        CharacterHeartbeat::ping($characterUuid);
        return response()->json(['success' => true]);
    }

    public function online(): JsonResponse
    {
        $online = CharacterHeartbeat::getOnline(5);
        
        return response()->json([
            'characters' => $online->map(fn($c) => [
                'uuid' => $c->uuid,
                'name' => $c->name,
            ]),
        ]);
    }
}
