<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('channel', 32)->default('general');
            $table->string('guild_uuid', 36)->nullable();
            $table->string('character_uuid', 36);
            $table->string('character_name');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['channel', 'id']);
            $table->foreign('character_uuid')->references('uuid')->on('characters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
