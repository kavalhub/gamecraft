<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('slot_types')->insertOrIgnore([
            [
                'type' => 'encounter_loot',
                'parent_type' => null,
                'name' => 'Добыча после боя',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'encounter_loot_backing',
                'parent_type' => 'encounter_loot',
                'name' => 'Служебный слот добычи',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('storages_type')->insertOrIgnore([
            [
                'type' => 'encounter_loot',
                'name' => 'Добыча',
                'allowed_types' => json_encode([
                    'slots' => [
                        ['slot_type' => 'encounter_loot_backing', 'count' => 12],
                    ],
                ]),
                'metadata' => json_encode(['grid_cols' => 4]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('storages_type')->where('type', 'encounter_loot')->delete();
        DB::table('slot_types')->whereIn('type', ['encounter_loot', 'encounter_loot_backing'])->delete();
    }
};
