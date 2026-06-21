<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Пользователь
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'gold' => 1000,
        ]);

        // Шаблоны предметов (материалы, экипировка, чертежи)
        $this->call(ItemTemplateSeeder::class);

        // Рецепты
        $this->call(RecipeSeeder::class);

        // Компоненты рецептов
        $this->call(RecipeItemSeeder::class);

        // Стартовый инвентарь для тестов
        $this->call(DebugInventorySeeder::class);
    }
}
