<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('storages_type')->where('type', 'mail')->update([
            'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 6]]]),
            'metadata' => json_encode(['grid_cols' => 6]),
            'updated_at' => now(),
        ]);

        if (!DB::table('storages_type')->where('type', 'mail_compose')->exists()) {
            DB::table('storages_type')->insert([
                'type' => 'mail_compose',
                'name' => 'Составление письма',
                'allowed_types' => null,
                'metadata' => json_encode(['grid_cols' => 6, 'temporary_slot_count' => 6]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('storages_type')->where('type', 'mail')->update([
            'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 100]]]),
            'metadata' => null,
            'updated_at' => now(),
        ]);

        DB::table('storages_type')->where('type', 'mail_compose')->delete();
    }
};
