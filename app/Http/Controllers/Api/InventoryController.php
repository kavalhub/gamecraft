<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemTemplate;
use App\Models\ResourceBalance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
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

        // Предметы (equipment, blueprint)
        $items = Item::where('owner_id', $userId)
            ->with('template')
            ->get()
            ->map(fn($item) => [
                'instance_id' => $item->id,
                'template_id' => $item->template_id,
                'name' => $item->template->name,
                'type' => $item->template->type,
                'icon' => $item->template->icon,
                'is_stackable' => $item->template->is_stackable,
                'quantity' => $item->template->is_stackable ? $item->quantity : null,
                'description' => $item->template->description,
                'stats' => $item->stats ?? [],
            ]);

        // Ресурсы (material, consumable)
        $resources = ResourceBalance::where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->with('template')
            ->get()
            ->map(fn($balance) => [
                'instance_id' => null,
                'template_id' => $balance->template_id,
                'name' => $balance->template->name,
                'type' => $balance->template->type,
                'icon' => $balance->template->icon,
                'is_stackable' => true,
                'quantity' => $balance->quantity,
                'description' => $balance->template->description,
                'stats' => [],
            ]);

        // Объединяем: сначала ресурсы, потом предметы
        $inventory = $resources->merge($items)->values()->toArray();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->name,
                'gold' => $user->getGold(),
            ],
            'inventory' => $inventory,
        ]);
    }
}
