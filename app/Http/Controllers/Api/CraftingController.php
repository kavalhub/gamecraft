<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CraftingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CraftingController extends Controller
{
    public function __construct(
        private CraftingService $craftingService
    ) {}

    public function recipes(): JsonResponse
    {
        return response()->json([
            'recipes' => $this->craftingService->getRecipes(),
        ]);
    }

    public function craft(Request $request): JsonResponse
    {
        $request->validate([
            'recipe_id' => 'required|integer',
            'user_id' => 'required|integer',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        try {
            $result = $this->craftingService->craft(
                (int)$request->input('user_id'),
                (int)$request->input('recipe_id'),
                (int)$request->input('quantity', 1)
            );

            return response()->json([
                'item' => [
                    'id' => $result->id,
                    'template_id' => $result->template_id,
                    'name' => $result->template->name,
                    'type' => $result->template->type,
                    'icon' => $result->template->icon,
                    'quantity' => $result->quantity,
                    'stats' => $result->stats ?? [],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function disassemble(Request $request): JsonResponse
    {
        $request->validate([
            'instance_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);

        try {
            $materials = $this->craftingService->disassemble(
                (int)$request->input('user_id'),
                (int)$request->input('instance_id')
            );

            return response()->json([
                'message' => 'Предмет разобран',
                'materials' => $materials,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
