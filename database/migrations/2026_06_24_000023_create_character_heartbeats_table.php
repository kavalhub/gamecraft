<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->uuid('character_uuid')->unique();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->foreign('character_uuid')->references('uuid')->on('characters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_heartbeats');
    }
};
