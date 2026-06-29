<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->unsignedTinyInteger('slot_index')->nullable()->after('character_uuid');
        });

        DB::table('storages_type')->where('type', 'inventory')->update([
            'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 36]]]),
            'updated_at' => now(),
        ]);

        $this->shrinkInventorySlotsTo36();
    }

    public function down(): void
    {
        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->dropColumn('slot_index');
        });

        DB::table('storages_type')->where('type', 'inventory')->update([
            'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 50]]]),
            'updated_at' => now(),
        ]);
    }

    private function shrinkInventorySlotsTo36(): void
    {
        $inventoryStorages = DB::table('storages')
            ->where('storage_type', 'inventory')
            ->pluck('uuid');

        foreach ($inventoryStorages as $storageUuid) {
            $slots = DB::table('slots')
                ->where('storage_uuid', $storageUuid)
                ->orderBy('id')
                ->get();

            if ($slots->count() <= 36) {
                continue;
            }

            $slotsToRemove = $slots->slice(36);

            foreach ($slotsToRemove as $slot) {
                $hasItem = DB::table('items')->where('slot_uuid', $slot->uuid)->exists();
                $hasResource = DB::table('resources')->where('slot_uuid', $slot->uuid)->exists();

                if (!$hasItem && !$hasResource) {
                    DB::table('slots')->where('uuid', $slot->uuid)->delete();
                }
            }
        }
    }
};
