<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // Уникальный ID события (идемпотентность)

            // Что произошло
            $table->string('event_type', 100)->index(); // user.registered, item.crafted, etc.
            $table->string('aggregate_type', 50)->index(); // user, item, auction
            $table->unsignedBigInteger('aggregate_id')->index(); // ID сущности

            // Кто и когда
            $table->unsignedBigInteger('actor_id')->nullable()->index(); // ID игрока (если применимо)
            $table->timestamp('occurred_at')->index();

            // Данные события
            $table->json('payload'); // {item_id: 5, quantity: 2, ...}
            $table->json('metadata')->nullable(); // IP, user-agent, версия клиента

            // Связи между событиями (цепочки)
            $table->uuid('correlation_id')->nullable()->index(); // ID всей операции (крафт = 1 корреляция)
            $table->uuid('causation_id')->nullable(); // Какое событие вызвало это

            // Для производительности
            $table->unsignedBigInteger('version')->default(1); // Версия агрегата
            $table->boolean('is_snapshot')->default(false); // Это снапшот?

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
