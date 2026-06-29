<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Services\CraftingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CraftingController extends Controller
{
    public function __construct(
        private CraftingService $craftingService
    ) {}

    public function recipes(string $characterUuid): JsonResponse
    {
        $character = Character::where('uuid', $characterUuid)->firstOrFail();
        $recipes = $this->craftingService->getAvailableRecipes($character);

        return response()->json([
            'recipes' => $recipes,
        ]);
    }

    public function craftResource(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'recipe_slug' => 'required|string',
            'times' => 'integer|min:1',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $result = $this->craftingService->craftResource(
                $character,
                $request->recipe_slug,
                $request->input('times', 1)
            );

            return response()->json([
                'success' => true,
                'recipe_slug' => $result['recipe_slug'],
                'result_template_slug' => $result['result_template_slug'],
                'result_quantity' => $result['result_quantity'],
                'times' => $result['times'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function createBlueprint(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'recipe_slug' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $blueprint = $this->craftingService->createBlueprint(
                $character,
                $request->recipe_slug
            );

            return response()->json([
                'success' => true,
                'blueprint' => [
                    'uuid' => $blueprint->uuid,
                    'template_slug' => $blueprint->template_slug,
                    'stage' => $blueprint->stage,
                    'recipe_slug' => $blueprint->recipe_slug,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function craftItem(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'recipe_slug' => 'required|string',
            'blueprint_uuid' => 'required|string',
            'custom_name' => 'nullable|string|max:255',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $item = $this->craftingService->craftItem(
                $character,
                $request->recipe_slug,
                $request->blueprint_uuid,
                $request->custom_name
            );

            return response()->json([
                'success' => true,
                'item' => [
                    'uuid' => $item->uuid,
                    'template_slug' => $item->template_slug,
                    'stage' => $item->stage,
                    'custom_name' => $item->custom_name,
                    'stats' => $item->stats,
                    'materials_used' => $item->materials_used,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function disassemble(Request $request, string $characterUuid): JsonResponse
    {
        $request->validate([
            'item_uuid' => 'required|string',
        ]);

        $character = Character::where('uuid', $characterUuid)->firstOrFail();

        try {
            $result = $this->craftingService->disassembleItem(
                $character,
                $request->item_uuid
            );

            return response()->json([
                'success' => true,
                'blueprint' => [
                    'uuid' => $result['item']->uuid,
                    'template_slug' => $result['item']->template_slug,
                    'stage' => $result['item']->stage,
                ],
                'returned_resources' => $result['returned_resources'],
                'formula_description' => $result['formula']->description,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
