<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->dropForeign(['character_uuid']);
        });

        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->uuid('character_uuid')->nullable()->change();
            $table->foreign('character_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->dropForeign(['character_uuid']);
        });

        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->uuid('character_uuid')->nullable(false)->change();
            $table->foreign('character_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
        });
    }
};
