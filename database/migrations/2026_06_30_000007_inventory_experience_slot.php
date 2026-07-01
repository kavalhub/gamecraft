<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('storages_type')->where('type', 'inventory')->update([
            'allowed_types' => json_encode([
                'slots' => [
                    [
                        'slot_type' => 'gold',
                        'count' => 1,
                        'hidden' => true,
                        'priority_fill' => true,
                        'auto_reclaim' => true,
                    ],
                    [
                        'slot_type' => 'experience',
                        'count' => 1,
                        'hidden' => true,
                        'priority_fill' => true,
                        'auto_reclaim' => true,
                    ],
                    [
                        'slot_type' => null,
                        'count' => 36,
                    ],
                ],
            ]),
            'updated_at' => now(),
        ]);

        $this->provisionExperienceSlots();
    }

    public function down(): void
    {
        DB::table('storages_type')->where('type', 'inventory')->update([
            'allowed_types' => json_encode([
                'slots' => [
                    [
                        'slot_type' => 'gold',
                        'count' => 1,
                        'hidden' => true,
                        'priority_fill' => true,
                        'auto_reclaim' => true,
                    ],
                    [
                        'slot_type' => null,
                        'count' => 36,
                    ],
                ],
            ]),
            'updated_at' => now(),
        ]);

        $inventories = DB::table('storages')->where('storage_type', 'inventory')->pluck('uuid');
        DB::table('slots')->whereIn('storage_uuid', $inventories)->where('slot_type', 'experience')->delete();
    }

    private function provisionExperienceSlots(): void
    {
        $inventories = DB::table('storages')->where('storage_type', 'inventory')->get();

        foreach ($inventories as $storage) {
            $exists = DB::table('slots')
                ->where('storage_uuid', $storage->uuid)
                ->where('slot_type', 'experience')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('slots')->insert([
                'uuid' => Str::uuid()->toString(),
                'storage_uuid' => $storage->uuid,
                'slot_type' => 'experience',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
