<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_stats', function (Blueprint $table) {
            $table->unsignedTinyInteger('base_damage')->default(4)->after('level');
        });

        foreach (DB::table('character_stats')->pluck('id') as $id) {
            DB::table('character_stats')
                ->where('id', $id)
                ->update(['base_damage' => random_int(3, 5)]);
        }
    }

    public function down(): void
    {
        Schema::table('character_stats', function (Blueprint $table) {
            $table->dropColumn('base_damage');
        });
    }
};
