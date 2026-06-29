<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
    {
        DB::table('storages_type')->insertOrIgnore([
            'type' => 'play_panel',
            'name' => 'Панель игры',
            'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 12]]]),
            'metadata' => json_encode(['grid_cols' => 12]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $characters = DB::table('characters')->where('character_type', 'player')->pluck('uuid');

        foreach ($characters as $characterUuid) {
            $exists = DB::table('storages')
                ->where('characters_uuid', $characterUuid)
                ->where('storage_type', 'play_panel')
                ->exists();

            if ($exists) {
                continue;
            }

            $storageUuid = (string) \Illuminate\Support\Str::uuid();

            DB::table('storages')->insert([
                'uuid' => $storageUuid,
                'characters_uuid' => $characterUuid,
                'storage_type' => 'play_panel',
                'name' => 'Панель игры',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            for ($i = 0; $i < 12; $i++) {
                DB::table('slots')->insert([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'storage_uuid' => $storageUuid,
                    'slot_type' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $storageUuids = DB::table('storages')->where('storage_type', 'play_panel')->pluck('uuid');
        DB::table('slots')->whereIn('storage_uuid', $storageUuids)->delete();
        DB::table('storages')->where('storage_type', 'play_panel')->delete();
        DB::table('storages_type')->where('type', 'play_panel')->delete();
    }
};
