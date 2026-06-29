<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $inventories = DB::table('storages')->where('storage_type', 'inventory')->pluck('uuid');

        foreach ($inventories as $storageUuid) {
            $nullSlots = DB::table('slots')
                ->where('storage_uuid', $storageUuid)
                ->whereNull('slot_type')
                ->orderBy('id')
                ->get();

            if ($nullSlots->count() <= 36) {
                continue;
            }

            foreach ($nullSlots->slice(36) as $slot) {
                $hasItem = DB::table('items')->where('slot_uuid', $slot->uuid)->exists();
                $hasResource = DB::table('resources')->where('slot_uuid', $slot->uuid)->exists();

                if (!$hasItem && !$hasResource) {
                    DB::table('slots')->where('uuid', $slot->uuid)->delete();
                }
            }
        }
    }

    public function down(): void
    {
        // Не восстанавливаем лишние слоты
    }
};
