<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const GRANT_COUNT = 6;
    private const TURNIN_COUNT = 6;
    private const BACKING_COUNT = 12;

    public function up(): void
    {
        $now = now();

        DB::table('slot_types')->insertOrIgnore([
            [
                'type' => 'quest',
                'parent_type' => null,
                'name' => 'Квест',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'quest_grant',
                'parent_type' => 'quest',
                'name' => 'Выдача квеста',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'quest_turnin',
                'parent_type' => 'quest',
                'name' => 'Сдача квеста',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'quest_backing',
                'parent_type' => 'quest',
                'name' => 'Служебный слот квеста',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('storages_type')->insertOrIgnore([
            'type' => 'quest',
            'name' => 'Квест',
            'allowed_types' => json_encode([
                'slots' => [
                    ['slot_type' => 'quest_backing', 'count' => self::BACKING_COUNT, 'hidden' => true],
                ],
            ]),
            'metadata' => json_encode(['grid_cols' => 6]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $characters = DB::table('characters')->where('character_type', 'player')->pluck('uuid');

        foreach ($characters as $characterUuid) {
            $exists = DB::table('storages')
                ->where('characters_uuid', $characterUuid)
                ->where('storage_type', 'quest')
                ->exists();

            if ($exists) {
                continue;
            }

            $storageUuid = (string) Str::uuid();

            DB::table('storages')->insert([
                'uuid' => $storageUuid,
                'characters_uuid' => $characterUuid,
                'storage_type' => 'quest',
                'name' => 'Квест',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            for ($i = 0; $i < self::BACKING_COUNT; $i++) {
                DB::table('slots')->insert([
                    'uuid' => (string) Str::uuid(),
                    'storage_uuid' => $storageUuid,
                    'slot_type' => 'quest_backing',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            for ($i = 0; $i < self::GRANT_COUNT + self::TURNIN_COUNT; $i++) {
                DB::table('temporary_slots')->insert([
                    'uuid' => (string) Str::uuid(),
                    'storage_uuid' => $storageUuid,
                    'character_uuid' => $characterUuid,
                    'slot_index' => $i,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $storageUuids = DB::table('storages')->where('storage_type', 'quest')->pluck('uuid');
        DB::table('temporary_slots')->whereIn('storage_uuid', $storageUuids)->delete();
        DB::table('slots')->whereIn('storage_uuid', $storageUuids)->delete();
        DB::table('storages')->where('storage_type', 'quest')->delete();
        DB::table('storages_type')->where('type', 'quest')->delete();
        DB::table('slot_types')->whereIn('type', ['quest_grant', 'quest_turnin', 'quest_backing', 'quest'])->delete();
    }
};
