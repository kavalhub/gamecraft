<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Dto\Content\ContentImportDto;
use App\Models\ItemTemplate;
use App\Models\ResourceBalance;
use App\Models\User;
use App\Services\ContentImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Создаём тестового пользователя
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        // Импортируем контент (шаблоны, рецепты, NPC, магазинные лоты)
        $importService = app(ContentImportService::class);

        $baseContentPath = base_path('content/base.json');
        if (File::exists($baseContentPath)) {
            $data = json_decode(File::get($baseContentPath), true);
            $dto = ContentImportDto::fromArray($data);
            $report = $importService->import($dto);

            $this->command->info('Content imported:');
            $this->command->info("  Templates: {$report['templates']['created']} created, {$report['templates']['updated']} updated");
            $this->command->info("  Recipes: {$report['recipes']['created']} created, {$report['recipes']['updated']} updated");
            $this->command->info("  Disassemble formulas: {$report['disassemble_formulas']['created']} created");
            $this->command->info("  NPCs: {$report['npcs']['created']} created, {$report['npcs']['updated']} updated");
            $this->command->info("  Shop lots: {$report['shop_lots']['created']} created, {$report['shop_lots']['updated']} updated");
        } else {
            $this->command->warn('content/base.json not found');
        }

        // Начисляем тестовому пользователю 1000 золота через resource_balances
        $goldTemplate = ItemTemplate::where('slug', 'gold')->first();
        if ($goldTemplate) {
            ResourceBalance::updateOrCreate(
                ['user_id' => $user->id, 'template_id' => $goldTemplate->id],
                ['quantity' => 1000]
            );
            $this->command->info("  Test user gold: 1000");
        }
    }
}
