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
            $table->foreignId('template_id')->constrained('item_templates')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->unsignedBigInteger('item_instance_id')->nullable();
            $table->json('item_stats')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedTinyInteger('commission_percent')->default(5);
            $table->enum('status', ['active', 'sold', 'cancelled', 'expired'])->default('active');
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

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
