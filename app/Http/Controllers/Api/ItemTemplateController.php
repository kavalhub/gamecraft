<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemTemplate;
use Illuminate\Http\JsonResponse;

class ItemTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = ItemTemplate::query()->orderBy('name')->get();

        return response()->json([
            'templates' => $templates->map(fn ($t) => [
                'slug' => $t->slug,
                'name' => $t->name,
                'icon' => $t->icon,
                'description' => $t->description,
                'type' => $t->type,
                'max_stack' => $t->max_stack,
                'base_stats' => $t->base_stats,
                'slot_type' => $t->slot_type,
                'recipe_slug' => $t->recipe_slug,
            ]),
        ]);
    }
}
