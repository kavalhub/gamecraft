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
                'type' => 'workbench',
                'parent_type' => null,
                'name' => 'Верстак',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'workbench_blueprint',
                'parent_type' => 'workbench',
                'name' => 'Чертёж на верстаке',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'workbench_material',
                'parent_type' => 'workbench',
                'name' => 'Материал на верстаке',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('storages_type')->insertOrIgnore([
            'type' => 'workbench',
            'name' => 'Верстак',
            'allowed_types' => json_encode([
                'slots' => [
                    ['slot_type' => 'workbench_blueprint', 'count' => 1],
                    ['slot_type' => 'workbench_material', 'count' => 8],
                ],
            ]),
            'metadata' => json_encode(['grid_cols' => 4]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $characters = DB::table('characters')->where('character_type', 'player')->pluck('uuid');

        foreach ($characters as $characterUuid) {
            $exists = DB::table('storages')
                ->where('characters_uuid', $characterUuid)
                ->where('storage_type', 'workbench')
                ->exists();

            if ($exists) {
                continue;
            }

            $storageUuid = (string) \Illuminate\Support\Str::uuid();

            DB::table('storages')->insert([
                'uuid' => $storageUuid,
                'characters_uuid' => $characterUuid,
                'storage_type' => 'workbench',
                'name' => 'Верстак',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('slots')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'storage_uuid' => $storageUuid,
                'slot_type' => 'workbench_blueprint',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            for ($i = 0; $i < 8; $i++) {
                DB::table('slots')->insert([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'storage_uuid' => $storageUuid,
                    'slot_type' => 'workbench_material',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $storageUuids = DB::table('storages')->where('storage_type', 'workbench')->pluck('uuid');
        DB::table('slots')->whereIn('storage_uuid', $storageUuids)->delete();
        DB::table('storages')->where('storage_type', 'workbench')->delete();
        DB::table('storages_type')->where('type', 'workbench')->delete();
        DB::table('slot_types')->whereIn('type', ['workbench_blueprint', 'workbench_material', 'workbench'])->delete();
    }
};
