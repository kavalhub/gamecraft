<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ItemInstance;
use App\Models\ItemTemplate;
use App\Models\Recipe;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

class DebugInventorySeeder extends Seeder
{
    private InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function run(): void
    {
        // Находим первого пользователя (или создаем тестового)
        $user = User::first();

        if (!$user) {
            $this->command->warn('Пользователь не найден. Создаем тестового...');
            $user = User::create([
                'name' => 'debug_player',
                'email' => 'debug@game.local',
                'password' => bcrypt('debug123'),
                'gold' => 10000,
            ]);
            $this->command->info("Создан пользователь: debug_player (ID: {$user->id})");
        }

        $userId = $user->id;
        $this->command->info("Обновляем инвентарь для пользователя ID: {$userId}");

        // Проверяем флаг --fresh
        $fresh = in_array('--fresh', $_SERVER['argv'] ?? []);

        if ($fresh) {
            $this->command->warn('Режим --fresh: очищаем инвентарь...');
            ItemInstance::where('owner_id', $userId)->delete();
        }

        // 1. Добавляем стартовые материалы
        $materials = [
            ['name' => 'Дерево', 'qty' => 100],
            ['name' => 'Железная руда', 'qty' => 50],
        ];

        foreach ($materials as $mat) {
            $template = ItemTemplate::where('name', $mat['name'])->first();

            if (!$template) {
                $this->command->warn("Материал '{$mat['name']}' не найден в БД");
                continue;
            }

            $this->inventoryService->addItem($userId, $template->id, $mat['qty']);
            $this->command->info("✅ Добавлено: {$mat['name']} x{$mat['qty']}");
        }

        // 2. Добавляем чертежи всех рецептов
        $recipes = Recipe::all();

        if ($recipes->isEmpty()) {
            $this->command->warn('Рецепты не найдены. Сначала запусти RecipeSeeder');
            return;
        }

        foreach ($recipes as $recipe) {
            // Проверяем, есть ли уже чертеж для этого рецепта
            $existingBlueprint = ItemInstance::where('owner_id', $userId)
                ->whereHas('template', function ($q) use ($recipe) {
                    $q->where('type', 'recipe')
                        ->where('stats->recipe_id', $recipe->id);
                })
                ->first();

            if ($existingBlueprint) {
                $this->command->info("⏭️  Чертеж '{$recipe->name}' уже есть, пропускаем");
                continue;
            }

            // Создаем шаблон чертежа (если еще нет)
            $blueprintTemplate = ItemTemplate::firstOrCreate(
                ['name' => "Чертёж: {$recipe->name}"],
                [
                    'type' => 'recipe',
                    'is_stackable' => false,
                    'icon' => 'recipe.png',
                    'disassemble_data' => null,
                ]
            );

            // Создаем инстанс чертежа
            ItemInstance::create([
                'template_id' => $blueprintTemplate->id,
                'owner_id' => $userId,
                'quantity' => 1,
                'durability' => 100,
                'stats' => ['recipe_id' => $recipe->id],
            ]);

            $this->command->info("📜 Добавлен чертёж: {$recipe->name}");
        }

        $this->command->info("\n✅ Инвентарь обновлен!");
        $this->command->info("Материалы: 100 дерева, 50 железа");
        $this->command->info("Чертежи: " . $recipes->count() . " шт.");
    }
}
