<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_type', 50);
            $table->string('aggregate_type', 50);
            $table->uuid('aggregate_uuid');
            $table->uuid('actor_uuid')->nullable();
            $table->timestamp('occurred_at');
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->uuid('correlation_uuid');
            $table->uuid('causation_uuid')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->index('event_type');
            $table->index(['aggregate_type', 'aggregate_uuid']);
            $table->index('actor_uuid');
            $table->index('occurred_at');
            $table->index('correlation_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
