<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        // Рецепт: Деревянный меч (template_id=3) из 2x Дерево (template_id=1) + 1x Железная руда (template_id=2)
        $recipeId = DB::table('recipes')->insertGetId([
            'result_template_id' => 3,
            'result_quantity' => 1,
            'name' => 'Деревянный меч',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('recipe_components')->insert([
            [
                'recipe_id' => $recipeId,
                'template_id' => 1, // Дерево
                'quantity' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'recipe_id' => $recipeId,
                'template_id' => 2, // Железная руда
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
