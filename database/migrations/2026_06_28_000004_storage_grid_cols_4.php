<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('storages_type')->where('type', 'inventory')->update([
            'metadata' => json_encode(['grid_cols' => 4, 'grid_rows' => 9]),
            'updated_at' => now(),
        ]);

        DB::table('storages_type')->where('type', 'trade')->update([
            'metadata' => json_encode(['grid_cols' => 4, 'grid_rows' => 5, 'temporary_slot_count' => 20]),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('storages_type')->where('type', 'inventory')->update([
            'metadata' => json_encode(['grid_cols' => 6]),
            'updated_at' => now(),
        ]);

        DB::table('storages_type')->where('type', 'trade')->update([
            'metadata' => json_encode(['grid_cols' => 5, 'temporary_slot_count' => 20]),
            'updated_at' => now(),
        ]);
    }
};
