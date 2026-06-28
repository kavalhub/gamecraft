<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->index(['actor_uuid', 'id'], 'game_events_actor_uuid_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->dropIndex('game_events_actor_uuid_id_index');
        });
    }
};
