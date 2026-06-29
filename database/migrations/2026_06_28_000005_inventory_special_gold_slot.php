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
                        'slot_type' => null,
                        'count' => 36,
                    ],
                ],
            ]),
            'updated_at' => now(),
        ]);

        $this->provisionGoldSlotsAndConsolidate();
    }

    public function down(): void
    {
        DB::table('storages_type')->where('type', 'inventory')->update([
            'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 36]]]),
            'updated_at' => now(),
        ]);
    }

    private function provisionGoldSlotsAndConsolidate(): void
    {
        $inventories = DB::table('storages')->where('storage_type', 'inventory')->get();

        foreach ($inventories as $storage) {
            $goldSlotUuid = DB::table('slots')
                ->where('storage_uuid', $storage->uuid)
                ->where('slot_type', 'gold')
                ->value('uuid');

            if (!$goldSlotUuid) {
                $goldSlotUuid = Str::uuid()->toString();
                DB::table('slots')->insert([
                    'uuid' => $goldSlotUuid,
                    'storage_uuid' => $storage->uuid,
                    'slot_type' => 'gold',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $gridSlotUuids = DB::table('slots')
                ->where('storage_uuid', $storage->uuid)
                ->whereNull('slot_type')
                ->pluck('uuid');

            $goldInGrid = DB::table('resources')
                ->whereIn('slot_uuid', $gridSlotUuids)
                ->where('template_slug', 'gold')
                ->whereNull('temporary_slot_uuid')
                ->get();

            $goldInSpecial = DB::table('resources')
                ->where('slot_uuid', $goldSlotUuid)
                ->where('template_slug', 'gold')
                ->whereNull('temporary_slot_uuid')
                ->first();

            $totalGold = (int) $goldInGrid->sum('quantity') + (int) ($goldInSpecial->quantity ?? 0);

            foreach ($goldInGrid as $row) {
                DB::table('resources')->where('uuid', $row->uuid)->delete();
            }

            if ($goldInSpecial) {
                DB::table('resources')->where('uuid', $goldInSpecial->uuid)->update([
                    'quantity' => $totalGold,
                    'updated_at' => now(),
                ]);
            } elseif ($totalGold > 0) {
                DB::table('resources')->insert([
                    'uuid' => Str::uuid()->toString(),
                    'slot_uuid' => $goldSlotUuid,
                    'recipe_slug' => 'gold',
                    'template_slug' => 'gold',
                    'slot_type' => 'gold',
                    'max_stack' => null,
                    'quantity' => $totalGold,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
