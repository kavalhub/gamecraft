<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('lot_uuid');
            $table->uuid('seller_uuid');
            $table->uuid('buyer_uuid')->nullable();
            $table->string('template_slug');
            $table->integer('quantity');
            $table->integer('price');
            $table->integer('commission');
            $table->integer('seller_received');
            $table->string('action'); // sold|cancelled
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('lot_uuid');
            $table->index('seller_uuid');
            $table->index('buyer_uuid');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_history');
    }
};
