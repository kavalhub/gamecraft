<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_lots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('storage_uuid')->nullable();
            $table->uuid('seller_uuid');
            $table->string('template_slug');
            $table->integer('quantity');
            $table->integer('price');
            $table->integer('commission_percent')->default(5);
            $table->string('status')->default('active');
            $table->boolean('is_infinite')->default(false);
            $table->uuid('buyer_uuid')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->foreign('storage_uuid')->references('uuid')->on('storages')->onDelete('set null');
            $table->foreign('seller_uuid')->references('uuid')->on('characters')->onDelete('cascade');
            $table->foreign('template_slug')->references('slug')->on('item_templates')->onDelete('restrict');
            $table->index(['status', 'is_infinite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_lots');
    }
};
