<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_world_states', function (Blueprint $table) {
            $table->id();
            $table->uuid('character_uuid')->unique();
            $table->string('zone_slug', 64);
            $table->double('x')->default(0);
            $table->double('y')->default(0);
            $table->double('z')->default(0);
            $table->double('rotation_y')->default(0);
            $table->timestamp('moved_at')->useCurrent();
            $table->timestamps();

            $table->foreign('character_uuid')
                ->references('uuid')
                ->on('characters')
                ->cascadeOnDelete();

            $table->index(['zone_slug', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_world_states');
    }
};
