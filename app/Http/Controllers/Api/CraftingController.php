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

    /**
     * Получить список рецептов
     */
    public function recipes(): JsonResponse
    {
        return response()->json([
            'recipes' => $this->craftingService->getRecipes(),
        ]);
    }

    /**
     * Создать предмет по рецепту
     */
    public function craft(Request $request): JsonResponse
    {
        $request->validate([
            'recipe_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);

        try {
            $result = $this->craftingService->craft(
                (int)$request->input('user_id'),
                (int)$request->input('recipe_id')
            );

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Разобрать предмет
     */
    public function disassemble(Request $request): JsonResponse
    {
        $request->validate([
            'instance_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);

        try {
            $result = $this->craftingService->disassemble(
                (int)$request->input('user_id'),
                (int)$request->input('instance_id')
            );

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
