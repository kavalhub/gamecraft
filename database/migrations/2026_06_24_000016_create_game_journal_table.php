<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_journal', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type'); // trade|auction|drop|quest_reward|mail|craft|disassemble
            $table->uuid('item_uuid')->nullable();
            $table->uuid('resource_uuid')->nullable();
            $table->uuid('from_character_uuid')->nullable();
            $table->uuid('to_character_uuid')->nullable();
            $table->uuid('from_slot_uuid')->nullable();
            $table->uuid('to_slot_uuid')->nullable();
            $table->integer('quantity');
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('item_uuid');
            $table->index('from_character_uuid');
            $table->index('to_character_uuid');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_journal');
    }
};
