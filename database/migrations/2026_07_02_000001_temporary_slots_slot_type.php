<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->string('slot_type')->nullable()->after('slot_index');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->dropColumn('slot_type');
        });
    }
};
