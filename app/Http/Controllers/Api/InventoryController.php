<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemInstance;
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

        $inventory = ItemInstance::where('owner_id', $userId)
            ->with('template')
            ->get()
            ->map(fn($item) => [
                'instance_id' => $item->id,
                'template_id' => $item->template_id,
                'name' => $item->template->name,
                'type' => $item->template->type,
                'icon' => $item->template->icon,
                'quantity' => $item->quantity,
                'description' => $item->template->description,
                'stats' => $item->stats ?? [],
            ]);

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
