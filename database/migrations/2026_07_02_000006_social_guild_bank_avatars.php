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
        Schema::table('characters', function (Blueprint $table) {
            $table->string('avatar', 32)->default('warrior')->after('name');
            $table->string('emblem', 32)->nullable()->after('avatar');
        });

        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('requester_uuid');
            $table->uuid('addressee_uuid');
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->foreign('requester_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
            $table->foreign('addressee_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
            $table->unique(['requester_uuid', 'addressee_uuid']);
            $table->index(['addressee_uuid', 'status']);
        });

        Schema::create('guild_invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('guild_uuid');
            $table->uuid('inviter_uuid');
            $table->uuid('target_uuid');
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->foreign('guild_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
            $table->foreign('inviter_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
            $table->foreign('target_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
            $table->index(['target_uuid', 'status']);
        });

        DB::table('storages_type')
            ->where('type', 'bank')
            ->update([
                'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 80]]]),
                'metadata' => json_encode(['grid_cols' => 15]),
                'updated_at' => now(),
            ]);

        if (!DB::table('storages_type')->where('type', 'guild_bank')->exists()) {
            DB::table('storages_type')->insert([
                'type' => 'guild_bank',
                'name' => 'Банк гильдии',
                'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 200]]]),
                'metadata' => json_encode(['grid_cols' => 15]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('storages_type')->where('type', 'guild_bank')->delete();

        DB::table('storages_type')
            ->where('type', 'bank')
            ->update([
                'allowed_types' => json_encode(['slots' => [['slot_type' => null, 'count' => 100]]]),
                'metadata' => null,
                'updated_at' => now(),
            ]);

        Schema::dropIfExists('guild_invites');
        Schema::dropIfExists('friendships');

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'emblem']);
        });
    }
};
