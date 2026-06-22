<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Dto\Content\ContentImportDto;
use App\Models\User;
use App\Services\ContentImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'gold' => 1000,
            ]
        );

        $importService = app(ContentImportService::class);

        $baseContentPath = base_path('content/base.json');
        if (File::exists($baseContentPath)) {
            $data = json_decode(File::get($baseContentPath), true);
            $dto = ContentImportDto::fromArray($data);
            $report = $importService->import($dto);

            $this->command->info('Content imported:');
            $this->command->info("  Templates: {$report['templates']['created']} created, {$report['templates']['updated']} updated");
            $this->command->info("  Recipes: {$report['recipes']['created']} created, {$report['recipes']['updated']} updated");
            $this->command->info("  NPCs: {$report['npcs']['created']} created, {$report['npcs']['updated']} updated");
            $this->command->info("  Shop lots: {$report['shop_lots']['created']} created, {$report['shop_lots']['updated']} updated");
        } else {
            $this->command->warn('content/base.json not found');
        }
    }
}
