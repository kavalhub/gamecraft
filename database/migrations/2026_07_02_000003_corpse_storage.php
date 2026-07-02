<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('characters_type')->insertOrIgnore([
            [
                'type' => 'corpse',
                'name' => 'Труп',
                'parent_type' => 'npc',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('storages_type')->insertOrIgnore([
            [
                'type' => 'corpse',
                'name' => 'Добыча с трупа',
                'allowed_types' => json_encode(['slots' => []]),
                'metadata' => json_encode(['grid_cols' => 4]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('storages_type')->where('type', 'corpse')->delete();
        DB::table('characters_type')->where('type', 'corpse')->delete();
    }
};
