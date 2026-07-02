<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CharacterHeartbeat;
use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function __construct(
        private PresenceService $presenceService
    ) {}

    public function ping(Request $request, string $characterUuid): JsonResponse
    {
        $this->presenceService->markOnline($characterUuid);

        return response()->json(['success' => true]);
    }

    public function online(): JsonResponse
    {
        $online = CharacterHeartbeat::getOnline(5);
        
        return response()->json([
            'characters' => $online->map(fn($c) => [
                'uuid' => $c->uuid,
                'name' => $c->name,
                'avatar' => $c->avatar ?? 'warrior',
                'avatar_icon' => method_exists($c, 'avatarIcon') ? $c->avatarIcon() : '🧙',
            ]),
        ]);
    }
}
