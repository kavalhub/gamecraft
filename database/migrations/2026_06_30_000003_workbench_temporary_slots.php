<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MATERIAL_COUNT = 8;

    public function up(): void
    {
        $now = now();

        $workbenchStorages = DB::table('storages')->where('storage_type', 'workbench')->get();

        foreach ($workbenchStorages as $storage) {
            $characterUuid = $storage->characters_uuid;
            $inventory = DB::table('storages')
                ->where('characters_uuid', $characterUuid)
                ->where('storage_type', 'inventory')
                ->first();

            $workbenchSlotUuids = DB::table('slots')
                ->where('storage_uuid', $storage->uuid)
                ->pluck('uuid');

            foreach ($workbenchSlotUuids as $slotUuid) {
                $this->relocateOccupantsToInventory($slotUuid, $inventory?->uuid);
            }

            $existing = DB::table('temporary_slots')
                ->where('storage_uuid', $storage->uuid)
                ->where('character_uuid', $characterUuid)
                ->count();

            for ($i = $existing; $i < 1 + self::MATERIAL_COUNT; $i++) {
                DB::table('temporary_slots')->insert([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'storage_uuid' => $storage->uuid,
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
        $workbenchStorageUuids = DB::table('storages')->where('storage_type', 'workbench')->pluck('uuid');

        DB::table('temporary_slots')->whereIn('storage_uuid', $workbenchStorageUuids)->delete();
    }

    private function relocateOccupantsToInventory(string $fromSlotUuid, ?string $inventoryStorageUuid): void
    {
        if (!$inventoryStorageUuid) {
            return;
        }

        $inventorySlotUuids = DB::table('slots')
            ->where('storage_uuid', $inventoryStorageUuid)
            ->whereNull('slot_type')
            ->orderBy('id')
            ->pluck('uuid');

        foreach ($inventorySlotUuids as $candidateUuid) {
            $hasItem = DB::table('items')->where('slot_uuid', $candidateUuid)->exists();
            $hasResource = DB::table('resources')->where('slot_uuid', $candidateUuid)->exists();
            if ($hasItem || $hasResource) {
                continue;
            }
            $emptySlotUuid = $candidateUuid;
            break;
        }

        if (!isset($emptySlotUuid)) {
            return;
        }

        $items = DB::table('items')->where('slot_uuid', $fromSlotUuid)->get();
        foreach ($items as $item) {
            if ($emptySlotUuid) {
                DB::table('items')->where('uuid', $item->uuid)->update([
                    'slot_uuid' => $emptySlotUuid,
                    'temporary_slot_uuid' => null,
                    'updated_at' => now(),
                ]);
                $emptySlotUuid = null;
            }
        }

        $resources = DB::table('resources')->where('slot_uuid', $fromSlotUuid)->get();
        foreach ($resources as $resource) {
            if ($emptySlotUuid) {
                DB::table('resources')->where('uuid', $resource->uuid)->update([
                    'slot_uuid' => $emptySlotUuid,
                    'temporary_slot_uuid' => null,
                    'updated_at' => now(),
                ]);
                $emptySlotUuid = null;
            }
        }
    }
};
