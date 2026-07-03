<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropForeign(['storage_uuid']);
        });

        Schema::table('mail_messages', function (Blueprint $table) {
            $table->uuid('storage_uuid')->nullable()->change();
            $table->unsignedTinyInteger('attachment_count')->default(0)->after('body');
        });

        Schema::table('mail_messages', function (Blueprint $table) {
            $table->foreign('storage_uuid')->references('uuid')->on('storages')->onDelete('set null');
        });

        if (!Schema::hasColumn('temporary_slots', 'mail_message_uuid')) {
            Schema::table('temporary_slots', function (Blueprint $table) {
                $table->uuid('mail_message_uuid')->nullable()->after('character_uuid');
                $table->index('mail_message_uuid');
            });
        }

        DB::table('storages_type')->where('type', 'mail')->update([
            'type' => 'post_outbox',
            'name' => 'Исходящая почта',
            'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 6]]]),
            'metadata' => json_encode(['grid_cols' => 6]),
            'updated_at' => now(),
        ]);

        if (DB::table('storages_type')->where('type', 'mail_compose')->exists()) {
            DB::table('storages_type')->where('type', 'mail_compose')->delete();
        }

        if (!DB::table('storages_type')->where('type', 'post_inbox')->exists()) {
            DB::table('storages_type')->insert([
                'type' => 'post_inbox',
                'name' => 'Входящая почта',
                'allowed_types' => null,
                'metadata' => json_encode(['grid_cols' => 6]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!DB::table('storages_type')->where('type', 'post_outbox')->exists()) {
            DB::table('storages_type')->insert([
                'type' => 'post_outbox',
                'name' => 'Исходящая почта',
                'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 6]]]),
                'metadata' => json_encode(['grid_cols' => 6]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('temporary_slots', function (Blueprint $table) {
            $table->dropIndex(['mail_message_uuid']);
            $table->dropColumn('mail_message_uuid');
        });

        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropForeign(['storage_uuid']);
            $table->dropColumn('attachment_count');
        });
    }
};
