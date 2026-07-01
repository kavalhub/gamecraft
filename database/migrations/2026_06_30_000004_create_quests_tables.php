<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('giver_character_uuid')->nullable();
            $table->json('prerequisites')->nullable();
            $table->json('rewards')->nullable();
            $table->boolean('repeatable')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('quest_objectives', function (Blueprint $table) {
            $table->id();
            $table->string('quest_slug');
            $table->string('objective_key');
            $table->string('type');
            $table->json('config');
            $table->unsignedInteger('required_count')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['quest_slug', 'objective_key']);
            $table->foreign('quest_slug')->references('slug')->on('quests')->cascadeOnDelete();
        });

        Schema::create('character_quests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('character_uuid');
            $table->string('quest_slug');
            $table->string('status');
            $table->json('progress')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('turned_in_at')->nullable();
            $table->timestamps();

            $table->unique(['character_uuid', 'quest_slug']);
            $table->foreign('character_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
            $table->foreign('quest_slug')->references('slug')->on('quests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_quests');
        Schema::dropIfExists('quest_objectives');
        Schema::dropIfExists('quests');
    }
};
