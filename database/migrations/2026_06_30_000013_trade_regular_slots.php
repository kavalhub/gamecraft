<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_items', function (Blueprint $table) {
            $table->uuid('origin_slot_uuid')->nullable()->after('resource_uuid');
        });

        DB::table('storages_type')->where('type', 'trade')->update([
            'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 6]]]),
            'metadata' => json_encode(['grid_cols' => 3, 'grid_rows' => 2, 'slot_count' => 6]),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('trade_items', function (Blueprint $table) {
            $table->dropColumn('origin_slot_uuid');
        });

        DB::table('storages_type')->where('type', 'trade')->update([
            'allowed_types' => null,
            'metadata' => json_encode(['grid_cols' => 4, 'grid_rows' => 5, 'temporary_slot_count' => 20]),
            'updated_at' => now(),
        ]);
    }
};
