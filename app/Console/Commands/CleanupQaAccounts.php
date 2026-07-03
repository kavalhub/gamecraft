<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QaAccountCleanupService;
use Illuminate\Console\Command;

class CleanupQaAccounts extends Command
{
    protected $signature = 'game:cleanup-qa
                            {--keep-test-user : Не удалять test@example.com (для PHPUnit)}';

    protected $description = 'Удалить QA-ботов (bot_a_*, bot_b_*), Test Character и тестовые гильдии';

    public function handle(QaAccountCleanupService $cleanup): int
    {
        $includeTestUser = ! $this->option('keep-test-user');
        $deleted = $cleanup->cleanup($includeTestUser);

        if ($deleted === []) {
            $this->info('QA-аккаунты не найдены.');

            return self::SUCCESS;
        }

        $this->info('Удалено:');
        foreach ($deleted as $label) {
            $this->line('  • ' . $label);
        }

        return self::SUCCESS;
    }
}
