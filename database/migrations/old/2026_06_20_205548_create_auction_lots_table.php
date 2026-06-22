<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');

            // Что продаём
            $table->foreignId('template_id')->constrained('item_templates')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->unsignedBigInteger('item_instance_id')->nullable(); // Для экипировки (не стакуемой)
            $table->json('item_stats')->nullable(); // Копия stats предмета на момент выставления

            // Цена
            $table->unsignedBigInteger('price');
            $table->unsignedTinyInteger('commission_percent')->default(5); // Комиссия %

            // Статус
            $table->enum('status', ['active', 'sold', 'cancelled', 'expired'])->default('active');

            // Покупатель (заполняется при продаже)
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sold_at')->nullable();

            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index(['status', 'template_id']);
            $table->index('seller_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_lots');
    }
};
