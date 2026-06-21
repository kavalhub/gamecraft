<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json(['error' => 'user_id required'], 400);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $inventory = $this->inventoryService->getInventory((int)$userId);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->name,
                'gold' => $user->gold,
            ],
            'inventory' => $inventory,
        ]);
    }
}
