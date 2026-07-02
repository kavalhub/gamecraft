<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['slot_uuid']);
            $table->dropForeign(['temporary_slot_uuid']);
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->dropForeign(['slot_uuid']);
            $table->dropForeign(['temporary_slot_uuid']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->renameColumn('temporary_slot_uuid', 'buffer_slot_uuid');
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->renameColumn('temporary_slot_uuid', 'buffer_slot_uuid');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreign('buffer_slot_uuid')
                ->references('uuid')
                ->on('temporary_slots')
                ->onDelete('set null');
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->foreign('buffer_slot_uuid')
                ->references('uuid')
                ->on('temporary_slots')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['buffer_slot_uuid']);
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->dropForeign(['buffer_slot_uuid']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->renameColumn('buffer_slot_uuid', 'temporary_slot_uuid');
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->renameColumn('buffer_slot_uuid', 'temporary_slot_uuid');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreign('slot_uuid')->references('uuid')->on('slots')->onDelete('restrict');
            $table->foreign('temporary_slot_uuid')->references('uuid')->on('temporary_slots')->onDelete('set null');
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->foreign('slot_uuid')->references('uuid')->on('slots')->onDelete('restrict');
            $table->foreign('temporary_slot_uuid')->references('uuid')->on('temporary_slots')->onDelete('set null');
        });
    }
};
