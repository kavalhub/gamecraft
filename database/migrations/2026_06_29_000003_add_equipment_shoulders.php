<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\Storage;
use App\Services\StorageProvisioningService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('slot_types')->insertOrIgnore([
            'type' => 'equipment_shoulders',
            'parent_type' => 'equipment',
            'name' => 'Плечи',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $equipmentSlots = [
            'slots' => [
                ['slot_type' => 'equipment_head', 'count' => 1],
                ['slot_type' => 'equipment_shoulders', 'count' => 1],
                ['slot_type' => 'equipment_chest', 'count' => 1],
                ['slot_type' => 'equipment_legs', 'count' => 1],
                ['slot_type' => 'equipment_weapon', 'count' => 1],
                ['slot_type' => 'equipment_offhand', 'count' => 1],
                ['slot_type' => 'equipment_ring', 'count' => 2],
                ['slot_type' => 'equipment_amulet', 'count' => 1],
            ],
        ];

        DB::table('storages_type')
            ->where('type', 'equipment')
            ->update(['allowed_types' => json_encode($equipmentSlots)]);

        $provisioning = app(StorageProvisioningService::class);

        Storage::where('storage_type', 'equipment')->each(function (Storage $storage) use ($provisioning) {
            $provisioning->provisionStorageSlots($storage);
        });
    }

    public function down(): void
    {
        DB::table('slot_types')->where('type', 'equipment_shoulders')->delete();

        $equipmentSlots = [
            'slots' => [
                ['slot_type' => 'equipment_head', 'count' => 1],
                ['slot_type' => 'equipment_chest', 'count' => 1],
                ['slot_type' => 'equipment_legs', 'count' => 1],
                ['slot_type' => 'equipment_weapon', 'count' => 1],
                ['slot_type' => 'equipment_offhand', 'count' => 1],
                ['slot_type' => 'equipment_ring', 'count' => 2],
                ['slot_type' => 'equipment_amulet', 'count' => 1],
            ],
        ];

        DB::table('storages_type')
            ->where('type', 'equipment')
            ->update(['allowed_types' => json_encode($equipmentSlots)]);
    }
};
