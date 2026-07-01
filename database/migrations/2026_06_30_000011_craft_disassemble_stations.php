<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('slot_types')->insertOrIgnore([
            [
                'type' => 'craft',
                'parent_type' => null,
                'name' => 'Станция создания',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'craft_center',
                'parent_type' => 'craft',
                'name' => 'Центр станции создания',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'craft_material',
                'parent_type' => 'craft',
                'name' => 'Материал станции создания',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'disassemble',
                'parent_type' => null,
                'name' => 'Станция разбора',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'disassemble_center',
                'parent_type' => 'disassemble',
                'name' => 'Центр станции разбора',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('storages_type')->insertOrIgnore([
            [
                'type' => 'craft',
                'name' => 'Создание',
                'allowed_types' => json_encode([
                    'slots' => [
                        ['slot_type' => 'craft_center', 'count' => 1],
                        ['slot_type' => 'craft_material', 'count' => 8],
                    ],
                ]),
                'metadata' => json_encode(['grid_cols' => 4]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'disassemble',
                'name' => 'Разбор',
                'allowed_types' => json_encode([
                    'slots' => [
                        ['slot_type' => 'disassemble_center', 'count' => 1],
                    ],
                ]),
                'metadata' => json_encode(['grid_cols' => 1]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $workbenchStorageUuids = DB::table('storages')->where('storage_type', 'workbench')->pluck('uuid');
        if ($workbenchStorageUuids->isNotEmpty()) {
            $tempUuids = DB::table('temporary_slots')
                ->whereIn('storage_uuid', $workbenchStorageUuids)
                ->pluck('uuid');

            if ($tempUuids->isNotEmpty()) {
                DB::table('items')->whereIn('temporary_slot_uuid', $tempUuids)->update(['temporary_slot_uuid' => null]);
                DB::table('resources')->whereIn('temporary_slot_uuid', $tempUuids)->update(['temporary_slot_uuid' => null]);
                DB::table('temporary_slots')->whereIn('uuid', $tempUuids)->delete();
            }

            DB::table('slots')->whereIn('storage_uuid', $workbenchStorageUuids)->delete();
            DB::table('storages')->whereIn('uuid', $workbenchStorageUuids)->delete();
        }

        $characters = DB::table('characters')->where('character_type', 'player')->pluck('uuid');

        foreach ($characters as $characterUuid) {
            foreach (['craft', 'disassemble'] as $storageType) {
                $exists = DB::table('storages')
                    ->where('characters_uuid', $characterUuid)
                    ->where('storage_type', $storageType)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $storageUuid = (string) \Illuminate\Support\Str::uuid();
                $name = $storageType === 'craft' ? 'Создание' : 'Разбор';

                DB::table('storages')->insert([
                    'uuid' => $storageUuid,
                    'characters_uuid' => $characterUuid,
                    'storage_type' => $storageType,
                    'name' => $name,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($storageType === 'craft') {
                    DB::table('slots')->insert([
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'storage_uuid' => $storageUuid,
                        'slot_type' => 'craft_center',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    for ($i = 0; $i < 8; $i++) {
                        DB::table('slots')->insert([
                            'uuid' => (string) \Illuminate\Support\Str::uuid(),
                            'storage_uuid' => $storageUuid,
                            'slot_type' => 'craft_material',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    for ($i = 0; $i < 9; $i++) {
                        DB::table('temporary_slots')->insert([
                            'uuid' => (string) \Illuminate\Support\Str::uuid(),
                            'storage_uuid' => $storageUuid,
                            'character_uuid' => $characterUuid,
                            'slot_index' => $i,
                            'active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                } else {
                    DB::table('slots')->insert([
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'storage_uuid' => $storageUuid,
                        'slot_type' => 'disassemble_center',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('temporary_slots')->insert([
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'storage_uuid' => $storageUuid,
                        'character_uuid' => $characterUuid,
                        'slot_index' => 0,
                        'active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        DB::table('storages_type')->where('type', 'workbench')->delete();
        DB::table('slot_types')->whereIn('type', ['workbench', 'workbench_blueprint', 'workbench_material'])->delete();
    }

    public function down(): void
    {
        // Irreversible without data loss — workbench is replaced by craft/disassemble.
    }
};
