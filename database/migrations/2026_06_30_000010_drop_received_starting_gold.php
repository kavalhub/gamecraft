<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('characters', 'received_starting_gold')) {
            Schema::table('characters', function (Blueprint $table) {
                $table->dropColumn('received_starting_gold');
            });
        }
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->boolean('received_starting_gold')->default(true)->after('active');
        });
    }
};
