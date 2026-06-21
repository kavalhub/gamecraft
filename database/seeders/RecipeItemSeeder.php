<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecipeItemSeeder extends Seeder
{
    public function run(): void
    {
        // Находим существующий рецепт "Деревянный меч"
        $recipe = DB::table('recipes')->where('name', 'Деревянный меч')->first();

        if (!$recipe) {
            $this->command->warn('Рецепт "Деревянный меч" не найден. Сначала запусти RecipeSeeder.');
            return;
        }

        // Создаем предмет-рецепт (чертёж)
        $templateId = DB::table('item_templates')->insertGetId([
            'name' => 'Чертёж: Деревянный меч',
            'type' => 'recipe',
            'is_stackable' => false,
            'icon' => 'recipe.png',
            'disassemble_data' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Создаем инстанс чертежа у первого пользователя (ID=1)
        DB::table('item_instances')->insert([
            'template_id' => $templateId,
            'owner_id' => 1,
            'quantity' => 1,
            'durability' => 100,
            'stats' => json_encode(['recipe_id' => $recipe->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("Создан предмет-рецепт ID={$templateId} для рецепта ID={$recipe->id}");
    }
}
