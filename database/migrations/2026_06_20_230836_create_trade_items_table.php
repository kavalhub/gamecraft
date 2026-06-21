<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained('trade_offers')->onDelete('cascade');

            // Кто добавил: initiator или partner
            $table->enum('side', ['initiator', 'partner']);

            $table->foreignId('template_id')->constrained('item_templates')->onDelete('cascade');
            $table->unsignedBigInteger('item_instance_id'); // Ссылка на реальный предмет в инвентаре
            $table->integer('quantity')->default(1);

            $table->timestamps();

            $table->index(['trade_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_items');
    }
};
