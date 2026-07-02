<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bank', 'guild_bank'] as $type) {
            DB::table('storages_type')
                ->where('type', $type)
                ->update([
                    'metadata' => json_encode(['grid_cols' => 15]),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('storages_type')
            ->where('type', 'bank')
            ->update([
                'metadata' => json_encode(['grid_cols' => 8]),
                'updated_at' => now(),
            ]);

        DB::table('storages_type')
            ->where('type', 'guild_bank')
            ->update([
                'metadata' => json_encode(['grid_cols' => 10]),
                'updated_at' => now(),
            ]);
    }
};
