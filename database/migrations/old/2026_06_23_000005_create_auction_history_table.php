<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('auction_lots')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('template_id')->constrained('item_templates')->onDelete('cascade');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('commission')->default(0);
            $table->unsignedBigInteger('seller_received')->default(0);
            $table->enum('action', ['listed', 'sold', 'cancelled', 'expired']);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('lot_id');
            $table->index('seller_id');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_history');
    }
};
