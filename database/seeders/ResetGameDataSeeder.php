<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResetGameDataSeeder extends Seeder
{
    /**
     * Таблицы со справочными данными (оставляем)
     */
    private const KEEP_TABLES = [
        'item_templates',
        'recipes',
        'recipe_components',
        'migrations',
    ];

    /**
     * Таблицы с данными игроков (очищаем)
     */
    private const RESET_TABLES = [
        'game_events',
        'item_instances',
        'users',
    ];

    public function run(): void
    {
        $this->command->warn('⚠️  ВНИМАНИЕ: Этот сидер удалит ВСЕ данные игроков!');
        $this->command->info('Будут очищены таблицы: ' . implode(', ', self::RESET_TABLES));
        $this->command->info('Будут сохранены: ' . implode(', ', self::KEEP_TABLES));

        if (!$this->command->confirm('Продолжить? Это действие необратимо', false)) {
            $this->command->info('Отменено.');
            return;
        }

        $this->command->newLine();
        $this->command->info('🔄 Начинаем сброс...');

        // Отключаем проверки внешних ключей для безопасного удаления
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::RESET_TABLES as $table) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    $this->command->warn("  ⏭️  Таблица {$table} не существует, пропускаем");
                    continue;
                }

                $count = DB::table($table)->count();
                DB::table($table)->truncate();

                $this->command->info("  🗑️  {$table}: удалено {$count} записей");
            }
        } finally {
            // Включаем проверки обратно
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->command->newLine();
        $this->command->info('✅ Сброс завершён!');
        $this->command->newLine();
        $this->command->info('📊 Текущее состояние справочных таблиц:');

        foreach (['item_templates', 'recipes', 'recipe_components'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $count = DB::table($table)->count();
                $this->command->info("  • {$table}: {$count} записей");
            }
        }

        $this->command->newLine();
        $this->command->info('💡 Теперь можно:');
        $this->command->info('   1. Зарегистрировать нового игрока через веб-интерфейс');
        $this->command->info('   2. Запустить DebugInventorySeeder для тестовых данных:');
        $this->command->info('      php artisan db:seed --class=DebugInventorySeeder');
    }
}
